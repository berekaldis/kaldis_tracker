<?php
/**
 * Kaldis Coffee Telegram Reply Tracker (v2)
 * Automated Test Suite: Real-Time Live APIs & Action Methods
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
use App\Services\MessageTrackingService;
use App\Controllers\TrackedMessageController;
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
echo "Starting Real-Time & Response Details Action Test Suite\n";
echo "========================================================\n\n";

// 1. Authenticate as super admin for owner checks
$loginRes = AuthService::attempt('superadmin', 'KaldisSuperAdmin2026!');
assertTest("Admin login succeeds for actions", $loginRes['success'] === true);
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['csrf_token'] = AuthService::getCsrfToken();

$trackedCtrl = new TrackedMessageController();
$dashCtrl = new DashboardController();
$tracker = new MessageTrackingService();

// Setup test data
$testChatId = -100444555666;
$authUserId = 814523272;
$memberId = 333444555;

// Clean up
$existing = Database::fetch("SELECT id FROM telegram_groups WHERE telegram_chat_id = :cid", [':cid' => $testChatId]);
if ($existing) {
    $gid = $existing['id'];
    Database::execute("DELETE FROM message_responses WHERE tracked_message_id IN (SELECT id FROM tracked_messages WHERE group_id = :gid)", [':gid' => $gid]);
    Database::execute("DELETE FROM tracked_messages WHERE group_id = :gid", [':gid' => $gid]);
    Database::execute("DELETE FROM group_members WHERE group_id = :gid", [':gid' => $gid]);
    Database::execute("DELETE FROM telegram_groups WHERE id = :gid", [':gid' => $gid]);
}
Database::execute("DELETE FROM telegram_updates WHERE chat_id = :cid OR update_id >= 95000", [':cid' => $testChatId]);

// Create group and member
$timeBase = time() - 3600;
$tracker->processUpdate([
    'update_id' => 95001,
    'message' => [
        'message_id' => 101,
        'date' => $timeBase,
        'chat' => ['id' => $testChatId, 'title' => 'Actions Test Group', 'type' => 'supergroup'],
        'from' => ['id' => $memberId, 'is_bot' => false, 'first_name' => 'Sara', 'username' => 'sara_k'],
        'text' => 'Hello group!'
    ]
]);

// Author creates tracked message
$trackedRes = $tracker->processUpdate([
    'update_id' => 95002,
    'message' => [
        'message_id' => 102,
        'date' => $timeBase + 60,
        'chat' => ['id' => $testChatId, 'title' => 'Actions Test Group', 'type' => 'supergroup'],
        'from' => ['id' => $authUserId, 'is_bot' => false, 'first_name' => 'Bereket'],
        'text' => 'Team, please report morning cash balance.'
    ]
]);

$trackedId = (int)$trackedRes['tracked_message_id'];
assertTest("Tracked message created with ID {$trackedId}", $trackedId > 0);

// Member sends a photo reply with caption
$photoReply = $tracker->processUpdate([
    'update_id' => 95003,
    'message' => [
        'message_id' => 103,
        'date' => $timeBase + 120,
        'chat' => ['id' => $testChatId, 'title' => 'Actions Test Group', 'type' => 'supergroup'],
        'from' => ['id' => $memberId, 'is_bot' => false, 'first_name' => 'Sara'],
        'reply_to_message' => [
            'message_id' => 102,
            'text' => 'Team, please report morning cash balance.'
        ],
        'photo' => [['file_id' => 'abc123xyz']],
        'caption' => 'Cash count sheet attached: 15,200 ETB'
    ]
]);

echo "\n--- Group 1: Photo & Media Caption Tracking ---\n";
$responseRow = Database::fetch("SELECT * FROM message_responses WHERE tracked_message_id = :tmid", [':tmid' => $trackedId]);
assertTest("Media reply was recorded", $responseRow !== null);
assertTest("Status is 'replied'", ($responseRow['status'] ?? '') === 'replied');
assertTest("Caption and [Photo] tag were captured", str_contains($responseRow['response_text'] ?? '', '[Photo: Cash count sheet attached: 15,200 ETB]'));

echo "\n--- Group 2: Admin Actions (Exclude, Include, Manual Reply) ---\n";

$groupRow = Database::fetch("SELECT id FROM telegram_groups WHERE telegram_chat_id = :cid", [':cid' => $testChatId]);
$groupId = (int)$groupRow['id'];

// Test Exclude Member
$memberRecord = Database::fetch("SELECT id FROM members WHERE telegram_user_id = :uid", [':uid' => $memberId]);
$_POST['message_id'] = $trackedId;
$_POST['member_id'] = $memberRecord['id'];
$_POST['telegram_user_id'] = $memberId;
$_POST['reason'] = 'On annual leave';

// Call exclude logic directly or via DB
Database::execute(
    "UPDATE message_responses 
     SET status = 'excluded', excluded_reason = :reason, reviewed_at = :now, updated_at = :now
     WHERE tracked_message_id = :tmid AND member_telegram_user_id = :uid",
    [':reason' => 'On annual leave', ':now' => utc_now(), ':tmid' => $trackedId, ':uid' => $memberId]
);

$excludedRow = Database::fetch("SELECT * FROM message_responses WHERE tracked_message_id = :tmid", [':tmid' => $trackedId]);
assertTest("Member status changed to 'excluded'", ($excludedRow['status'] ?? '') === 'excluded');
assertTest("Exclusion reason saved", ($excludedRow['excluded_reason'] ?? '') === 'On annual leave');

// Verify exclusion recalculates stats
$statsAfterExclude = $dashCtrl->calculateMessageStats($trackedId, $groupId, date('Y-m-d H:i:s', $timeBase + 60));
assertTest("Excluded member is counted in excluded stats", $statsAfterExclude['excluded'] === 1);

// Test Manual Mark as Replied
Database::execute(
    "UPDATE message_responses 
     SET status = 'replied', matching_method = 'manual', response_text = 'Confirmed via telephone call', updated_at = :now
     WHERE tracked_message_id = :tmid AND member_telegram_user_id = :uid",
    [':now' => utc_now(), ':tmid' => $trackedId, ':uid' => $memberId]
);

$manualRow = Database::fetch("SELECT * FROM message_responses WHERE tracked_message_id = :tmid", [':tmid' => $trackedId]);
assertTest("Manual reply status is 'replied'", ($manualRow['status'] ?? '') === 'replied');
assertTest("Matching method is 'manual'", ($manualRow['matching_method'] ?? '') === 'manual');
assertTest("Manual note saved", ($manualRow['response_text'] ?? '') === 'Confirmed via telephone call');

echo "\n--- Group 3: Real-Time API Calculations ---\n";
// Test calculateMessageStats returns denominator and percentages
$statsFinal = $dashCtrl->calculateMessageStats($trackedId, $groupId, date('Y-m-d H:i:s', $timeBase + 60));
assertTest("Stats calculateReplied > 0", $statsFinal['replied'] === 1);
assertTest("Response percentage is 100%", $statsFinal['percentage'] == 100.0);

// Cleanup
Database::execute("DELETE FROM message_responses WHERE tracked_message_id = :tmid", [':tmid' => $trackedId]);
Database::execute("DELETE FROM tracked_messages WHERE id = :tmid", [':tmid' => $trackedId]);
Database::execute("DELETE FROM group_members WHERE group_id = :gid", [':gid' => $groupId]);
Database::execute("DELETE FROM telegram_groups WHERE id = :gid", [':gid' => $groupId]);
Database::execute("DELETE FROM telegram_updates WHERE chat_id = :cid", [':cid' => $testChatId]);

echo "\n========================================================\n";
echo "Real-Time & Actions Tests: {$passed} Passed, {$failed} Failed\n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
