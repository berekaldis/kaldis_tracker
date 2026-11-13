<?php
/**
 * Kaldis Coffee Telegram Reply Tracker (v2)
 * Direct Telegram Webhook Endpoint
 */

declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

// Class Autoloader (Linux case-insensitive fallback)
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = dirname(__DIR__) . '/app/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);

    // 1. Direct path check
    $direct = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($direct)) {
        require $direct;
        return;
    }

    // 2. Linux directory lowercase fallback
    $parts = explode('\\', $relativeClass);
    $className = array_pop($parts);
    $subDir = strtolower(implode('/', $parts));
    $fallback = $baseDir . ($subDir !== '' ? $subDir . '/' : '') . $className . '.php';
    if (file_exists($fallback)) {
        require $fallback;
        return;
    }
});

require_once dirname(__DIR__) . '/app/helpers.php';

use App\Controllers\WebhookController;

$controller = new WebhookController();
$controller->handle();
