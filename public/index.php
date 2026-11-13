<?php
/**
 * Kaldis Coffee Telegram Reply Tracker (v2)
 * Front Controller & Routing
 */

declare(strict_types=1);

// Prevent PHP 8.5+ deprecation notices from leaking into UI
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

// 1. Security Headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.bunny.net https://fonts.googleapis.com; font-src 'self' https://cdn.jsdelivr.net https://fonts.bunny.net https://fonts.gstatic.com data:; img-src 'self' data: https:; connect-src 'self';");

// 2. Class Autoloader (Linux case-insensitive fallback)
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = dirname(__DIR__) . '/app/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);

    // 1. Direct path check (e.g. app/Services/AuthService.php)
    $direct = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($direct)) {
        require $direct;
        return;
    }

    // 2. Linux directory lowercase fallback (e.g. app/services/AuthService.php)
    $parts = explode('\\', $relativeClass);
    $className = array_pop($parts);
    $subDir = strtolower(implode('/', $parts));
    $fallback = $baseDir . ($subDir !== '' ? $subDir . '/' : '') . $className . '.php';
    if (file_exists($fallback)) {
        require $fallback;
        return;
    }
});

// 3. Load Helpers
require_once dirname(__DIR__) . '/app/helpers.php';

// 4. Global Error & Exception Handling
$debug = (bool)config('app.debug', false);
if (!$debug && isset($_GET['debug']) && $_GET['debug'] === 'kaldis2026') {
    $debug = true;
}

if ($debug) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

set_exception_handler(function (\Throwable $e) use ($debug) {
    error_log("Unhandled exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    if ($debug) {
        echo "<!DOCTYPE html><html><head><title>System Error</title><link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'></head><body class='p-5 bg-light'>";
        echo "<div class='card shadow-sm border-danger mx-auto' style='max-width:800px;'><div class='card-header bg-danger text-white fw-bold'>500 Internal Server Error</div><div class='card-body'>";
        echo "<h5 class='text-danger'>" . htmlspecialchars($e->getMessage()) . "</h5>";
        echo "<p class='small text-muted'>File: " . htmlspecialchars($e->getFile()) . " (Line " . $e->getLine() . ")</p>";
        echo "<pre class='bg-dark text-light p-3 rounded small overflow-auto' style='max-height:400px;'>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
        echo "</div></div></body></html>";
    } else {
        echo "<!DOCTYPE html><html><head><title>500 Internal Server Error</title></head><body style='font-family:sans-serif;text-align:center;padding:50px;'>";
        echo "<h1>500 Internal Server Error</h1><p>An unexpected error occurred. Please contact the system administrator or check server error logs.</p>";
        echo "</body></html>";
    }
    exit;
});

register_shutdown_function(function() use ($debug) {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        if ($debug) {
            echo "<!DOCTYPE html><html><head><title>Fatal PHP Error</title><link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'></head><body class='p-5 bg-light'>";
            echo "<div class='card shadow-sm border-danger mx-auto' style='max-width:800px;'><div class='card-header bg-danger text-white fw-bold'>Fatal PHP Error</div><div class='card-body'>";
            echo "<h5 class='text-danger'>" . htmlspecialchars($error['message'] ?? 'Unknown error') . "</h5>";
            echo "<p class='small text-muted'>File: " . htmlspecialchars($error['file'] ?? '') . " (Line " . ($error['line'] ?? '') . ")</p>";
            echo "</div></div></body></html>";
        }
    }
});

// 5. Parse Request URI & Method
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($requestMethod === 'HEAD') {
    $requestMethod = 'GET';
}

// Calculate base script path if installed in subdirectory (e.g. /tgroup/public or /tgroup)
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$scriptDir = str_replace('\\', '/', dirname($scriptName));

