<?php
/**
 * Kaldis Coffee Tracker - Scheduled Background Maintenance
 * 
 * Recommended cPanel Cron Job schedule: Every 5 or 10 minutes
 * Command: php /home/kaldisbp/ict.kaldisbunnaet.com/tgroup/bin/cron.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/services/Database.php';
require_once __DIR__ . '/../app/services/SettingService.php';
require_once __DIR__ . '/../app/services/AuditService.php';
require_once __DIR__ . '/../app/services/TelegramService.php';
require_once __DIR__ . '/../app/controllers/DashboardController.php';
require_once __DIR__ . '/../app/services/MessageTrackingService.php';

echo "[" . gmdate('Y-m-d H:i:s') . "] Starting Kaldis Tracker maintenance tasks...\n";

// 1. Verify schema integrity
try {
    \App\Services\Database::ensureSchemaIntegrity();
} catch (\Throwable $e) {
    echo "Schema integrity error: " . $e->getMessage() . "\n";
}

// 2. Process expired message deadlines and dispatch summary notifications to Authorized Senders
try {
    $service = new \App\Services\MessageTrackingService();
    $summaryResult = $service->processExpiredDeadlinesAndSendSummaries();
    echo "Deadline summaries: " . json_encode($summaryResult) . "\n";
} catch (\Throwable $e) {
    echo "Summary dispatch error: " . $e->getMessage() . "\n";
}

// 3. Purge old tracked messages according to data retention policy (Week, 15 days, 30 days, etc.)
try {
    $cleanupResult = \App\Services\MessageTrackingService::autoDeleteOldTrackedMessages();
    echo "Retention cleanup: " . json_encode($cleanupResult) . "\n";
} catch (\Throwable $e) {
    echo "Retention cleanup error: " . $e->getMessage() . "\n";
}

echo "[" . gmdate('Y-m-d H:i:s') . "] Maintenance tasks completed.\n";
