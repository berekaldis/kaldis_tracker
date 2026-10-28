<?php
namespace App\Controllers;

use App\Services\AuthService;
use App\Services\AuditService;
use App\Services\Database;
use App\Services\SettingService;
use App\Services\TelegramService;

class SettingsController
{
    /**
     * Display Bot Configuration Page (Super Admin only).
     */
    public function botConfig(): void
    {
        AuthService::requireSuperAdmin();

        $botToken = config('telegram.bot_token', '');
        $botUsername = config('telegram.bot_username', '');
        $webhookSecret = config('telegram.webhook_secret', '');
        $webhookUrl = config('telegram.webhook_url', '');
        if (empty($webhookUrl)) {
            $webhookUrl = url('webhook.php');
        }

        $authUsers = config('telegram.authorized_user_ids', []);
        $authUsersStr = is_array($authUsers) ? implode(', ', $authUsers) : (string)$authUsers;
        $trackHashtag = config('telegram.track_hashtag', '#track');
        $trackCommand = config('telegram.track_command', '/track');

        // Test Live Bot & Webhook Status if token is present
        $botInfo = null;
        $webhookInfo = null;
        if (!empty($botToken)) {
            $tg = new TelegramService($botToken);
            $botInfo = $tg->getMe();
            $webhookInfo = $tg->getWebhookInfo();
        }

        view('settings.bot', [
            'botToken' => $botToken,
            'botUsername' => $botUsername,
            'webhookSecret' => $webhookSecret,
            'webhookUrl' => $webhookUrl,
            'authUsersStr' => $authUsersStr,
            'trackHashtag' => $trackHashtag,
            'trackCommand' => $trackCommand,
            'botInfo' => $botInfo,
            'webhookInfo' => $webhookInfo,
            'pageTitle' => 'Bot Configuration',
        ]);
    }

    /**
     * Save Bot Configuration Settings.
     */
    public function saveBotConfig(): void
    {
        AuthService::requireSuperAdmin();
        AuthService::verifyCsrf();

        $botToken = trim($_POST['bot_token'] ?? '');
        $botUsername = trim(ltrim($_POST['bot_username'] ?? '', '@'));
        $webhookSecret = trim($_POST['webhook_secret'] ?? '');
        $webhookUrl = trim($_POST['webhook_url'] ?? '');
        $authorizedUserIds = trim($_POST['authorized_user_ids'] ?? '');
        $trackHashtag = trim($_POST['track_hashtag'] ?? '#track');
        $trackCommand = trim($_POST['track_command'] ?? '/track');

        if (!str_starts_with($trackHashtag, '#')) {
            $trackHashtag = '#' . $trackHashtag;
        }
        if (!str_starts_with($trackCommand, '/')) {
            $trackCommand = '/' . $trackCommand;
        }

        SettingService::setMany([
            'telegram_bot_token' => $botToken,
            'telegram_bot_username' => $botUsername,
            'telegram_webhook_secret' => $webhookSecret,
            'telegram_webhook_url' => $webhookUrl,
            'telegram_authorized_user_ids' => $authorizedUserIds,
            'telegram_track_hashtag' => $trackHashtag,
            'telegram_track_command' => $trackCommand,
        ]);

        AuditService::log('bot_configuration_updated', 'settings', 'telegram', [
            'bot_username' => $botUsername,
            'webhook_url' => $webhookUrl,
            'authorized_users' => $authorizedUserIds,
            'track_hashtag' => $trackHashtag,
        ]);

        flash('success', 'Telegram Bot configuration has been successfully saved!');
        redirect(url('settings/bot'));
    }

    /**
     * One-Click Set Webhook with Telegram Bot API.
     */
    public function setWebhook(): void
    {
        AuthService::requireSuperAdmin();
        AuthService::verifyCsrf();

        $botToken = config('telegram.bot_token', '');
        $webhookUrl = config('telegram.webhook_url', '');
        if (empty($webhookUrl)) {
            $webhookUrl = url('webhook.php');
        }
        $secretToken = config('telegram.webhook_secret', '');

        if (empty($botToken)) {
            flash('danger', 'Cannot register webhook: Bot Token is missing.');
            redirect(url('settings/bot'));
        }

        if (empty($webhookUrl) || !filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
            flash('danger', 'Cannot register webhook: Please provide a valid public HTTPS Webhook URL.');
            redirect(url('settings/bot'));
        }

        $tg = new TelegramService($botToken);
        $res = $tg->setWebhook($webhookUrl, $secretToken);

        if (!empty($res['ok'])) {
            AuditService::log('telegram_webhook_registered', 'settings', 'webhook', [
                'webhook_url' => $webhookUrl,
                'response' => $res['description'] ?? 'Webhook was set'
            ]);
            flash('success', 'Telegram Webhook registered successfully! ' . ($res['description'] ?? ''));
        } else {
            $err = $res['description'] ?? 'Unknown Telegram API error';
            flash('danger', 'Failed to set webhook: ' . $err);
        }

        redirect(url('settings/bot'));
    }

