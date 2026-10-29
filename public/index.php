<?php
/**
 * Kaldis Coffee Telegram Reply Tracker (v2)
 * Front Controller & Routing
 */

declare(strict_types=1);

// 1. Security Headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; font-src 'self' https://cdn.jsdelivr.net data:; img-src 'self' data: https:; connect-src 'self';");

// 2. Class Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = dirname(__DIR__) . '/app/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// 3. Load Helpers
require_once dirname(__DIR__) . '/app/helpers.php';

// 4. Global Error & Exception Handling
if (config('app.debug', false)) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

set_exception_handler(function (\Throwable $e) {
    error_log("Unhandled exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    if (config('app.debug', false)) {
        echo "<h1>500 Internal Server Error</h1>";
        echo "<pre>" . e($e->getMessage() . "\n\n" . $e->getTraceAsString()) . "</pre>";
    } else {
        echo "<h1>500 Internal Server Error</h1><p>An unexpected error occurred. Please contact the system administrator.</p>";
    }
    exit;
});

// 5. Parse Request URI & Method
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($requestMethod === 'HEAD') {
    $requestMethod = 'GET';
}

// Calculate base script path if installed in subdirectory (e.g. /tgroup/public or /tgroup)
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$scriptDir = dirname($scriptName);
$normalizedDir = str_replace('\\', '/', $scriptDir);

if ($normalizedDir !== '/' && str_starts_with($requestUri, $normalizedDir)) {
    $path = substr($requestUri, strlen($normalizedDir));
} else {
    $path = $requestUri;
}

$path = trim($path, '/');
// Strip index.php prefix if present (e.g. /index.php/login or /index.php)
$path = preg_replace('#^index\.php/?#', '', $path);
$path = trim($path, '/');

// Support query parameter fallback (e.g. ?route=login)
if (!empty($_GET['route'])) {
    $path = trim($_GET['route'], '/');
} elseif (!empty($_GET['path'])) {
    $path = trim($_GET['path'], '/');
}

if (empty($path)) {
    $path = 'dashboard';
}

// 6. Application Routes
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\GroupController;
use App\Controllers\TrackedMessageController;
use App\Controllers\AuditController;
use App\Controllers\WebhookController;
use App\Controllers\SettingsController;

match (true) {
    // Auth routes
    $path === 'login' && $requestMethod === 'GET' => (new AuthController())->showLogin(),
    $path === 'login' && $requestMethod === 'POST' => (new AuthController())->login(),
    $path === 'logout' && $requestMethod === 'POST' => (new AuthController())->logout(),

    // Dashboard routes
    $path === 'dashboard' && $requestMethod === 'GET' => (new DashboardController())->index(),
    $path === 'dashboard/about' && $requestMethod === 'GET' => (new DashboardController())->about(),

    // Groups routes
    $path === 'groups' && $requestMethod === 'GET' => (new GroupController())->index(),
    $path === 'groups/register' && $requestMethod === 'POST' => (new GroupController())->register(),
    $path === 'groups/toggle-active' && $requestMethod === 'POST' => (new GroupController())->toggleActive(),
    $path === 'groups/health-check' && $requestMethod === 'POST' => (new GroupController())->healthCheck(),
    $path === 'groups/view' && $requestMethod === 'GET' => (new GroupController())->view(),

    // Tracked Messages routes
    $path === 'messages/create' && $requestMethod === 'POST' => (new TrackedMessageController())->create(),
    $path === 'messages/view' && $requestMethod === 'GET' => (new TrackedMessageController())->view(),
    $path === 'messages/confirm-response' && $requestMethod === 'POST' => (new TrackedMessageController())->confirmResponse(),
    $path === 'messages/reject-response' && $requestMethod === 'POST' => (new TrackedMessageController())->rejectResponse(),
    $path === 'messages/exclude-member' && $requestMethod === 'POST' => (new TrackedMessageController())->excludeMember(),
    $path === 'messages/include-member' && $requestMethod === 'POST' => (new TrackedMessageController())->includeMember(),
    $path === 'messages/manual-reply' && $requestMethod === 'POST' => (new TrackedMessageController())->manualMarkReplied(),
    $path === 'messages/export' && $requestMethod === 'GET' => (new TrackedMessageController())->exportCsv(),

    // Real-Time Live API routes
    $path === 'api/dashboard/live-stats' && $requestMethod === 'GET' => (new DashboardController())->liveStats(),
    $path === 'api/messages/live-details' && $requestMethod === 'GET' => (new TrackedMessageController())->liveDetails(),

    // Super Admin: Bot Configuration & Admin Management routes
    $path === 'settings/bot' && $requestMethod === 'GET' => (new SettingsController())->botConfig(),
    $path === 'settings/bot' && $requestMethod === 'POST' => (new SettingsController())->saveBotConfig(),
    $path === 'settings/set-webhook' && $requestMethod === 'POST' => (new SettingsController())->setWebhook(),
    $path === 'settings/delete-webhook' && $requestMethod === 'POST' => (new SettingsController())->deleteWebhook(),
    $path === 'settings/admins' && $requestMethod === 'GET' => (new SettingsController())->admins(),
    $path === 'settings/admins/create' && $requestMethod === 'POST' => (new SettingsController())->createAdmin(),
    $path === 'settings/admins/toggle' && $requestMethod === 'POST' => (new SettingsController())->toggleAdminStatus(),

    // Audit logs
    $path === 'audit' && $requestMethod === 'GET' => (new AuditController())->index(),

    // Webhook route
    ($path === 'webhook' || $path === 'webhook.php') => (new WebhookController())->handle(),

    default => (function() {
        http_response_code(404);
        echo "<!DOCTYPE html><html><head><title>404 Not Found</title></head><body style='font-family:sans-serif;text-align:center;padding:50px;'>";
        echo "<h1>404 Page Not Found</h1>";
        echo "<p>The requested URL was not found on this server.</p>";
        echo "<a href='" . url('dashboard') . "'>Return to Dashboard</a>";
        echo "</body></html>";
    })(),
};
