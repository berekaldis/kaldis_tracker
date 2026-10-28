<?php
namespace App\Controllers;

use App\Services\AuthService;
use App\Services\AuditService;
use App\Services\Database;
use App\Services\TelegramService;

class GroupController
{
    /**
     * List all registered groups with health and monitoring status.
     */
    public function index(): void
    {
        AuthService::requireLogin();

        $groups = Database::fetchAll(
            "SELECT g.*, 
                    (SELECT COUNT(*) FROM group_members gm WHERE gm.group_id = g.id AND gm.is_active = 1) AS member_count,
                    (SELECT COUNT(*) FROM tracked_messages tm WHERE tm.group_id = g.id) AS tracked_count
             FROM telegram_groups g
             ORDER BY g.created_at DESC"
        );

        view('groups.index', ['groups' => $groups]);
    }

    /**
     * Register a new group by Telegram Chat ID.
     */
    public function register(): void
    {
        AuthService::requireOwner();
        AuthService::verifyCsrf();

        $chatIdInput = trim($_POST['telegram_chat_id'] ?? '');

        if (empty($chatIdInput) || !preg_match('/^-?[0-9]+$/', $chatIdInput)) {
            flash('danger', 'Please enter a valid numeric Telegram Chat ID (e.g. -1001234567890).');
            redirect(url('groups'));
        }

        $chatId = (int)$chatIdInput;

        // Check if group is already registered
        $existing = Database::fetch("SELECT * FROM telegram_groups WHERE telegram_chat_id = :cid", [':cid' => $chatId]);
        if ($existing) {
            flash('warning', "Group '{$existing['title']}' is already registered.");
            redirect(url('groups'));
        }

        // Validate bot membership via Telegram Bot API
        $tg = new TelegramService();
        $chatInfo = $tg->getChat($chatId);

        if (empty($chatInfo['ok'])) {
            $err = $chatInfo['description'] ?? 'Unable to connect to Telegram Bot API.';
            flash('danger', "Telegram verification failed: {$err}. Ensure the bot is added to this group.");
            redirect(url('groups'));
        }

        $chatData = $chatInfo['result'];
        $title = $chatData['title'] ?? 'Group ' . $chatId;
        $username = $chatData['username'] ?? null;
        $type = $chatData['type'] ?? 'supergroup';

        // Check bot membership and permissions in the group
        $botMe = $tg->getMe();
        $botId = $botMe['result']['id'] ?? null;
        $botIsAdmin = 0;
        $botStatus = 'active';
        $canReadAll = 0;

        if ($botId) {
            $memberInfo = $tg->getChatMember($chatId, $botId);
            if (!empty($memberInfo['ok'])) {
                $status = $memberInfo['result']['status'] ?? '';
                $botIsAdmin = ($status === 'administrator' || $status === 'creator') ? 1 : 0;
                $canReadAll = !empty($memberInfo['result']['can_read_all_group_messages']) ? 1 : 0;
                $botStatus = match ($status) {
                    'administrator', 'creator', 'member' => 'active',
                    'restricted' => 'restricted',
                    'left' => 'left',
                    'kicked' => 'kicked',
                    default => 'unknown',
                };
            }
        }

        // Insert group
        $now = utc_now();
        Database::execute(
            "INSERT INTO telegram_groups 
             (telegram_chat_id, title, username, type, is_active, bot_status, bot_is_admin, bot_can_read_all_messages, last_health_check_at, created_at, updated_at)
             VALUES (:cid, :title, :username, :type, 1, :bot_status, :is_admin, :can_read, :health_at, :created_at, :updated_at)",
            [
                ':cid' => $chatId,
                ':title' => $title,
                ':username' => $username,
                ':type' => $type,
                ':bot_status' => $botStatus,
                ':is_admin' => $botIsAdmin,
                ':can_read' => $canReadAll,
                ':health_at' => $now,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]
        );

        $groupId = (int)Database::lastInsertId();

        AuditService::log('group_registered', 'group', (string)$groupId, [
            'chat_id' => $chatId,
            'title' => $title,
            'bot_status' => $botStatus,
            'is_admin' => $botIsAdmin,
        ]);

        flash('success', "Group '{$title}' successfully registered and monitoring activated!");
        redirect(url('groups'));
    }