    /**
     * Delete Webhook from Telegram.
     */
    public function deleteWebhook(): void
    {
        AuthService::requireSuperAdmin();
        AuthService::verifyCsrf();

        $botToken = config('telegram.bot_token', '');
        if (empty($botToken)) {
            flash('danger', 'Bot Token is not configured.');
            redirect(url('settings/bot'));
        }

        $tg = new TelegramService($botToken);
        $res = $tg->deleteWebhook();

        if (!empty($res['ok'])) {
            AuditService::log('telegram_webhook_deleted', 'settings', 'webhook');
            flash('info', 'Telegram webhook has been removed.');
        } else {
            flash('danger', 'Failed to delete webhook: ' . ($res['description'] ?? 'Error'));
        }

        redirect(url('settings/bot'));
    }

    /**
     * Manage Admin Accounts (Super Admin only).
     */
    public function admins(): void
    {
        AuthService::requireSuperAdmin();

        $admins = Database::fetchAll("SELECT * FROM admins ORDER BY created_at ASC");
        view('settings.admins', [
            'admins' => $admins,
            'pageTitle' => 'Admin Users Management',
        ]);
    }

    /**
     * Create New Admin Account.
     */
    public function createAdmin(): void
    {
        AuthService::requireSuperAdmin();
        AuthService::verifyCsrf();

        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $role = trim($_POST['role'] ?? 'viewer');

        if (empty($username) || empty($email) || empty($password)) {
            flash('danger', 'Please provide Username, Email, and Password.');
            redirect(url('settings/admins'));
        }

        if (!in_array($role, ['super_admin', 'owner', 'viewer'])) {
            $role = 'viewer';
        }

        // Check duplicate
        $existing = Database::fetch("SELECT id FROM admins WHERE username = :u OR email = :e", [':u' => $username, ':e' => $email]);
        if ($existing) {
            flash('danger', 'An administrator with this username or email already exists.');
            redirect(url('settings/admins'));
        }

        $now = utc_now();
        Database::execute(
            "INSERT INTO admins (username, email, password_hash, role, is_active, created_at, updated_at)
             VALUES (:u, :e, :p, :role, 1, :now1, :now2)",
            [
                ':u' => $username,
                ':e' => $email,
                ':p' => password_hash($password, PASSWORD_DEFAULT),
                ':role' => $role,
                ':now1' => $now,
                ':now2' => $now,
            ]
        );

        $newId = Database::lastInsertId();
        AuditService::log('admin_user_created', 'admin', (string)$newId, [
            'username' => $username,
            'role' => $role,
            'email' => $email
        ]);

        flash('success', "New administrator '{$username}' ({$role}) created successfully!");
        redirect(url('settings/admins'));
    }

    /**
     * Toggle Admin Active Status.
     */
    public function toggleAdminStatus(): void
    {
        AuthService::requireSuperAdmin();
        AuthService::verifyCsrf();

        $adminId = (int)($_POST['admin_id'] ?? 0);
        $currentUser = AuthService::user();

        if ($adminId === (int)$currentUser['id']) {
            flash('danger', 'You cannot deactivate your own account.');
            redirect(url('settings/admins'));
        }

        $admin = Database::fetch("SELECT * FROM admins WHERE id = :id", [':id' => $adminId]);
        if (!$admin) {
            flash('danger', 'Admin user not found.');
            redirect(url('settings/admins'));
        }

        $newActive = $admin['is_active'] ? 0 : 1;
        Database::execute("UPDATE admins SET is_active = :act, updated_at = :now WHERE id = :id", [
            ':act' => $newActive,
            ':now' => utc_now(),
            ':id' => $adminId,
        ]);

        AuditService::log('admin_status_toggled', 'admin', (string)$adminId, [
            'username' => $admin['username'],
            'new_status' => $newActive ? 'active' : 'inactive'
        ]);

        flash('success', "Administrator '{$admin['username']}' is now " . ($newActive ? 'Active' : 'Deactivated') . ".");
        redirect(url('settings/admins'));
    }
}
