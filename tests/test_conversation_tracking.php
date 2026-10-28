<?php
/**
 * Kaldis Coffee Telegram Reply Tracker (v2)
 * Automated Test Suite: Conversation Tracking ("Track Each Conversation")
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
use App\Services\SettingService;
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
echo "Starting Conversation Tracking Test Suite\n";
echo "========================================================\n\n";

$tracker = new MessageTrackingService();
$dashboardCtrl = new DashboardController();

// Use test chat ID and test users
$testChatId = -100888999111;
$authUserId = 814523272; // Configured authorized user
$member1Id = 777111001;
$member2Id = 777222002;

// Ensure clean slate for test chat
$existing = Database::fetch("SELECT id FROM telegram_groups WHERE telegram_chat_id = :cid", [':cid' => $testChatId]);
if ($existing) {
    $gid = $existing['id'];
    Database::execute("DELETE FROM message_responses WHERE tracked_message_id IN (SELECT id FROM tracked_messages WHERE group_id = :gid)", [':gid' => $gid]);
    Database::execute("DELETE FROM tracked_messages WHERE group_id = :gid", [':gid' => $gid]);
    Database::execute("DELETE FROM group_members WHERE group_id = :gid", [':gid' => $gid]);
    Database::execute("DELETE FROM telegram_groups WHERE id = :gid", [':gid' => $gid]);
}
Database::execute("DELETE FROM telegram_updates WHERE chat_id = :cid", [':cid' => $testChatId]);

// Ensure tracking mode is set to 'all_authorized'
SettingService::set('telegram_tracking_mode', 'all_authorized');
SettingService::set('telegram_track_all_messages', '1');
SettingService::set('telegram_authorized_user_ids', (string)$authUserId);

echo "--- Group 1: Configuration & Defaults ---\n";
assertTest("Tracking mode is 'all_authorized'", config('telegram.tracking_mode') === 'all_authorized');
assertTest("track_all_messages returns true", config('telegram.track_all_messages') === true);
assertTest("Authorized user is recognized", in_array((string)$authUserId, array_map('strval', (array)config('telegram.authorized_user_ids'))));

echo "\n--- Group 2: Automatic Conversation Tracking (No #track required) ---\n";

// Step 1: Pre-populate 2 active group members
$timeBase = time() - 3600;
$tracker->processUpdate([
    'update_id' => 90001,
    'message' => [
        'message_id' => 501,
        'date' => $timeBase,
        'chat' => ['id' => $testChatId, 'title' => 'Kaldis Coffee Operations', 'type' => 'supergroup'],
        'from' => ['id' => $member1Id, 'is_bot' => false, 'first_name' => 'Tigist', 'username' => 'tigist_kaldis'],
        'text' => 'Good morning everyone!'
    ]
]);

$tracker->processUpdate([
    'update_id' => 90002,
    'message' => [
        'message_id' => 502,
        'date' => $timeBase + 60,
        'chat' => ['id' => $testChatId, 'title' => 'Kaldis Coffee Operations', 'type' => 'supergroup'],
        'from' => ['id' => $member2Id, 'is_bot' => false, 'first_name' => 'Dawit', 'username' => 'dawit_kaldis'],
        'text' => 'Good morning!'
    ]
]);

// Step 2: Authorized user posts a normal question WITHOUT #track
$convMessageId = 600;
$resConv = $tracker->processUpdate([
    'update_id' => 90003,
    'message' => [
        'message_id' => $convMessageId,
        'date' => $timeBase + 300,
        'chat' => ['id' => $testChatId, 'title' => 'Kaldis Coffee Operations', 'type' => 'supergroup'],
        'from' => ['id' => $authUserId, 'is_bot' => false, 'first_name' => 'Bereket', 'username' => 'bereket'],
        'text' => 'Team, please report whether espresso machine calibration is complete for today.'
    ]
]);

assertTest("Message from authorized user WITHOUT #track is auto-tracked", ($resConv['status'] ?? '') === 'tracked');
$trackedMsg1 = Database::fetch(
    "SELECT * FROM tracked_messages WHERE telegram_message_id = :mid",
    [':mid' => $convMessageId]
);
assertTest("Tracked message record was created in database", $trackedMsg1 !== null);
assertTest("tracking_source is 'auto'", ($trackedMsg1['tracking_source'] ?? '') === 'auto');

// Step 3: Member 1 replies to the auto-tracked conversation
$replyUpdate = $tracker->processUpdate([
    'update_id' => 90004,
    'message' => [
        'message_id' => 601,
        'date' => $timeBase + 400,
        'chat' => ['id' => $testChatId, 'title' => 'Kaldis Coffee Operations', 'type' => 'supergroup'],
        'from' => ['id' => $member1Id, 'is_bot' => false, 'first_name' => 'Tigist'],
        'reply_to_message' => [
            'message_id' => $convMessageId,
            'text' => 'Team, please report whether espresso machine calibration is complete for today.'
        ],
        'text' => 'Bole Medhanealem branch is calibrated and ready!'
    ]
]);

$responseRow1 = Database::fetch(
    "SELECT * FROM message_responses WHERE tracked_message_id = :tmid AND member_telegram_user_id = :uid",
    [':tmid' => $trackedMsg1['id'], ':uid' => $member1Id]
);
assertTest("Member 1 response is recorded for auto-tracked conversation", $responseRow1 !== null);
assertTest("Response status is 'replied'", ($responseRow1['status'] ?? '') === 'replied');
assertTest("Matching method is 'direct_reply'", ($responseRow1['matching_method'] ?? '') === 'direct_reply');

// Step 4: Verify Dashboard stats reflect 1 replied, 1 not replied (50%)
$stats1 = $dashboardCtrl->calculateMessageStats((int)$trackedMsg1['id'], (int)$trackedMsg1['group_id'], $trackedMsg1['message_date']);
assertTest("Eligible roster members count is 2 (excluding sender)", $stats1['eligible'] >= 2);
assertTest("Replied count is 1", $stats1['replied'] === 1);
assertTest("Not replied count is 1", $stats1['not_replied'] === 1);
assertTest("Response percentage is 50%", $stats1['percentage'] == 50.0);

echo "\n--- Group 3: Conversation Continuity & Thread Follow-ups ---\n";

// Step 5: Author replies to Member 1 inside the thread: "Thanks Tigist!"
// This should NOT create a duplicate tracked message
$authorFollowUp = $tracker->processUpdate([
    'update_id' => 90005,
    'message' => [
        'message_id' => 602,
        'date' => $timeBase + 500,
        'chat' => ['id' => $testChatId, 'title' => 'Kaldis Coffee Operations', 'type' => 'supergroup'],
        'from' => ['id' => $authUserId, 'is_bot' => false, 'first_name' => 'Bereket'],
        'reply_to_message' => [
            'message_id' => 601,
            'text' => 'Bole Medhanealem branch is calibrated and ready!'
        ],
        'text' => 'Thank you Tigist, noted.'
    ]
]);

$dupTracked = Database::fetch("SELECT * FROM tracked_messages WHERE telegram_message_id = 602");
assertTest("Author thread follow-up does NOT create duplicate tracked conversation", $dupTracked === null);

$authorResp = Database::fetch(
    "SELECT * FROM message_responses WHERE tracked_message_id = :tmid AND member_telegram_user_id = :uid",
    [':tmid' => $trackedMsg1['id'], ':uid' => $authUserId]
);
assertTest("Author is NOT recorded as a pending response to their own conversation", $authorResp === null);

echo "\n--- Group 4: Reference Code Auto-Extraction ---\n";

// Step 6: Authorized user starts a new conversation with a task reference code (e.g. #ops-202)
$convMessageId2 = 700;
$tracker->processUpdate([
    'update_id' => 90006,
    'message' => [
        'message_id' => $convMessageId2,
        'date' => $timeBase + 600,
        'chat' => ['id' => $testChatId, 'title' => 'Kaldis Coffee Operations', 'type' => 'supergroup'],
        'from' => ['id' => $authUserId, 'is_bot' => false, 'first_name' => 'Bereket'],
        'text' => 'Audit check #ops-202: Please confirm fire extinguisher inspection.'
    ]
]);

$trackedMsg2 = Database::fetch("SELECT * FROM tracked_messages WHERE telegram_message_id = :mid", [':mid' => $convMessageId2]);
assertTest("Second conversation is auto-tracked", $trackedMsg2 !== null);
assertTest("Reference code #ops-202 is extracted without #track", ($trackedMsg2['reference_code'] ?? '') === '#ops-202');

// Member 2 replies with reference code (without Telegram reply button)
$tracker->processUpdate([
    'update_id' => 90007,
    'message' => [
        'message_id' => 701,
        'date' => $timeBase + 700,
        'chat' => ['id' => $testChatId, 'title' => 'Kaldis Coffee Operations', 'type' => 'supergroup'],
        'from' => ['id' => $member2Id, 'is_bot' => false, 'first_name' => 'Dawit'],
        'text' => 'All extinguishers checked and certified #ops-202'
    ]
]);

$responseRow2 = Database::fetch(
    "SELECT * FROM message_responses WHERE tracked_message_id = :tmid AND member_telegram_user_id = :uid",
    [':tmid' => $trackedMsg2['id'], ':uid' => $member2Id]
);
assertTest("Member 2 response matched via reference code #ops-202", $responseRow2 !== null && $responseRow2['status'] === 'replied');

echo "\n--- Group 5: Trigger Only Mode Verification ---\n";

// Switch to 'trigger_only' mode
SettingService::set('telegram_tracking_mode', 'trigger_only');
SettingService::set('telegram_track_all_messages', '0');

// Message without #track should NOT be tracked in trigger_only mode
$resUntracked = $tracker->processUpdate([
    'update_id' => 90008,
    'message' => [
        'message_id' => 800,
        'date' => $timeBase + 800,
        'chat' => ['id' => $testChatId, 'title' => 'Kaldis Coffee Operations', 'type' => 'supergroup'],
        'from' => ['id' => $authUserId, 'is_bot' => false, 'first_name' => 'Bereket'],
        'text' => 'Casual announcement: Staff meeting tomorrow at 4 PM.'
    ]
]);
assertTest("Message without #track is NOT tracked in trigger_only mode", ($resUntracked['status'] ?? '') !== 'tracked');

// Message WITH #track should be tracked in trigger_only mode
$resTrackedHashtag = $tracker->processUpdate([
    'update_id' => 90009,
    'message' => [
        'message_id' => 801,
        'date' => $timeBase + 900,
        'chat' => ['id' => $testChatId, 'title' => 'Kaldis Coffee Operations', 'type' => 'supergroup'],
        'from' => ['id' => $authUserId, 'is_bot' => false, 'first_name' => 'Bereket'],
        'text' => 'Confirm shift coverage for weekend #track'
    ]
]);
assertTest("Message WITH #track IS tracked in trigger_only mode", ($resTrackedHashtag['status'] ?? '') === 'tracked');

// Restore default mode: all_authorized
SettingService::set('telegram_tracking_mode', 'all_authorized');
SettingService::set('telegram_track_all_messages', '1');

// Clean up test group data
if ($trackedMsg1) {
    Database::execute("DELETE FROM message_responses WHERE tracked_message_id IN (:t1, :t2)", [':t1' => $trackedMsg1['id'], ':t2' => $trackedMsg2['id']]);
    Database::execute("DELETE FROM tracked_messages WHERE group_id = :gid", [':gid' => $trackedMsg1['group_id']]);
    Database::execute("DELETE FROM group_members WHERE group_id = :gid", [':gid' => $trackedMsg1['group_id']]);
    Database::execute("DELETE FROM telegram_groups WHERE id = :gid", [':gid' => $trackedMsg1['group_id']]);
}
Database::execute("DELETE FROM telegram_updates WHERE chat_id = :cid", [':cid' => $testChatId]);

echo "\n========================================================\n";
echo "Conversation Tracking Tests: {$passed} Passed, {$failed} Failed\n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
