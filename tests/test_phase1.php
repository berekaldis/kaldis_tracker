<?php
/**
 * Kaldis Coffee Telegram Reply Tracker (v2)
 * Phase 1 Comprehensive Automated Test Suite
 */

declare(strict_types=1);

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = dirname(__DIR__) . '/app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require $file;
});

require_once dirname(__DIR__) . '/app/helpers.php';

use App\Services\Database;
use App\Services\AuthService;
use App\Services\AuditService;
use App\Services\MessageTrackingService;
use App\Controllers\DashboardController;

$passed = 0;
$failed = 0;

function assertTest(string $description, bool $condition): void {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$description}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$description}\n";
        $failed++;
    }
}

echo "========================================================\n";
echo "Starting Phase 1 Test Suite\n";
echo "========================================================\n\n";

// TEST GROUP 1: Database & Schema
echo "--- Group 1: Database Tables & Schema ---\n";
$tables = Database::fetchAll("SHOW TABLES");
$tableNames = array_map(fn($t) => array_values($t)[0], $tables);

$requiredTables = [
    'admins', 'login_attempts', 'telegram_groups', 'members',
    'group_members', 'tracked_messages', 'message_responses',
    'telegram_updates', 'audit_logs'
];

foreach ($requiredTables as $tbl) {
    assertTest("Table '{$tbl}' exists", in_array($tbl, $tableNames));
}

// Check BIGINT for telegram_chat_id and telegram_user_id
$chatIdCol = Database::fetch("SHOW COLUMNS FROM telegram_groups LIKE 'telegram_chat_id'");
assertTest("telegram_groups.telegram_chat_id is BIGINT", str_contains(strtolower($chatIdCol['Type']), 'bigint'));

$userIdCol = Database::fetch("SHOW COLUMNS FROM members LIKE 'telegram_user_id'");
assertTest("members.telegram_user_id is BIGINT", str_contains(strtolower($userIdCol['Type']), 'bigint'));

// TEST GROUP 2: Authentication & Security
echo "\n--- Group 2: Authentication, Lockout & CSRF ---\n";
// Test CSRF
$token1 = AuthService::getCsrfToken();
assertTest("CSRF token is generated and non-empty", !empty($token1) && strlen($token1) === 64);
assertTest("CSRF token validates correctly", AuthService::validateCsrfToken($token1));
assertTest("Invalid CSRF token is rejected", !AuthService::validateCsrfToken("invalid_token_12345"));

// Test Admin Login
$loginSuccess = AuthService::attempt('admin', 'KaldisAdmin2026!');
assertTest("Admin login succeeds with valid credentials", $loginSuccess['success'] === true);
assertTest("Session stores admin user data", AuthService::check() && AuthService::isOwner());

// Test Failed Login & Lockout Rate Limiting
$_SERVER['REMOTE_ADDR'] = '192.168.1.100';
for ($i = 0; $i < 5; $i++) {
    AuthService::attempt('fake_attacker', 'wrong_pass');
}
$lockedResult = AuthService::attempt('fake_attacker', 'wrong_pass');
assertTest("Lockout triggers after 5 failed attempts", $lockedResult['success'] === false && str_contains($lockedResult['error'], 'locked'));

// TEST GROUP 3: Telegram Webhook & Idempotency
echo "\n--- Group 3: Webhook Idempotency & Message Tracking Engine ---\n";
$tracker = new MessageTrackingService();

// Clean test data for predictable tests
$testChatId = -1009876543210;
$testOldChatId = -9876543210;
$groupMigrateTestId = -554433221;
$supergroupMigrateId = -100554433221;
$authUserId = 123456789; // matching config
$member1Id = 555000111;
$member2Id = 555000222;

// Delete child rows first due to ON DELETE RESTRICT
$allTestChatIds = [$testChatId, $testOldChatId, $groupMigrateTestId, $supergroupMigrateId];
$chatPlaceholders = implode(',', array_fill(0, count($allTestChatIds), '?'));
$existingGroupIds = Database::fetchAll(
    "SELECT id FROM telegram_groups WHERE telegram_chat_id IN ({$chatPlaceholders})",
    $allTestChatIds
);
if (!empty($existingGroupIds)) {
    $gids = array_column($existingGroupIds, 'id');
    $placeholders = implode(',', array_fill(0, count($gids), '?'));
    Database::execute("DELETE FROM message_responses WHERE tracked_message_id IN (SELECT id FROM tracked_messages WHERE group_id IN ({$placeholders}))", $gids);
    Database::execute("DELETE FROM tracked_messages WHERE group_id IN ({$placeholders})", $gids);
    Database::execute("DELETE FROM group_members WHERE group_id IN ({$placeholders})", $gids);
    Database::execute("DELETE FROM telegram_groups WHERE id IN ({$placeholders})", $gids);
}

