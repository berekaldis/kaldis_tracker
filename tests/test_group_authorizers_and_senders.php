<?php
/**
 * Kaldis Coffee Telegram Reply Tracker (v2)
 * Test Suite: Authorized Senders by Groups, Group Authorizer Role, @kaldisbunna.et, kaldis123
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
    else {
        $file2 = $baseDir . strtolower(str_replace('\\', '/', $relativeClass)) . '.php';
        if (file_exists($file2)) require $file2;
    }
});

require_once dirname(__DIR__) . '/app/helpers.php';

use App\Services\Database;
use App\Services\AuthService;
use App\Services\MessageTrackingService;

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
echo "Starting Authorized Senders & Group Authorizer Test Suite\n";
echo "========================================================\n\n";

// --- Group 1: Default Admin Emails (@kaldisbunna.et) & Password (kaldis123) ---
echo "--- Group 1: Default Admin Credentials & Domain ---\n";

$superadmin = Database::fetch("SELECT * FROM admins WHERE username = 'superadmin'");
assertTest("Superadmin exists in database", $superadmin !== null);
assertTest("Superadmin email has domain @kaldisbunna.et", str_ends_with($superadmin['email'] ?? '', '@kaldisbunna.et'));
assertTest("Superadmin password verifies with 'kaldis123'", password_verify('kaldis123', $superadmin['password_hash'] ?? ''));

$admin = Database::fetch("SELECT * FROM admins WHERE username = 'admin'");
assertTest("Admin exists in database", $admin !== null);
assertTest("Admin email has domain @kaldisbunna.et", str_ends_with($admin['email'] ?? '', '@kaldisbunna.et'));
assertTest("Admin password verifies with 'kaldis123'", password_verify('kaldis123', $admin['password_hash'] ?? ''));

// --- Group 2: Group Authorized Senders Table & Per-Group Isolation ---
echo "\n--- Group 2: Authorized Senders by Groups ---\n";

$tracker = new MessageTrackingService();

// Group 21 and Group 65
$group21 = Database::fetch("SELECT id FROM telegram_groups WHERE id = 21");
$group65 = Database::fetch("SELECT id FROM telegram_groups WHERE id = 65");

if ($group21 && $group65) {
    // Add unique sender to group 21 ONLY
    $testSenderId = 999111222;
    Database::execute(
        "INSERT INTO group_authorized_senders (group_id, telegram_user_id, full_name, username, notes, is_active, created_at, updated_at)
         VALUES (21, :uid, 'Test Roastery Supervisor', 'roastery_sup', 'Test', 1, NOW(), NOW())
         ON DUPLICATE KEY UPDATE is_active = 1",
        [':uid' => $testSenderId]
    );

    // Make sure testSenderId is NOT in group 65
    Database::execute("DELETE FROM group_authorized_senders WHERE group_id = 65 AND telegram_user_id = :uid", [':uid' => $testSenderId]);

    // Check authorization
    $isAuthGroup21 = $tracker->isAuthorizedSenderForGroup(21, $testSenderId);
    $isAuthGroup65 = $tracker->isAuthorizedSenderForGroup(65, $testSenderId);

    assertTest("Sender #999111222 is authorized for Group #21", $isAuthGroup21 === true);
    assertTest("Sender #999111222 is NOT authorized for Group #65", $isAuthGroup65 === false);

    // Clean up test sender
    Database::execute("DELETE FROM group_authorized_senders WHERE telegram_user_id = :uid", [':uid' => $testSenderId]);
} else {
    echo "  [SKIP] Groups 21 or 65 missing for per-group sender test\n";
}

// --- Group 3: Group Authorizer Role & Group Access Restrictions ---
echo "\n--- Group 3: Group Authorizer Access Scope ---\n";

// Mock user session as group_authorizer assigned to group 65
$_SESSION['admin_user'] = [
    'id' => 999,
    'username' => 'test_airport_authorizer',
    'email' => 'airport_lead@kaldisbunna.et',
    'role' => 'group_authorizer',
    'department' => 'Operations',
    'assigned_group_id' => 65,
];

assertTest("isGroupAuthorizer() identifies group_authorizer role", AuthService::isGroupAuthorizer() === true);
assertTest("canTrack() allows group_authorizer to track", AuthService::canTrack() === true);
assertTest("canAccessGroup(65) allows assigned group 65", AuthService::canAccessGroup(65) === true);
assertTest("canAccessGroup(21) denies unassigned group 21", AuthService::canAccessGroup(21) === false);

// Test group authorizer with NO assigned group
$_SESSION['admin_user']['assigned_group_id'] = null;
assertTest("Group authorizer with no assigned group is denied group 65", AuthService::canAccessGroup(65) === false);
assertTest("Group authorizer with no assigned group is denied group 21", AuthService::canAccessGroup(21) === false);

// Restore session to superadmin
$_SESSION['admin_user'] = [
    'id' => 1,
    'username' => 'superadmin',
    'email' => 'superadmin@kaldisbunna.et',
    'role' => 'super_admin',
    'department' => null,
    'assigned_group_id' => null,
];

assertTest("Superadmin has unrestricted group access to Group 21", AuthService::canAccessGroup(21) === true);
assertTest("Superadmin has unrestricted group access to Group 65", AuthService::canAccessGroup(65) === true);

echo "\n========================================================\n";
echo "Test Summary: {$passed} Passed, {$failed} Failed\n";
echo "========================================================\n";
