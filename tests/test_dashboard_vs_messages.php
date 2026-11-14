<?php
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

require_once __DIR__ . '/../app/helpers.php';

use App\Services\Database;
use App\Services\AuthService;
use App\Controllers\DashboardController;
use App\Controllers\TrackedMessageController;

echo "========================================================\n";
echo "Starting Dashboard vs Tracked Messages Verification\n";
echo "========================================================\n\n";

$passCount = 0;
$failCount = 0;

function it(string $desc, bool $condition): void {
    global $passCount, $failCount;
    if ($condition) {
        echo "  [PASS] {$desc}\n";
        $passCount++;
    } else {
        echo "  [FAIL] {$desc}\n";
        $failCount++;
    }
}

// 1. Simulate Super Admin Login
$_SESSION['admin_user'] = [
    'id' => 1,
    'username' => 'superadmin',
    'email' => 'superadmin@kaldisbunna.et',
    'role' => 'super_admin',
    'department' => null,
    'assigned_group_id' => null,
];
$_SERVER['REQUEST_URI'] = '/dashboard';

// Test Dashboard output buffer
ob_start();
(new DashboardController())->index();
$dashHtml = ob_get_clean();

it("Dashboard renders Executive Dashboard", str_contains($dashHtml, 'Executive Operations'));
it("Dashboard contains Compliance Matrix", str_contains($dashHtml, 'Monitored Groups Compliance Matrix'));
it("Dashboard contains Department Breakdown", str_contains($dashHtml, 'Department Compliance Breakdown'));
it("Dashboard contains System & Bot Health", str_contains($dashHtml, 'System &amp; Bot Health'));
it("Dashboard contains Recent Announcements Live Stream", str_contains($dashHtml, 'Recent Announcements Live Stream'));
it("Dashboard contains CTA link to messages", str_contains($dashHtml, 'Tracked Group Messages') && str_contains($dashHtml, 'href="'));

// Test Tracked Messages output buffer
$_SERVER['REQUEST_URI'] = '/messages';
ob_start();
(new TrackedMessageController())->index();
$msgHtml = ob_get_clean();

it("Messages renders Tracked Group Messages screen", str_contains($msgHtml, 'Tracked Group Messages'));
it("Messages contains Filter Toolbar", str_contains($msgHtml, 'Search message text or #ref...'));
it("Messages contains All Monitored Announcements table", str_contains($msgHtml, 'All Monitored Announcements'));
it("Messages contains SLA Status column", str_contains($msgHtml, 'SLA Status'));
it("Messages and Dashboard have distinct titles & layout", $dashHtml !== $msgHtml);

// 2. Simulate Group Authorizer Login
$_SESSION['admin_user'] = [
    'id' => 3,
    'username' => 'authorizer_g65',
    'email' => 'authorizer65@kaldisbunna.et',
    'role' => 'group_authorizer',
    'department' => 'Operations',
    'assigned_group_id' => 65,
];

ob_start();
(new DashboardController())->index();
$dashAuthHtml = ob_get_clean();

it("Group Authorizer Dashboard shows assigned group badge", str_contains($dashAuthHtml, 'Assigned Group #65'));

ob_start();
(new TrackedMessageController())->index();
$msgAuthHtml = ob_get_clean();

it("Group Authorizer Messages screen shows assigned group badge", str_contains($msgAuthHtml, 'Assigned Group #65'));

echo "\n========================================================\n";
echo "Test Summary: {$passCount} Passed, {$failCount} Failed\n";
echo "========================================================\n";

if ($failCount > 0) {
    exit(1);
}
