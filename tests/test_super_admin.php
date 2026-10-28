<?php
/**
 * Kaldis Coffee Telegram Reply Tracker (v2)
 * Super Admin & Dynamic Settings Test Suite
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
use App\Services\SettingService;

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
echo "Starting Super Admin & Bot Settings Test Suite\n";
echo "========================================================\n\n";

// 1. Verify Super Admin User in Database
echo "--- Group 1: Super Admin Role & Accounts ---\n";
$superUser = Database::fetch("SELECT * FROM admins WHERE username = 'superadmin'");
assertTest("Dedicated superadmin account exists in database", $superUser !== null);
assertTest("superadmin user has 'super_admin' role", ($superUser['role'] ?? '') === 'super_admin');
assertTest("superadmin account password verifies correctly", password_verify('KaldisSuperAdmin2026!', $superUser['password_hash']));

$adminUser = Database::fetch("SELECT * FROM admins WHERE username = 'admin'");
assertTest("admin user has also been upgraded to 'super_admin'", ($adminUser['role'] ?? '') === 'super_admin');

// 2. Verify Role Logic in AuthService
echo "\n--- Group 2: AuthService Role Checks ---\n";
$_SESSION['admin_user'] = ['id' => 2, 'username' => 'superadmin', 'role' => 'super_admin'];
assertTest("isSuperAdmin() returns true for super_admin", AuthService::isSuperAdmin() === true);
assertTest("isOwner() returns true for super_admin (full access)", AuthService::isOwner() === true);

$_SESSION['admin_user'] = ['id' => 3, 'username' => 'owner_test', 'role' => 'owner'];
assertTest("isSuperAdmin() returns false for owner", AuthService::isSuperAdmin() === false);
assertTest("isOwner() returns true for owner", AuthService::isOwner() === true);

$_SESSION['admin_user'] = ['id' => 4, 'username' => 'viewer_test', 'role' => 'viewer'];
assertTest("isSuperAdmin() returns false for viewer", AuthService::isSuperAdmin() === false);
assertTest("isOwner() returns false for viewer", AuthService::isOwner() === false);

// 3. Verify Dynamic Settings Service
echo "\n--- Group 3: Dynamic System Settings Storage ---\n";
SettingService::set('telegram_test_key', 'KaldisValue123');
assertTest("SettingService::set() persists in memory and DB", SettingService::get('telegram_test_key') === 'KaldisValue123');

$dbVal = Database::fetchColumn("SELECT setting_value FROM system_settings WHERE setting_key = 'telegram_test_key'");
assertTest("Setting value stored in system_settings table", $dbVal === 'KaldisValue123');

// Test config() override
SettingService::set('telegram_track_hashtag', '#kaldis_urgent');
assertTest("config('telegram.track_hashtag') returns database override", config('telegram.track_hashtag') === '#kaldis_urgent');

// Clean up test setting
SettingService::set('telegram_track_hashtag', '#track');
Database::execute("DELETE FROM system_settings WHERE setting_key = 'telegram_test_key'");

echo "\n========================================================\n";
echo "Super Admin Tests: {$passed} Passed, {$failed} Failed\n";
echo "========================================================\n";

if ($failed > 0) exit(1);
