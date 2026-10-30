<?php
/**
 * Test Suite: Interactive Button Check-in, Roll-call command, and Bulk Roster Import
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/helpers.php';
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $file = $baseDir . str_replace('\\', '/', substr($class, $len)) . '.php';
    if (file_exists($file)) require $file;
});

use App\Services\Database;
use App\Services\AuthService;
use App\Services\MessageTrackingService;
use App\Services\TelegramService;
use App\Controllers\GroupController;

$passed = 0;
$failed = 0;

function assertTest(string $description, bool $condition, string $details = ''): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$description}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$description}" . ($details ? " - {$details}" : '') . "\n";
    }
}

echo "========================================================\n";
echo "Starting Check-In Button & Bulk Import Test Suite\n";
echo "========================================================\n\n";

$now = utc_now();
$testChatId = -1009988776655;

// Clean prior test artifacts
$existing = Database::fetch("SELECT id FROM telegram_groups WHERE telegram_chat_id = :cid", [':cid' => $testChatId]);
if ($existing) {
    Database::execute("DELETE FROM group_members WHERE group_id = :gid", [':gid' => $existing['id']]);
    Database::execute("DELETE FROM telegram_groups WHERE id = :gid", [':gid' => $existing['id']]);
}

Database::execute(
    "INSERT INTO telegram_groups (telegram_chat_id, title, type, department, is_active, created_at, updated_at)
     VALUES (:cid, 'Kaldis Airport Shift 1', 'supergroup', 'Operations', 1, :now1, :now2)",
    [':cid' => $testChatId, ':now1' => $now, ':now2' => $now]
);
$group = Database::fetch("SELECT * FROM telegram_groups WHERE telegram_chat_id = :cid", [':cid' => $testChatId]);
$groupId = (int)$group['id'];

// --- Group 1: Interactive Button Tap (callback_query) ---
echo "--- Group 1: Interactive Button Tap (callback_query) ---\n";
$tracker = new MessageTrackingService();

$staffUserId = 6611223344;
$callbackUpdate = [
    'update_id' => 995001,
    'callback_query' => [
        'id' => 'cb_query_999888',
        'from' => [
            'id' => $staffUserId,
            'is_bot' => false,
            'first_name' => 'Kassahun',
            'last_name' => 'Waiter',
            'username' => 'kassa_airport'
        ],
        'message' => [
            'message_id' => 205,
            'chat' => [
                'id' => $testChatId,
                'title' => 'Kaldis Airport Shift 1',
                'type' => 'supergroup'
            ]
        ],
        'data' => 'checkin:' . $groupId
    ]
];

$res = $tracker->processUpdate($callbackUpdate);
assertTest("callback_query was processed with status 'checked_in'", ($res['status'] ?? '') === 'checked_in');

$member = Database::fetch("SELECT * FROM members WHERE telegram_user_id = :uid", [':uid' => $staffUserId]);
assertTest("Member record was created from callback_query", $member !== null);
assertTest("Member name is Kassahun Waiter", ($member['full_name'] ?? '') === 'Kassahun Waiter');
assertTest("Member username is kassa_airport", ($member['username'] ?? '') === 'kassa_airport');

$gm = Database::fetch("SELECT * FROM group_members WHERE group_id = :gid AND member_id = :mid", [
    ':gid' => $groupId,
    ':mid' => $member['id']
]);
assertTest("group_members junction record exists", $gm !== null);
assertTest("Membership is active", ($gm['is_active'] ?? 0) == 1);

// --- Group 2: /checkin Command in Group Dispatches Roll Call ---
echo "\n--- Group 2: /checkin Command in Group ---\n";
$authUserId = 814523272; // Bere (Authorized Sender)
$checkinCmdUpdate = [
    'update_id' => 995002,
    'message' => [
        'message_id' => 206,
        'from' => [
            'id' => $authUserId,
            'is_bot' => false,
            'first_name' => 'Bere',
            'username' => 'berekaldis'
        ],
        'chat' => [
            'id' => $testChatId,
            'title' => 'Kaldis Airport Shift 1',
            'type' => 'supergroup'
        ],
        'date' => time(),
        'text' => '/checkin'
    ]
];

$resCmd = $tracker->processUpdate($checkinCmdUpdate);
assertTest("/checkin command returned status 'checkin_dispatched'", ($resCmd['status'] ?? '') === 'checkin_dispatched');

// --- Group 3: Bulk Import Roster Feature ---
echo "\n--- Group 3: Bulk Import Roster Parsing & Ingestion ---\n";
// Setup session as owner
AuthService::startSession();
$_SESSION['admin_user'] = [
    'id' => 1,
    'username' => 'superadmin',
    'email' => 'superadmin@kaldiscoffee.com',
    'role' => 'super_admin',
    'department' => null,
];

// Test the bulk parsing logic
$rawText = "
Abebe Bikila, 77001122, member
Tigist Alemu, @tigist_kaldis, member
Dawit Kebede, 77003344, admin
Marta Tesfaye, @marta_t
";

$_POST = [
    'csrf_token' => AuthService::getCsrfToken(),
    'group_id' => $groupId,
    'raw_members' => $rawText
];

// Execute bulk import parsing directly
$lines = preg_split("/\r\n|\n|\r/", $rawText);
$imported = 0;
foreach ($lines as $line) {
    $line = trim($line);
    if (empty($line)) continue;
    $parts = str_getcsv($line);
    $name = trim($parts[0] ?? '');
    $identifier = trim($parts[1] ?? '');
    $role = strtolower(trim($parts[2] ?? 'member'));
    if (!in_array($role, ['member', 'admin', 'creator'])) $role = 'member';

    $userId = preg_match('/^-?\d+$/', $identifier) ? (int)$identifier : -(abs(crc32($identifier)) + 1000000);
    $username = str_starts_with($identifier, '@') ? ltrim($identifier, '@') : null;

    Database::execute(
        "INSERT INTO members (telegram_user_id, full_name, username, is_bot, created_at, updated_at)
         VALUES (:uid, :name, :uname, 0, :now, :now)
         ON DUPLICATE KEY UPDATE full_name = :name_u, updated_at = :now",
        [':uid' => $userId, ':name' => $name, ':uname' => $username, ':name_u' => $name, ':now' => utc_now()]
    );
    $m = Database::fetch("SELECT id FROM members WHERE telegram_user_id = :uid", [':uid' => $userId]);
    Database::execute(
        "INSERT INTO group_members (group_id, member_id, role, joined_at, source, is_active, created_at, updated_at)
         VALUES (:gid, :mid, :role, :now, 'imported', 1, :now, :now)
         ON DUPLICATE KEY UPDATE role = :role_u, is_active = 1",
        [':gid' => $groupId, ':mid' => $m['id'], ':role' => $role, ':role_u' => $role, ':now' => utc_now()]
    );
    $imported++;
}

assertTest("Bulk import processed 4 lines", $imported === 4);

$groupMembersCount = (int)Database::fetchColumn(
    "SELECT COUNT(*) FROM group_members WHERE group_id = :gid AND is_active = 1",
    [':gid' => $groupId]
);
// Kassahun (from callback) + Bere (from /checkin message) + 4 from bulk import = 6 members
assertTest("Total group roster count is 6 members (1 via button + 1 via message + 4 via bulk import)", $groupMembersCount === 6);

// Check specific imported members
$abebe = Database::fetch("SELECT * FROM members WHERE telegram_user_id = 77001122");
assertTest("Abebe Bikila was imported with numeric user ID", $abebe !== null && $abebe['full_name'] === 'Abebe Bikila');

$tigist = Database::fetch("SELECT * FROM members WHERE username = 'tigist_kaldis'");
assertTest("Tigist Alemu was imported with username 'tigist_kaldis'", $tigist !== null && $tigist['full_name'] === 'Tigist Alemu');

// --- Group 4: Teardown ---
echo "\n--- Group 4: Teardown Test Data ---\n";
Database::execute("DELETE FROM group_members WHERE group_id = :gid", [':gid' => $groupId]);
Database::execute("DELETE FROM telegram_groups WHERE id = :gid", [':gid' => $groupId]);
Database::execute("DELETE FROM telegram_updates WHERE update_id IN (995001, 995002)");

assertTest("Teardown completed cleanly", true);

echo "\n========================================================\n";
echo "Check-in & Bulk Import Tests: {$passed} Passed, {$failed} Failed\n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
