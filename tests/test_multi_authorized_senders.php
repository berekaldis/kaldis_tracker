<?php
/**
 * Test Suite: Multi-Authorized Sender Telegram User IDs & Kaldis Logo Integration
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
use App\Services\SettingService;
use App\Services\MessageTrackingService;

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
echo "Starting Multi-Authorized Sender & Kaldis Logo Test Suite\n";
echo "========================================================\n\n";

// --- Group 1: Kaldis Logo Asset Verification ---
echo "--- Group 1: Kaldis Logo Asset Verification ---\n";
$logoPath = dirname(__DIR__) . '/public/assets/img/kaldis-logo.jpg';
assertTest("Kaldis logo file exists in public/assets/img/kaldis-logo.jpg", file_exists($logoPath));
assertTest("Logo file has valid image content (> 50KB)", file_exists($logoPath) && filesize($logoPath) > 50000);

$sidebarContent = file_get_contents(dirname(__DIR__) . '/app/views/layouts/sidebar.php');
assertTest("Sidebar layout references kaldis-logo.jpg", str_contains($sidebarContent, 'assets/img/kaldis-logo.jpg'));

$loginContent = file_get_contents(dirname(__DIR__) . '/app/views/auth/login.php');
assertTest("Login view displays kaldis-logo.jpg", str_contains($loginContent, 'assets/img/kaldis-logo.jpg'));
assertTest("Login view includes favicon link", str_contains($loginContent, 'rel="icon"'));

$mainLayout = file_get_contents(dirname(__DIR__) . '/app/views/layouts/main.php');
assertTest("Main layout includes favicon pointing to kaldis-logo.jpg", str_contains($mainLayout, 'assets/img/kaldis-logo.jpg'));

$navbarContent = file_get_contents(dirname(__DIR__) . '/app/views/layouts/navbar.php');
assertTest("Navbar layout references kaldis-logo.jpg", str_contains($navbarContent, 'assets/img/kaldis-logo.jpg'));

// --- Group 2: Multi-Authorized Sender Configuration & Helper Parsing ---
echo "\n--- Group 2: Multi-Authorized Sender Configuration ---\n";

$sender1 = 814523272;   // Bereket (Primary)
$sender2 = 1626288394;  // Berhanu Seyoum (Manager 2)
$sender3 = 6492922880;  // KALDI'S Coffee (Manager 3)

SettingService::set('telegram_authorized_user_ids', "{$sender1}, {$sender2}, {$sender3}");
$authList = config('telegram.authorized_user_ids', []);

assertTest("config('telegram.authorized_user_ids') returns an array", is_array($authList));
assertTest("Authorized senders list has 3 IDs", count($authList) === 3);
assertTest("Sender 1 ({$sender1}) is recognized", in_array((string)$sender1, array_map('strval', $authList)));
assertTest("Sender 2 ({$sender2}) is recognized", in_array((string)$sender2, array_map('strval', $authList)));
assertTest("Sender 3 ({$sender3}) is recognized", in_array((string)$sender3, array_map('strval', $authList)));

// Test normalization with extra spaces, duplicates, and newlines
$rawInput = " {$sender1},   {$sender2} , {$sender3} , {$sender1}\n{$sender2} ";
$rawIds = preg_split('/[\s,]+/', $rawInput, -1, PREG_SPLIT_NO_EMPTY);
$cleanIds = array_values(array_unique(array_filter($rawIds, fn($id) => preg_match('/^-?\d+$/', trim($id)))));
assertTest("Sanitizer removes duplicates and formats cleanly", count($cleanIds) === 3 && $cleanIds === [(string)$sender1, (string)$sender2, (string)$sender3]);

// --- Group 3: Multi-Authorized Senders Conversation Tracking ---
echo "\n--- Group 3: Multi-Sender Conversation Auto-Tracking ---\n";

// Set tracking mode to all_authorized
SettingService::set('telegram_tracking_mode', 'all_authorized');
SettingService::set('telegram_track_all_messages', '1');

$testChatId = -100999888111;
$groupName = 'Kaldis Quality & Barista Team';

// Pre-clean any prior test artifacts
$existingGroup = Database::fetch("SELECT id FROM telegram_groups WHERE telegram_chat_id = :cid", [':cid' => $testChatId]);
if ($existingGroup) {
    Database::execute("DELETE FROM message_responses WHERE tracked_message_id IN (SELECT id FROM tracked_messages WHERE group_id = :gid)", [':gid' => $existingGroup['id']]);
    Database::execute("DELETE FROM tracked_messages WHERE group_id = :gid", [':gid' => $existingGroup['id']]);
    Database::execute("DELETE FROM group_members WHERE group_id = :gid", [':gid' => $existingGroup['id']]);
    Database::execute("DELETE FROM telegram_groups WHERE id = :gid", [':gid' => $existingGroup['id']]);
}
Database::execute("DELETE FROM telegram_updates WHERE update_id IN (991001, 991002, 991003, 991004, 991005)");

// Setup test group
Database::execute(
    "INSERT INTO telegram_groups (telegram_chat_id, title, type, is_active, created_at, updated_at)
     VALUES (:cid, :title, 'supergroup', 1, :now, :now)
     ON DUPLICATE KEY UPDATE title = :title, is_active = 1",
    [':cid' => $testChatId, ':title' => $groupName, ':now' => utc_now()]
);
$group = Database::fetch("SELECT * FROM telegram_groups WHERE telegram_chat_id = :cid", [':cid' => $testChatId]);
$groupId = (int)$group['id'];

// Register a regular member who will reply
$teamMemberId = 777999111;
Database::execute(
    "INSERT INTO members (telegram_user_id, full_name, username, created_at, updated_at)
     VALUES (:uid, 'Abebe Team Member', 'abebe_kaldis', :now, :now)
     ON DUPLICATE KEY UPDATE full_name = 'Abebe Team Member'",
    [':uid' => $teamMemberId, ':now' => utc_now()]
);
$memberRec = Database::fetch("SELECT * FROM members WHERE telegram_user_id = :uid", [':uid' => $teamMemberId]);
Database::execute(
    "INSERT INTO group_members (group_id, member_id, role, joined_at, is_active, created_at, updated_at)
     VALUES (:gid, :mid, 'member', :now, 1, :now, :now)
     ON DUPLICATE KEY UPDATE is_active = 1",
    [':gid' => $groupId, ':mid' => $memberRec['id'], ':now' => utc_now()]
);

$tracker = new MessageTrackingService();

// 1. Message from Sender 1 (Bereket)
$update1 = [
    'update_id' => 991001,
    'message' => [
        'message_id' => 8801,
        'from' => [
            'id' => $sender1,
            'is_bot' => false,
            'first_name' => 'Bereket',
            'username' => 'bere_sender1'
        ],
        'chat' => [
            'id' => $testChatId,
            'title' => $groupName,
            'type' => 'supergroup'
        ],
        'date' => time(),
        'text' => 'Daily briefing: Check morning roast profiles for Bole branch.'
    ]
];
$res1 = $tracker->processUpdate($update1);
assertTest("Sender 1 ({$sender1}) message is automatically tracked", ($res1['status'] ?? '') === 'tracked');

// 2. Message from Sender 2 (Berhanu Seyoum)
$update2 = [
    'update_id' => 991002,
    'message' => [
        'message_id' => 8802,
        'from' => [
            'id' => $sender2,
            'is_bot' => false,
            'first_name' => 'Berhanu Seyoum',
            'username' => 'bere_sender2'
        ],
        'chat' => [
            'id' => $testChatId,
            'title' => $groupName,
            'type' => 'supergroup'
        ],
        'date' => time(),
        'text' => 'IT Update: Point of sale network maintenance scheduled tonight at 10 PM.'
    ]
];
$res2 = $tracker->processUpdate($update2);
assertTest("Sender 2 ({$sender2}) message is automatically tracked", ($res2['status'] ?? '') === 'tracked');

// 3. Message from Sender 3 (KALDI'S Coffee)
$update3 = [
    'update_id' => 991003,
    'message' => [
        'message_id' => 8803,
        'from' => [
            'id' => $sender3,
            'is_bot' => false,
            'first_name' => "KALDI'S Coffee",
            'username' => 'kaldis_official'
        ],
        'chat' => [
            'id' => $testChatId,
            'title' => $groupName,
            'type' => 'supergroup'
        ],
        'date' => time(),
        'text' => 'Management Announcement: Customer service survey starts tomorrow.'
    ]
];
$res3 = $tracker->processUpdate($update3);
assertTest("Sender 3 ({$sender3}) message is automatically tracked", ($res3['status'] ?? '') === 'tracked');

// 4. Message from non-authorized user (Regular member)
$unauthorizedUserId = 999888777;
$updateNonAuth = [
    'update_id' => 991004,
    'message' => [
        'message_id' => 8804,
        'from' => [
            'id' => $unauthorizedUserId,
            'is_bot' => false,
            'first_name' => 'Regular Member',
            'username' => 'reg_user'
        ],
        'chat' => [
            'id' => $testChatId,
            'title' => $groupName,
            'type' => 'supergroup'
        ],
        'date' => time(),
        'text' => 'Good morning team, what is for lunch today?'
    ]
];
$resNonAuth = $tracker->processUpdate($updateNonAuth);
assertTest("Non-authorized member message is NOT tracked as conversation", ($resNonAuth['status'] ?? '') !== 'tracked');

// 5. Team member replies to Sender 2's message
$replyUpdate = [
    'update_id' => 991005,
    'message' => [
        'message_id' => 8805,
        'from' => [
            'id' => $teamMemberId,
            'is_bot' => false,
            'first_name' => 'Abebe Team Member',
            'username' => 'abebe_kaldis'
        ],
        'chat' => [
            'id' => $testChatId,
            'title' => $groupName,
            'type' => 'supergroup'
        ],
        'reply_to_message' => [
            'message_id' => 8802,
            'from' => [
                'id' => $sender2,
                'first_name' => 'Berhanu Seyoum'
            ],
            'text' => 'IT Update: Point of sale network maintenance scheduled tonight at 10 PM.'
        ],
        'date' => time() + 5,
        'text' => 'Noted Berhanu! Bole branch POS backups completed.'
    ]
];
$resReply = $tracker->processUpdate($replyUpdate);
assertTest("Reply to Sender 2 tracked message is processed", ($resReply['status'] ?? '') === 'processed');

// Verify that response was attributed to Sender 2's tracked message
$trackedMsg2 = Database::fetch(
    "SELECT id FROM tracked_messages WHERE group_id = :gid AND telegram_message_id = 8802",
    [':gid' => $groupId]
);
$responseRow = Database::fetch(
    "SELECT * FROM message_responses WHERE tracked_message_id = :tmid AND member_id = :mid",
    [':tmid' => $trackedMsg2['id'], ':mid' => $memberRec['id']]
);
assertTest("Response recorded for Sender 2's conversation", $responseRow !== null);
assertTest("Response status is 'replied'", ($responseRow['status'] ?? '') === 'replied');
assertTest("Response matching method is 'direct_reply'", ($responseRow['matching_method'] ?? '') === 'direct_reply');

// Teardown test data safely
Database::execute(
    "DELETE FROM message_responses WHERE tracked_message_id IN (
        SELECT id FROM tracked_messages WHERE group_id = :gid AND telegram_message_id IN (8801, 8802, 8803)
     )",
    [':gid' => $groupId]
);
Database::execute(
    "DELETE FROM tracked_messages WHERE group_id = :gid AND telegram_message_id IN (8801, 8802, 8803)",
    [':gid' => $groupId]
);
Database::execute(
    "DELETE FROM telegram_updates WHERE update_id IN (991001, 991002, 991003, 991004, 991005)"
);
Database::execute(
    "DELETE FROM group_members WHERE group_id = :gid",
    [':gid' => $groupId]
);
Database::execute(
    "DELETE FROM telegram_groups WHERE id = :gid",
    [':gid' => $groupId]
);

echo "\n========================================================\n";
echo "Multi-Authorized Senders Summary: {$passed} Passed, {$failed} Failed\n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