    /**
     * Toggle monitoring active/inactive.
     */
    public function toggleActive(): void
    {
        AuthService::requireOwner();
        AuthService::verifyCsrf();

        $groupId = (int)($_POST['group_id'] ?? 0);
        $group = Database::fetch("SELECT * FROM telegram_groups WHERE id = :id", [':id' => $groupId]);

        if (!$group) {
            flash('danger', 'Group not found.');
            redirect(url('groups'));
        }

        $newActive = $group['is_active'] ? 0 : 1;
        Database::execute(
            "UPDATE telegram_groups SET is_active = :act, updated_at = :now WHERE id = :id",
            [':act' => $newActive, ':now' => utc_now(), ':id' => $groupId]
        );

        $action = $newActive ? 'group_activated' : 'group_deactivated';
        AuditService::log($action, 'group', (string)$groupId, [
            'title' => $group['title'],
            'new_status' => $newActive ? 'active' : 'inactive'
        ]);

        flash('success', "Monitoring for '{$group['title']}' has been " . ($newActive ? 'activated' : 'deactivated') . ".");
        redirect(url('groups'));
    }

    /**
     * Run an immediate health check on a group.
     */
    public function healthCheck(): void
    {
        AuthService::requireOwner();
        AuthService::verifyCsrf();

        $groupId = (int)($_POST['group_id'] ?? 0);
        $group = Database::fetch("SELECT * FROM telegram_groups WHERE id = :id", [':id' => $groupId]);

        if (!$group) {
            flash('danger', 'Group not found.');
            redirect(url('groups'));
        }

        $tg = new TelegramService();
        $chatInfo = $tg->getChat($group['telegram_chat_id']);

        if (empty($chatInfo['ok'])) {
            $err = $chatInfo['description'] ?? 'Unknown error';
            Database::execute(
                "UPDATE telegram_groups SET bot_status = 'restricted', last_health_check_at = :now WHERE id = :id",
                [':now' => utc_now(), ':id' => $groupId]
            );
            flash('danger', "Health check warning for '{$group['title']}': {$err}");
            redirect(url('groups'));
        }

        $botMe = $tg->getMe();
        $botId = $botMe['result']['id'] ?? null;
        $botIsAdmin = 0;
        $botStatus = 'active';
        $canReadAll = 0;

        if ($botId) {
            $memberInfo = $tg->getChatMember($group['telegram_chat_id'], $botId);
            if (!empty($memberInfo['ok'])) {
                $status = $memberInfo['result']['status'] ?? '';
                $botIsAdmin = ($status === 'administrator' || $status === 'creator') ? 1 : 0;
                $canReadAll = !empty($memberInfo['result']['can_read_all_group_messages']) ? 1 : 0;
                $botStatus = match ($status) {
                    'administrator', 'creator', 'member' => 'active',
                    'restricted' => 'restricted',
                    'left' => 'left',
                    'kicked' => 'kicked',
                    default => 'unknown',
                };
            }
        }

        $title = $chatInfo['result']['title'] ?? $group['title'];

        Database::execute(
            "UPDATE telegram_groups 
             SET title = :title, bot_status = :status, bot_is_admin = :is_admin, 
                 bot_can_read_all_messages = :read_all, last_health_check_at = :health_at, updated_at = :updated_at 
             WHERE id = :id",
            [
                ':title' => $title,
                ':status' => $botStatus,
                ':is_admin' => $botIsAdmin,
                ':read_all' => $canReadAll,
                ':health_at' => utc_now(),
                ':updated_at' => utc_now(),
                ':id' => $groupId
            ]
        );

        AuditService::log('group_health_checked', 'group', (string)$groupId, [
            'bot_status' => $botStatus,
            'is_admin' => $botIsAdmin,
            'can_read_all' => $canReadAll
        ]);

        flash('success', "Health check completed for '{$title}'. Bot Status: {$botStatus}, Admin: " . ($botIsAdmin ? 'Yes' : 'No'));
        redirect(url('groups'));
    }

    /**
     * View group roster and known members.
     */
    public function view(): void
    {
        AuthService::requireLogin();

        $groupId = (int)($_GET['id'] ?? 0);
        $group = Database::fetch("SELECT * FROM telegram_groups WHERE id = :id", [':id' => $groupId]);

        if (!$group) {
            flash('danger', 'Group not found.');
            redirect(url('groups'));
        }

        $members = Database::fetchAll(
            "SELECT m.*, gm.role, gm.joined_at, gm.left_at, gm.source, gm.is_active AS membership_active
             FROM group_members gm
             JOIN members m ON gm.member_id = m.id
             WHERE gm.group_id = :gid
             ORDER BY gm.joined_at DESC",
            [':gid' => $groupId]
        );

        view('groups.view', [
            'group' => $group,
            'members' => $members,
        ]);
    }
}
