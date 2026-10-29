<?php
/**
 * Test Suite: Group Member Synchronization, Discovery & Manual Addition
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
echo "Starting Group Member Sync & Discovery Test Suite\n";
echo "========================================================\n\n";

$now = utc_now();
$testChatId = -1007770001;

// Cleanup prior test artifacts
$existing = Database::fetch("SELECT id FROM telegram_groups WHERE telegram_chat_id = :cid", [':cid' => $testChatId]);
if ($existing) {
    Database::execute("DELETE FROM group_members WHERE group_id = :gid", [':gid' => $existing['id']]);
    Database::execute("DELETE FROM telegram_groups WHERE id = :gid", [':gid' => $existing['id']]);
}

Database::execute(
    "INSERT INTO telegram_groups (telegram_chat_id, title, type, department, is_active, created_at, updated_at)
     VALUES (:cid, 'Kaldis Roastery Shift A', 'supergroup', 'Supply Chain', 1, :now1, :now2)",
    [':cid' => $testChatId, ':now1' => $now, ':now2' => $now]
);
$group = Database::fetch("SELECT * FROM telegram_groups WHERE telegram_chat_id = :cid", [':cid' => $testChatId]);
$groupId = (int)$group['id'];

// --- Group 1: Automatic Member Discovery via Message Activity ---
echo "--- Group 1: Member Discovery via Message Activity ---\n";
$tracker = new MessageTrackingService();

// Simulate a waiter/barista sending their first message in the group
$waiterUserId = 555666777;
$update = [
    'update_id' => 881001,
    'message' => [
        'message_id' => 101,
        'from' => [
            'id' => $waiterUserId,
            'is_bot' => false,
            'first_name' => 'Tadesse',
            'last_name' => 'Waiter',
            'username' => 'tadesse_kaldis'
        ],
        'chat' => [
            'id' => $testChatId,
            'title' => 'Kaldis Roastery Shift A',
            'type' => 'supergroup'
        ],
        'date' => time(),
        'text' => 'Good morning everyone! Coffee beans stocked for today.'
    ]
];
$tracker->processUpdate($update);

$waiterMember = Database::fetch("SELECT * FROM members WHERE telegram_user_id = :uid", [':uid' => $waiterUserId]);
assertTest("Member record was automatically created for sender", $waiterMember !== null);
assertTest("Member name is captured correctly", ($waiterMember['full_name'] ?? '') === 'Tadesse Waiter');

$gm = Database::fetch("SELECT * FROM group_members WHERE group_id = :gid AND member_id = :mid", [
    ':gid' => $groupId,
    ':mid' => $waiterMember['id']
]);
assertTest("Member was added to group_members junction table", $gm !== null);
assertTest("Membership status is active", ($gm['is_active'] ?? 0) == 1);

// --- Group 2: Member Join Service Event (new_chat_members) ---
echo "\n--- Group 2: Member Join Service Event (new_chat_members) ---\n";
$newBaristaId = 555666888;
$joinUpdate = [
    'update_id' => 881002,
    'message' => [
        'message_id' => 102,
        'from' => [
            'id' => $waiterUserId,
            'first_name' => 'Tadesse'
        ],
        'chat' => [
            'id' => $testChatId,
            'title' => 'Kaldis Roastery Shift A',
            'type' => 'supergroup'
        ],
        'date' => time(),
        'new_chat_members' => [
            [
                'id' => $newBaristaId,
                'is_bot' => false,
                'first_name' => 'Selamawit',
                'last_name' => 'Barista',
                'username' => 'selam_barista'
            ]
        ]
    ]
];
$tracker->processUpdate($joinUpdate);

$baristaMember = Database::fetch("SELECT * FROM members WHERE telegram_user_id = :uid", [':uid' => $newBaristaId]);
assertTest("new_chat_members event registered new member in members table", $baristaMember !== null);
assertTest("Barista name is Selamawit Barista", ($baristaMember['full_name'] ?? '') === 'Selamawit Barista');

$gmBarista = Database::fetch("SELECT * FROM group_members WHERE group_id = :gid AND member_id = :mid", [
    ':gid' => $groupId,
    ':mid' => $baristaMember['id']
]);
assertTest("New barista added to group_members table", $gmBarista !== null);

// --- Group 3: Member Leave Service Event (left_chat_member) ---
echo "\n--- Group 3: Member Leave Event (left_chat_member) ---\n";
$leaveUpdate = [
    'update_id' => 881003,
    'message' => [
        'message_id' => 103,
        'from' => [
            'id' => $newBaristaId,
            'first_name' => 'Selamawit'
        ],
        'chat' => [
            'id' => $testChatId,
            'title' => 'Kaldis Roastery Shift A',
            'type' => 'supergroup'
        ],
        'date' => time(),
        'left_chat_member' => [
            'id' => $newBaristaId,
            'is_bot' => false,
            'first_name' => 'Selamawit'
        ]
    ]
];
$tracker->processUpdate($leaveUpdate);

$gmLeft = Database::fetch("SELECT * FROM group_members WHERE group_id = :gid AND member_id = :mid", [
    ':gid' => $groupId,
    ':mid' => $baristaMember['id']
]);
assertTest("Left member has is_active = 0", ($gmLeft['is_active'] ?? 1) == 0);
assertTest("Left member has left_at timestamp", !empty($gmLeft['left_at']));

// --- Group 4: Teardown ---
echo "\n--- Group 4: Teardown ---\n";
Database::execute("DELETE FROM group_members WHERE group_id = :gid", [':gid' => $groupId]);
Database::execute("DELETE FROM telegram_groups WHERE id = :gid", [':gid' => $groupId]);
Database::execute("DELETE FROM telegram_updates WHERE update_id IN (881001, 881002, 881003)");

assertTest("Cleanup successful", true);

echo "\n========================================================\n";
echo "Member Sync Tests: {$passed} Passed, {$failed} Failed\n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
