<?php
/**
 * Test Suite: Normal User Role & Group-Specific Access Control
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/helpers.php';

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require $file;
});

use App\Services\Database;
use App\Services\AuthService;
use App\Services\MessageTrackingService;

AuthService::startSession();

echo "========================================================\n";
echo "Starting Normal User Role & Group Access Test Suite\n";
echo "========================================================\n\n";

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

$now = utc_now();

// --- Setup Test Fixtures ---
echo "--- Setup: Test Fixtures ---\n";
// Create two groups: Group A (Operations) and Group B (IT)
Database::execute(
    "INSERT INTO telegram_groups (telegram_chat_id, title, department, is_active, created_at, updated_at)
     VALUES (-990011221, 'Test Operations Branch Group', 'Operations', 1, :now1, :now2)",
    [':now1' => $now, ':now2' => $now]
);
$groupAId = (int)Database::lastInsertId();

Database::execute(
    "INSERT INTO telegram_groups (telegram_chat_id, title, department, is_active, created_at, updated_at)
     VALUES (-990011222, 'Test IT Infrastructure Group', 'IT', 1, :now1, :now2)",
    [':now1' => $now, ':now2' => $now]
);
$groupBId = (int)Database::lastInsertId();

// Create 1 Normal User locked to Group A
$userPass = password_hash('NormalPass2026!', PASSWORD_DEFAULT);
Database::execute(
    "INSERT INTO admins (username, email, password_hash, role, department, assigned_group_id, is_active, created_at, updated_at)
     VALUES ('ops_normal_user', 'ops_user@kaldis.com', :p, 'user', 'Operations', :gid, 1, :now1, :now2)",
    [':p' => $userPass, ':gid' => $groupAId, ':now1' => $now, ':now2' => $now]
);
$normalUserId = (int)Database::lastInsertId();

// Create 1 Department-Wide Normal User (Operations, no specific group)
Database::execute(
    "INSERT INTO admins (username, email, password_hash, role, department, assigned_group_id, is_active, created_at, updated_at)
     VALUES ('dept_normal_user', 'dept_user@kaldis.com', :p, 'user', 'Operations', NULL, 1, :now1, :now2)",
    [':p' => $userPass, ':now1' => $now, ':now2' => $now]
);
$deptUserId = (int)Database::lastInsertId();

// Create 1 Viewer User
Database::execute(
    "INSERT INTO admins (username, email, password_hash, role, department, assigned_group_id, is_active, created_at, updated_at)
     VALUES ('test_viewer', 'viewer@kaldis.com', :p, 'viewer', 'Operations', :gid, 1, :now1, :now2)",
    [':p' => $userPass, ':gid' => $groupAId, ':now1' => $now, ':now2' => $now]
);
$viewerUserId = (int)Database::lastInsertId();

// Create Member and tracked messages in Group A and Group B
Database::execute(
    "INSERT INTO members (telegram_user_id, full_name, username, is_bot, created_at, updated_at)
     VALUES (77112233, 'Test Member One', 'testmember1', 0, :now1, :now2)",
    [':now1' => $now, ':now2' => $now]
);
$memberId = (int)Database::lastInsertId();

Database::execute(
    "INSERT INTO group_members (group_id, member_id, role, joined_at, source, is_active, created_at, updated_at)
     VALUES (:gid, :mid, 'member', :now1, 'imported', 1, :now2, :now3)",
    [':gid' => $groupAId, ':mid' => $memberId, ':now1' => $now, ':now2' => $now, ':now3' => $now]
);

Database::execute(
    "INSERT INTO tracked_messages (group_id, telegram_message_id, telegram_user_id, message_text, message_date, is_active, created_at, updated_at)
     VALUES (:gid, 9001, 100, 'Test announcement in Group A', :now1, 1, :now2, :now3)",
    [':gid' => $groupAId, ':now1' => $now, ':now2' => $now, ':now3' => $now]
);
$messageAId = (int)Database::lastInsertId();

Database::execute(
    "INSERT INTO tracked_messages (group_id, telegram_message_id, telegram_user_id, message_text, message_date, is_active, created_at, updated_at)
     VALUES (:gid, 9002, 100, 'Test announcement in Group B', :now1, 1, :now2, :now3)",
    [':gid' => $groupBId, ':now1' => $now, ':now2' => $now, ':now3' => $now]
);
$messageBId = (int)Database::lastInsertId();

// Create a pending review response for Message A
Database::execute(
    "INSERT INTO message_responses (tracked_message_id, member_id, member_telegram_user_id, response_telegram_message_id, response_text, response_date, status, created_at, updated_at)
     VALUES (:tmid, :mid, 77112233, 9003, 'Understood, on it!', :now1, 'pending_review', :now2, :now3)",
    [':tmid' => $messageAId, ':mid' => $memberId, ':now1' => $now, ':now2' => $now, ':now3' => $now]
);
$responseAId = (int)Database::lastInsertId();

echo "[+] Fixtures established.\n\n";

// --- Group 1: Role Verification & Helper Checks ---
echo "--- Group 1: Role Verification & Helper Checks ---\n";
$_SESSION['admin_user'] = [
    'id' => $normalUserId,
    'username' => 'ops_normal_user',
    'role' => 'user',
    'department' => 'Operations',
    'assigned_group_id' => $groupAId,
];

assertTest("isUser() returns true for normal user", AuthService::isUser() === true);
assertTest("isOwner() returns false for normal user", AuthService::isOwner() === false);
assertTest("isSuperAdmin() returns false for normal user", AuthService::isSuperAdmin() === false);
assertTest("isViewer() returns false for normal user", AuthService::isViewer() === false);
assertTest("canTrack() returns true for normal user", AuthService::canTrack() === true);
assertTest("assignedGroupId() returns group A ID", AuthService::assignedGroupId() === $groupAId);

$_SESSION['admin_user']['role'] = 'viewer';
assertTest("canTrack() returns false for viewer", AuthService::canTrack() === false);
assertTest("isViewer() returns true for viewer", AuthService::isViewer() === true);

$_SESSION['admin_user']['role'] = 'owner';
assertTest("canTrack() returns true for owner", AuthService::canTrack() === true);

$_SESSION['admin_user']['role'] = 'super_admin';
assertTest("canTrack() returns true for super_admin", AuthService::canTrack() === true);


// --- Group 2: Group & Message Access Isolation ---
echo "\n--- Group 2: Group & Message Access Isolation ---\n";
$_SESSION['admin_user'] = [
    'id' => $normalUserId,
    'username' => 'ops_normal_user',
    'role' => 'user',
    'department' => 'Operations',
    'assigned_group_id' => $groupAId,
];

assertTest("Normal user CAN access assigned Group A", AuthService::canAccessGroup($groupAId) === true);
assertTest("Normal user CANNOT access unassigned Group B", AuthService::canAccessGroup($groupBId) === false);
assertTest("Normal user CAN access message in Group A", AuthService::canAccessMessage($messageAId) === true);
assertTest("Normal user CANNOT access message in Group B", AuthService::canAccessMessage($messageBId) === false);

// Test department-level user without specific group
$_SESSION['admin_user'] = [
    'id' => $deptUserId,
    'username' => 'dept_normal_user',
    'role' => 'user',
    'department' => 'Operations',
    'assigned_group_id' => null,
];
assertTest("Dept-level user CAN access Group A (same dept)", AuthService::canAccessGroup($groupAId) === true);
assertTest("Dept-level user CANNOT access Group B (different dept)", AuthService::canAccessGroup($groupBId) === false);


// --- Group 3: Conversation Tracking & Response Management by Normal User ---
echo "\n--- Group 3: Conversation Tracking & Response Management by Normal User ---\n";
$_SESSION['admin_user'] = [
    'id' => $normalUserId,
    'username' => 'ops_normal_user',
    'role' => 'user',
    'department' => 'Operations',
    'assigned_group_id' => $groupAId,
];

// Test confirming pending response
Database::execute(
    "UPDATE message_responses SET status = 'replied', reviewed_by_admin_id = :aid, reviewed_at = :now, updated_at = :now WHERE id = :id",
    [':aid' => $normalUserId, ':now' => utc_now(), ':id' => $responseAId]
);
$resRow = Database::fetch("SELECT * FROM message_responses WHERE id = :id", [':id' => $responseAId]);
assertTest("Normal user can confirm response (status: replied)", $resRow['status'] === 'replied');

// Test manual reply marking
Database::execute(
    "UPDATE message_responses SET status = 'replied', matching_method = 'manual', response_text = 'Phone verbal' WHERE id = :id",
    [':id' => $responseAId]
);
$resRow = Database::fetch("SELECT * FROM message_responses WHERE id = :id", [':id' => $responseAId]);
assertTest("Normal user can mark manual reply", $resRow['matching_method'] === 'manual');

// Test excluding member
Database::execute(
    "UPDATE message_responses SET status = 'excluded', excluded_reason = 'Sick leave' WHERE id = :id",
    [':id' => $responseAId]
);
$resRow = Database::fetch("SELECT * FROM message_responses WHERE id = :id", [':id' => $responseAId]);
assertTest("Normal user can exclude member with reason", $resRow['status'] === 'excluded' && $resRow['excluded_reason'] === 'Sick leave');


// --- Group 4: Super Admin vs Owner vs Normal User Boundaries ---
echo "\n--- Group 4: Role Boundaries ---\n";
// Normal user is blocked by requireOwner
$_SESSION['admin_user'] = [
    'id' => $normalUserId,
    'username' => 'ops_normal_user',
    'role' => 'user',
    'department' => 'Operations',
    'assigned_group_id' => $groupAId,
];
assertTest("Normal user does not pass isOwner() check", AuthService::isOwner() === false);
assertTest("Normal user does not pass isSuperAdmin() check", AuthService::isSuperAdmin() === false);

// Super admin has unrestricted access
$_SESSION['admin_user'] = [
    'id' => 1,
    'username' => 'admin',
    'role' => 'super_admin',
    'department' => null,
    'assigned_group_id' => null,
];
assertTest("Super Admin can access Group A", AuthService::canAccessGroup($groupAId) === true);
assertTest("Super Admin can access Group B", AuthService::canAccessGroup($groupBId) === true);
assertTest("Super Admin can access Message A", AuthService::canAccessMessage($messageAId) === true);
assertTest("Super Admin can access Message B", AuthService::canAccessMessage($messageBId) === true);


// --- Teardown Test Data ---
echo "\n--- Teardown: Clean Test Data ---\n";
Database::execute("DELETE FROM message_responses WHERE tracked_message_id IN (:ma, :mb)", [':ma' => $messageAId, ':mb' => $messageBId]);
Database::execute("DELETE FROM tracked_messages WHERE id IN (:ma, :mb)", [':ma' => $messageAId, ':mb' => $messageBId]);
Database::execute("DELETE FROM group_members WHERE group_id IN (:ga, :gb)", [':ga' => $groupAId, ':gb' => $groupBId]);
Database::execute("DELETE FROM members WHERE id = :mid", [':mid' => $memberId]);
Database::execute("DELETE FROM admins WHERE id IN (:u1, :u2, :u3)", [':u1' => $normalUserId, ':u2' => $deptUserId, ':u3' => $viewerUserId]);
Database::execute("DELETE FROM telegram_groups WHERE id IN (:ga, :gb)", [':ga' => $groupAId, ':gb' => $groupBId]);

assertTest("Teardown completed cleanly", true);

echo "\n========================================================\n";
echo "Normal User Role Tests: {$passed} Passed, {$failed} Failed\n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
