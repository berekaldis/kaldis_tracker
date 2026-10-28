<?php
/**
 * Kaldis Coffee Telegram Reply Tracker (v2)
 * Main Application Configuration
 */

return [
    'app' => [
        'name' => 'Kaldis Coffee - Telegram Reply Tracker',
        'version' => '2.0.0',
        'url' => getenv('APP_URL') ?: 'http://localhost/tgroup',
        'timezone' => 'Africa/Addis_Ababa', // Display timezone
        'storage_timezone' => 'UTC',         // Database storage timezone
        'debug' => true,
        'retention_days' => 180,
    ],

    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'kaldis_tracker_dev',
        'user' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],

    'telegram' => [
        'bot_token' => getenv('TELEGRAM_BOT_TOKEN') ?: '',
        'bot_username' => getenv('TELEGRAM_BOT_USERNAME') ?: 'KaldisTrackerBot',
        'webhook_secret' => getenv('TELEGRAM_WEBHOOK_SECRET') ?: 'KaldisSecureWebhookToken_2026',
        'webhook_url' => getenv('TELEGRAM_WEBHOOK_URL') ?: '',
        // Numeric Telegram User IDs of authorized sender(s) whose messages get tracked.
        'authorized_user_ids' => [123456789],
        'tracking_mode' => 'all_authorized', // 'all_authorized' (tracks each conversation), 'all_messages', or 'trigger_only'
        'track_all_messages' => true,
        'track_hashtag' => '#track',
        'track_command' => '/track',
        'enable_reactions' => false,
        'reaction_counts_as_replied' => false,
    ],

    'security' => [
        'session_lifetime' => 7200,
        'session_name' => 'kaldis_session',
        'session_secure' => false, // Set to true in production over HTTPS
        'max_login_attempts' => 5,
        'lockout_time_seconds' => 900,
        'csrf_token_name' => 'csrf_token',
    ],
];