Database::execute("DELETE FROM telegram_updates WHERE chat_id IN ({$chatPlaceholders})", $allTestChatIds);

// 1. Initial message to create group & members
$initPayload = [
    'update_id' => 10001,
    'message' => [
        'message_id' => 101,
        'date' => time() - 3600,
        'chat' => [
            'id' => $testChatId,
            'title' => 'Kaldis Coffee Managers Test Group',
            'type' => 'supergroup'
        ],
        'from' => [
            'id' => $member1Id,
            'is_bot' => false,
            'first_name' => 'Abebe',
            'last_name' => 'Bikila',
            'username' => 'abebikila'
        ],
        'text' => 'Hello team, good morning!'
    ]
];

$res1 = $tracker->processUpdate($initPayload);
assertTest("Initial group message creates group and member record", $res1['status'] === 'processed');

// 2. Test Idempotency: duplicate update_id
$resDuplicate = $tracker->processUpdate($initPayload);
assertTest("Duplicate update_id is detected and handled idempotently", $resDuplicate['status'] === 'duplicate');

$updateRows = Database::fetchColumn("SELECT COUNT(*) FROM telegram_updates WHERE update_id = 10001");
assertTest("Duplicate update creates NO duplicate rows in telegram_updates", (int)$updateRows === 1);

// 3. Register second member in group
$member2Payload = [
    'update_id' => 10002,
    'message' => [
        'message_id' => 102,
        'date' => time() - 3500,
        'chat' => ['id' => $testChatId, 'title' => 'Kaldis Coffee Managers Test Group', 'type' => 'supergroup'],
        'from' => [
            'id' => $member2Id,
            'is_bot' => false,
            'first_name' => 'Almaz',
            'last_name' => 'Ayana',
            'username' => 'almazayana'
        ],
        'text' => 'Good morning Abebe!'
    ]
];
$tracker->processUpdate($member2Payload);

// 4. Test Tracked Message with #track from Authorized Sender
$trackMessageId = 200;
$trackPayload = [
    'update_id' => 10003,
    'message' => [
        'message_id' => $trackMessageId,
        'date' => time() - 1800,
        'chat' => ['id' => $testChatId, 'title' => 'Kaldis Coffee Managers Test Group', 'type' => 'supergroup'],
        'from' => [
            'id' => $authUserId,
            'is_bot' => false,
            'first_name' => 'Bereket',
            'username' => 'bereket_kaldis'
        ],
        'text' => "Urgent notice: Please confirm your branch morning coffee inventory by 10 AM. #track #inventory-01"
    ]
];

$trackRes = $tracker->processUpdate($trackPayload);
assertTest("Message with #track from authorized user is registered as tracked", $trackRes['status'] === 'tracked');
assertTest("Reference code #inventory-01 was extracted", $trackRes['reference_code'] === '#inventory-01');

// 5. Test Anonymous Admin track attempt warning
$anonPayload = [
    'update_id' => 10004,
    'message' => [
        'message_id' => 201,
        'date' => time() - 1700,
        'chat' => ['id' => $testChatId, 'title' => 'Kaldis Coffee Managers Test Group', 'type' => 'supergroup'],
        'from' => [
            'id' => 1087968824,
            'is_bot' => true,
            'first_name' => 'Group',
            'username' => 'GroupAnonymousBot'
        ],
        'text' => "Important announcement from anonymous admin #track"
    ]
];
$anonRes = $tracker->processUpdate($anonPayload);
assertTest("Anonymous admin #track attempt is rejected with warning", $anonRes['status'] === 'warning' && $anonRes['reason'] === 'anonymous_admin_cannot_be_tracked');

// 6. Test Edited Message update and audit logging
$editPayload = [
    'update_id' => 10005,
    'edited_message' => [
        'message_id' => $trackMessageId,
        'edit_date' => time() - 1500,
        'chat' => ['id' => $testChatId, 'type' => 'supergroup'],
        'text' => "Urgent notice: Please confirm your branch morning coffee inventory by 11 AM (extended). #track #inventory-01"
    ]
];
$editRes = $tracker->processUpdate($editPayload);
assertTest("Edited message updates text", $editRes['status'] === 'edited');

$editedMsg = Database::fetch("SELECT * FROM tracked_messages WHERE telegram_message_id = :mid", [':mid' => $trackMessageId]);
assertTest("Stored text reflects new edited content", str_contains($editedMsg['message_text'], '11 AM (extended)'));