// Check if APP_URL has a subpath (e.g. /tgroup)
$appUrlPath = '';
$configuredAppUrl = (string)config('app.url', '');
if (!empty($configuredAppUrl)) {
    $parsedPath = parse_url($configuredAppUrl, PHP_URL_PATH);
    if (!empty($parsedPath) && $parsedPath !== '/') {
        $appUrlPath = rtrim($parsedPath, '/');
    }
}

// Strip leading subdirectory from request URI
$path = $requestUri;
if (!empty($appUrlPath) && str_starts_with($path, $appUrlPath)) {
    $path = substr($path, strlen($appUrlPath));
} elseif ($scriptDir !== '/' && $scriptDir !== '.' && str_starts_with($path, $scriptDir)) {
    $path = substr($path, strlen($scriptDir));
} elseif (str_starts_with($path, '/tgroup')) {
    $path = substr($path, strlen('/tgroup'));
}

$path = trim($path, '/');
// Strip public or index.php prefix if present (e.g. public/login or index.php/login)
$path = preg_replace('#^public/?#', '', $path);
$path = trim($path, '/');
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
use App\Controllers\AuthorizedSenderController;

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
    $path === 'groups/update-department' && $requestMethod === 'POST' => (new GroupController())->updateDepartment(),
    $path === 'groups/sync-admins' && $requestMethod === 'POST' => (new GroupController())->syncAdmins(),
    $path === 'groups/send-checkin' && $requestMethod === 'POST' => (new GroupController())->sendCheckinPrompt(),
    $path === 'groups/add-member' && $requestMethod === 'POST' => (new GroupController())->addMember(),
    $path === 'groups/bulk-import' && $requestMethod === 'POST' => (new GroupController())->bulkImportMembers(),
    $path === 'groups/toggle-active' && $requestMethod === 'POST' => (new GroupController())->toggleActive(),
    $path === 'groups/health-check' && $requestMethod === 'POST' => (new GroupController())->healthCheck(),
    $path === 'groups/view' && $requestMethod === 'GET' => (new GroupController())->view(),

    // Authorized Senders routes
    $path === 'senders' && $requestMethod === 'GET' => (new AuthorizedSenderController())->index(),
    $path === 'senders/create' && $requestMethod === 'POST' => (new AuthorizedSenderController())->create(),
    $path === 'senders/toggle' && $requestMethod === 'POST' => (new AuthorizedSenderController())->toggle(),
    $path === 'senders/delete' && $requestMethod === 'POST' => (new AuthorizedSenderController())->delete(),
    $path === 'api/senders/group-members' && $requestMethod === 'GET' => (new AuthorizedSenderController())->groupMembers(),

    // Tracked Messages routes
    $path === 'messages/create' && $requestMethod === 'POST' => (new TrackedMessageController())->create(),
    $path === 'messages/view' && $requestMethod === 'GET' => (new TrackedMessageController())->view(),
    $path === 'messages/confirm-response' && $requestMethod === 'POST' => (new TrackedMessageController())->confirmResponse(),
    $path === 'messages/reject-response' && $requestMethod === 'POST' => (new TrackedMessageController())->rejectResponse(),
    $path === 'messages/exclude-member' && $requestMethod === 'POST' => (new TrackedMessageController())->excludeMember(),
    $path === 'messages/include-member' && $requestMethod === 'POST' => (new TrackedMessageController())->includeMember(),
    $path === 'messages/manual-reply' && $requestMethod === 'POST' => (new TrackedMessageController())->manualMarkReplied(),
    $path === 'messages/send-reminder' && $requestMethod === 'POST' => (new TrackedMessageController())->sendReminder(),
    $path === 'messages/send-summary' && $requestMethod === 'POST' => (new TrackedMessageController())->sendSummaryReport(),
    $path === 'messages/set-deadline' && $requestMethod === 'POST' => (new TrackedMessageController())->setDeadline(),
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
    $path === 'settings/admins/update-department' && $requestMethod === 'POST' => (new SettingsController())->updateAdminDepartment(),
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
