<?php
/**
 * Kaldis Coffee Telegram Reply Tracker (v2)
 * Main Application Configuration
 */

return [
    'app' => [
        'name' => 'Kaldis Coffee - Telegram Reply Tracker',
        'version' => '2.0.0',
        'url' => getenv('APP_URL') ?: 'http://localhost/tgroup/public',
        'timezone' => 'Africa/Addis_Ababa', // Display timezone
        'storage_timezone' => 'UTC',         // Database storage timezone
        'debug' => (bool)(getenv('APP_DEBUG') ?: true),
        'retention_days' => (int)(getenv('RETENTION_DAYS') ?: 180),
    ],

    'database' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => (int)(getenv('DB_PORT') ?: 3306),
        'name' => getenv('DB_NAME') ?: 'kaldis_tracker_dev',
        'user' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASS') !== false ? getenv('DB_PASS') : '',
        'charset' => 'utf8mb4',
    ],

    'telegram' => [
        'bot_token' => getenv('TELEGRAM_BOT_TOKEN') ?: '',
        'bot_username' => getenv('TELEGRAM_BOT_USERNAME') ?: '',
        'webhook_secret' => getenv('TELEGRAM_WEBHOOK_SECRET') ?: 'KaldisSecureWebhookToken_2026',
        'webhook_url' => getenv('TELEGRAM_WEBHOOK_URL') ?: '',
        // Numeric Telegram User IDs of authorized sender(s) whose #track messages get tracked.
        // Comma-separated or array of bigints (e.g. [123456789, 987654321])
        'authorized_user_ids' => array_filter(array_map('trim', explode(',', getenv('TELEGRAM_AUTHORIZED_USER_IDS') ?: '123456789'))),
        'track_hashtag' => getenv('TRACK_HASHTAG') ?: '#track',
        'track_command' => '/track',
        'enable_reactions' => (bool)(getenv('ENABLE_REACTIONS') ?: false),
        'reaction_counts_as_replied' => (bool)(getenv('REACTION_COUNTS_AS_REPLIED') ?: false),
    ],

    'security' => [
        'session_lifetime' => 7200, // 2 hours
        'session_name' => 'kaldis_session',
        'session_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'max_login_attempts' => 5,
        'lockout_time_seconds' => 900, // 15 minutes
        'csrf_token_name' => 'csrf_token',
    ],
];
