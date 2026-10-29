<?php
/**
 * Test Suite: Multi-Department Group Isolation & User Access Control
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
echo "Starting Multi-Department Isolation & Access Test Suite\n";
echo "========================================================\n\n";

// --- Group 1: Database Schema & Migration Verification ---
echo "--- Group 1: Database Schema & Migration Verification ---\n";
$tgCols = Database::fetchAll("SHOW COLUMNS FROM telegram_groups LIKE 'department'");
assertTest("telegram_groups table has 'department' column", !empty($tgCols));

$admCols = Database::fetchAll("SHOW COLUMNS FROM admins LIKE 'department'");
assertTest("admins table has 'department' column", !empty($admCols));

// Setup test departments and groups
$now = utc_now();
$itChatId = -100888001;
$opsChatId = -100888002;
$financeChatId = -100888003;

// Clean prior test artifacts
$testChatIds = [$itChatId, $opsChatId, $financeChatId];
foreach ($testChatIds as $cid) {
    $g = Database::fetch("SELECT id FROM telegram_groups WHERE telegram_chat_id = :cid", [':cid' => $cid]);
    if ($g) {
        Database::execute("DELETE FROM message_responses WHERE tracked_message_id IN (SELECT id FROM tracked_messages WHERE group_id = :gid)", [':gid' => $g['id']]);
        Database::execute("DELETE FROM tracked_messages WHERE group_id = :gid", [':gid' => $g['id']]);
        Database::execute("DELETE FROM group_members WHERE group_id = :gid", [':gid' => $g['id']]);
        Database::execute("DELETE FROM telegram_groups WHERE id = :gid", [':gid' => $g['id']]);
    }
}
Database::execute("DELETE FROM admins WHERE username IN ('it_manager_test', 'ops_manager_test')");

// Create test groups in different departments
Database::execute(
    "INSERT INTO telegram_groups (telegram_chat_id, title, type, department, is_active, created_at, updated_at)
     VALUES (:cid, 'Kaldis IT Infrastructure', 'supergroup', 'IT', 1, :now1, :now2)",
    [':cid' => $itChatId, ':now1' => $now, ':now2' => $now]
);
$itGroup = Database::fetch("SELECT * FROM telegram_groups WHERE telegram_chat_id = :cid", [':cid' => $itChatId]);
$itGroupId = (int)$itGroup['id'];

Database::execute(
    "INSERT INTO telegram_groups (telegram_chat_id, title, type, department, is_active, created_at, updated_at)
     VALUES (:cid, 'Kaldis Bole Operations & Branches', 'supergroup', 'Operations', 1, :now1, :now2)",
    [':cid' => $opsChatId, ':now1' => $now, ':now2' => $now]
);
$opsGroup = Database::fetch("SELECT * FROM telegram_groups WHERE telegram_chat_id = :cid", [':cid' => $opsChatId]);
$opsGroupId = (int)$opsGroup['id'];

Database::execute(
    "INSERT INTO telegram_groups (telegram_chat_id, title, type, department, is_active, created_at, updated_at)
     VALUES (:cid, 'Kaldis Finance & Accounting', 'supergroup', 'Finance', 1, :now1, :now2)",
    [':cid' => $financeChatId, ':now1' => $now, ':now2' => $now]
);
$financeGroup = Database::fetch("SELECT * FROM telegram_groups WHERE telegram_chat_id = :cid", [':cid' => $financeChatId]);
$financeGroupId = (int)$financeGroup['id'];

assertTest("IT Group created with department 'IT'", $itGroup['department'] === 'IT');
assertTest("Operations Group created with department 'Operations'", $opsGroup['department'] === 'Operations');
assertTest("Finance Group created with department 'Finance'", $financeGroup['department'] === 'Finance');

// Create Tracked Messages in each department
Database::execute(
    "INSERT INTO tracked_messages (group_id, telegram_message_id, telegram_user_id, message_text, message_date, is_active, created_at, updated_at)
     VALUES (:gid, 9001, 814523272, 'IT Server patch maintenance', :mdate, 1, :now, :now)",
    [':gid' => $itGroupId, ':mdate' => $now, ':now' => $now]
);
$itMessage = Database::fetch("SELECT * FROM tracked_messages WHERE group_id = :gid AND telegram_message_id = 9001", [':gid' => $itGroupId]);

Database::execute(
    "INSERT INTO tracked_messages (group_id, telegram_message_id, telegram_user_id, message_text, message_date, is_active, created_at, updated_at)
     VALUES (:gid, 9002, 814523272, 'Operations coffee bean batch delivery schedule', :mdate, 1, :now, :now)",
    [':gid' => $opsGroupId, ':mdate' => $now, ':now' => $now]
);
$opsMessage = Database::fetch("SELECT * FROM tracked_messages WHERE group_id = :gid AND telegram_message_id = 9002", [':gid' => $opsGroupId]);

// --- Group 2: Department Admin User & Permission Checks ---
echo "\n--- Group 2: Department Admin User & Permission Checks ---\n";

// 1. Create an IT Department Manager
Database::execute(
    "INSERT INTO admins (username, email, password_hash, role, department, is_active, created_at, updated_at)
     VALUES ('it_manager_test', 'it_manager@kaldiscoffee.com', :hash, 'owner', 'IT', 1, :now, :now)",
    [':hash' => password_hash('Secret123!', PASSWORD_DEFAULT), ':now' => $now]
);
$itAdmin = Database::fetch("SELECT * FROM admins WHERE username = 'it_manager_test'");

// Simulate login as IT Manager
AuthService::startSession();
$_SESSION['admin_user'] = [
    'id' => (int)$itAdmin['id'],
    'username' => $itAdmin['username'],
    'email' => $itAdmin['email'],
    'role' => $itAdmin['role'],
    'department' => $itAdmin['department'],
];

assertTest("IT user department is detected as 'IT'", AuthService::userDepartment() === 'IT');
assertTest("IT user is marked as department-restricted", AuthService::isDepartmentRestricted() === true);

// Check access permissions
assertTest("IT user CAN access IT group", AuthService::canAccessGroup($itGroupId) === true);
assertTest("IT user CANNOT access Operations group", AuthService::canAccessGroup($opsGroupId) === false);
assertTest("IT user CANNOT access Finance group", AuthService::canAccessGroup($financeGroupId) === false);

assertTest("IT user CAN access IT message", AuthService::canAccessMessage((int)$itMessage['id']) === true);
assertTest("IT user CANNOT access Operations message", AuthService::canAccessMessage((int)$opsMessage['id']) === false);

// --- Group 3: Super Admin Access Verification ---
echo "\n--- Group 3: Super Admin Company-Wide Access ---\n";

// Simulate login as Super Admin
$_SESSION['admin_user'] = [
    'id' => 1,
    'username' => 'superadmin',
    'email' => 'superadmin@kaldiscoffee.com',
    'role' => 'super_admin',
    'department' => null,
];

assertTest("Super Admin has null userDepartment (unrestricted)", AuthService::userDepartment() === null);
assertTest("Super Admin is NOT department-restricted", AuthService::isDepartmentRestricted() === false);
assertTest("Super Admin can access IT group", AuthService::canAccessGroup($itGroupId) === true);
assertTest("Super Admin can access Operations group", AuthService::canAccessGroup($opsGroupId) === true);
assertTest("Super Admin can access Finance group", AuthService::canAccessGroup($financeGroupId) === true);
assertTest("Super Admin can access IT message", AuthService::canAccessMessage((int)$itMessage['id']) === true);
assertTest("Super Admin can access Operations message", AuthService::canAccessMessage((int)$opsMessage['id']) === true);

// --- Group 4: Cleanup ---
echo "\n--- Group 4: Teardown Test Data ---\n";
foreach ($testChatIds as $cid) {
    $g = Database::fetch("SELECT id FROM telegram_groups WHERE telegram_chat_id = :cid", [':cid' => $cid]);
    if ($g) {
        Database::execute("DELETE FROM message_responses WHERE tracked_message_id IN (SELECT id FROM tracked_messages WHERE group_id = :gid)", [':gid' => $g['id']]);
        Database::execute("DELETE FROM tracked_messages WHERE group_id = :gid", [':gid' => $g['id']]);
        Database::execute("DELETE FROM group_members WHERE group_id = :gid", [':gid' => $g['id']]);
        Database::execute("DELETE FROM telegram_groups WHERE id = :gid", [':gid' => $g['id']]);
    }
}
Database::execute("DELETE FROM admins WHERE username = 'it_manager_test'");

assertTest("Cleanup completed cleanly", true);

echo "\n========================================================\n";
echo "Department Isolation Tests: {$passed} Passed, {$failed} Failed\n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
