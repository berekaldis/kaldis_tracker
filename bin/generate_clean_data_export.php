<?php
/**
 * Export production data from local MySQL to clean, comment-safe SQL
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/services/Database.php';

use App\Services\Database;

$pdo = Database::getConnection();

$tables = [
    'tg_admins',
    'tg_system_settings',
    'tg_telegram_groups',
    'tg_members',
    'tg_group_members',
    'tg_tracked_messages',
    'tg_message_responses',
];

$output = [];
$output[] = "/* ========================================================================= */";
$output[] = "/* KALDIS COFFEE TELEGRAM TRACKER - PRODUCTION DATA MIGRATION                */";
$output[] = "/* Target Database: kaldisbp_ict                                              */";
$output[] = "/* Generated: " . gmdate('Y-m-d H:i:s') . " UTC                             */";
$output[] = "/* ========================================================================= */";
$output[] = "";
$output[] = "SET NAMES utf8mb4;";
$output[] = "SET FOREIGN_KEY_CHECKS = 0;";
$output[] = "";

foreach ($tables as $table) {
    $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(\PDO::FETCH_ASSOC);
    if (empty($rows)) {
        continue;
    }

    $output[] = "/* ------------------------------------------------------------------------- */";
    $output[] = "/* Table Data: {$table} (" . count($rows) . " rows)                                        */";
    $output[] = "/* ------------------------------------------------------------------------- */";

    $cols = array_keys($rows[0]);
    $quotedCols = array_map(fn($c) => "`{$c}`", $cols);
    $colsStr = implode(', ', $quotedCols);

    $valuesList = [];
    foreach ($rows as $row) {
        // Update old ngrok webhook URL if in settings
        if ($table === 'tg_system_settings' && ($row['setting_key'] ?? '') === 'telegram_webhook_url') {
            $row['setting_value'] = 'https://ict.kaldisbunnaet.com/tgroup/webhook';
        }

        $rowVals = [];
        foreach ($cols as $c) {
            $val = $row[$c];
            if ($val === null) {
                $rowVals[] = "NULL";
            } elseif (is_int($val) || is_float($val)) {
                $rowVals[] = (string)$val;
            } else {
                $rowVals[] = $pdo->quote((string)$val);
            }
        }
        $valuesList[] = "(" . implode(', ', $rowVals) . ")";
    }

    $output[] = "INSERT IGNORE INTO `{$table}` ({$colsStr}) VALUES";
    $output[] = implode(",\n", $valuesList) . ";";
    $output[] = "";
}

$output[] = "SET FOREIGN_KEY_CHECKS = 1;";
$output[] = "";
$output[] = "/* Migration data successfully prepared for kaldisbp_ict */";

$sql = implode("\n", $output);
file_put_contents(__DIR__ . '/../database/kaldisbp_ict_data_migration.sql', $sql);

echo "Successfully exported " . count($tables) . " tables to database/kaldisbp_ict_data_migration.sql\n";