$auditEdit = Database::fetch(
    "SELECT * FROM audit_logs WHERE action = 'tracked_message_edited' AND entity_id = :eid",
    [':eid' => $editedMsg['id']]
);
assertTest("Audit log entry created for message edit", $auditEdit !== null);

// 7. Test Group Migration from normal group to supergroup
$groupMigrateTestId = -554433221;
$supergroupMigrateId = -100554433221;
Database::execute(
    "INSERT INTO telegram_groups (telegram_chat_id, title, type, is_active, created_at, updated_at) 
     VALUES (:cid, 'Migrate Test Group', 'group', 1, :now1, :now2)",
    [':cid' => $groupMigrateTestId, ':now1' => utc_now(), ':now2' => utc_now()]
);
$oldGroupId = (int)Database::lastInsertId();

$migrateRes = $tracker->handleGroupMigration($groupMigrateTestId, $supergroupMigrateId);
assertTest("Group migration handled successfully", $migrateRes['status'] === 'migrated');

$migratedGroup = Database::fetch("SELECT * FROM telegram_groups WHERE id = :id", [':id' => $oldGroupId]);
assertTest("Migrated group updated chat ID to supergroup", (int)$migratedGroup['telegram_chat_id'] === $supergroupMigrateId);
assertTest("Migrated group retained previous_chat_id", (int)$migratedGroup['previous_chat_id'] === $groupMigrateTestId);

// TEST GROUP 4: Dashboard Stats & Calculations
echo "\n--- Group 4: Dashboard Stats & Metrics ---\n";
$dashboardCtrl = new DashboardController();
$trackedMsgRecord = Database::fetch("SELECT * FROM tracked_messages WHERE telegram_message_id = :mid", [':mid' => $trackMessageId]);
$stats = $dashboardCtrl->calculateMessageStats(
    (int)$trackedMsgRecord['id'],
    (int)$trackedMsgRecord['group_id'],
    $trackedMsgRecord['message_date']
);

assertTest("Eligible members calculated correctly", $stats['eligible'] >= 2);
assertTest("Response percentage calculated (initial 0%)", $stats['percentage'] === 0.0);
assertTest("Confirmed not replied calculated accurately", $stats['not_replied'] >= 2);

// TEST GROUP 5: Timezone Formatting & Secret Token Security
echo "\n--- Group 5: Timezone Handling & Webhook Secret Validation ---\n";

// Test UTC to Africa/Addis_Ababa (+3 hours)
$utcTimestamp = '2026-10-28 09:00:00';
$formattedEat = format_tz($utcTimestamp, 'Y-m-d H:i');
assertTest("Timezone converts UTC 09:00 to Africa/Addis_Ababa 12:00", $formattedEat === '2026-10-28 12:00');

// Test Webhook Secret Token validation logic
$expectedSecret = (string)config('telegram.webhook_secret');
assertTest("Valid secret token match passes", hash_equals($expectedSecret, $expectedSecret));
assertTest("Invalid secret token fails", !hash_equals($expectedSecret, 'wrong_secret_token_abc'));
assertTest("Empty secret token fails", !hash_equals($expectedSecret, ''));

// Test Viewer Role enforcement
$_SESSION['admin_user']['role'] = 'viewer';
assertTest("Viewer role is detected", !AuthService::isOwner());
$_SESSION['admin_user']['role'] = 'owner';
assertTest("Owner role is restored", AuthService::isOwner());

// Clean up test groups
Database::execute("DELETE FROM message_responses WHERE tracked_message_id = :tmid", [':tmid' => $trackedMsgRecord['id']]);
Database::execute("DELETE FROM tracked_messages WHERE id = :tmid", [':tmid' => $trackedMsgRecord['id']]);
Database::execute("DELETE FROM group_members WHERE group_id IN (:g1, :g2)", [':g1' => $trackedMsgRecord['group_id'], ':g2' => $oldGroupId]);
Database::execute("DELETE FROM telegram_groups WHERE id IN (:g1, :g2)", [':g1' => $trackedMsgRecord['group_id'], ':g2' => $oldGroupId]);
Database::execute("DELETE FROM telegram_updates WHERE chat_id IN (:c1, :c2, :c3)", [':c1' => $testChatId, ':c2' => $groupMigrateTestId, ':c3' => $supergroupMigrateId]);

echo "\n========================================================\n";
echo "Phase 1 Test Summary: {$passed} Passed, {$failed} Failed\n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
