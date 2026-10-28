<?php
/**
 * Kaldis Coffee Telegram Reply Tracker (v2)
 * Direct Telegram Webhook Endpoint
 */

declare(strict_types=1);

// Class Autoloader
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

require_once dirname(__DIR__) . '/app/helpers.php';

use App\Controllers\WebhookController;

$controller = new WebhookController();
$controller->handle();
