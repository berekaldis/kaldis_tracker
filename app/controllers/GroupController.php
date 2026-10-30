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
     * Enforces Department Isolation: Department users only see their department's groups.
     */
    public function index(): void
    {
        AuthService::requireLogin();

        $userDept = AuthService::userDepartment();
        $params = [];
        $whereSql = '';

        if ($userDept !== null) {
            $whereSql = "WHERE g.department = :dept";
            $params[':dept'] = $userDept;
        }

        $groups = Database::fetchAll(
            "SELECT g.*, 
                    (SELECT COUNT(*) FROM group_members gm WHERE gm.group_id = g.id AND gm.is_active = 1) AS member_count,
                    (SELECT COUNT(*) FROM tracked_messages tm WHERE tm.group_id = g.id) AS tracked_count
             FROM telegram_groups g
             {$whereSql}
             ORDER BY g.created_at DESC",
            $params
        );

        $deptRows = Database::fetchAll("SELECT DISTINCT department FROM telegram_groups WHERE department IS NOT NULL AND department != '' ORDER BY department ASC");
        $departments = array_column($deptRows, 'department');

        view('groups.index', [
            'groups' => $groups,
            'userDept' => $userDept,
            'departments' => $departments,
        ]);
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

        // Department assignment
        $department = trim($_POST['department'] ?? 'General');
        if (empty($department)) {
            $department = 'General';
        }
        $userDept = AuthService::userDepartment();
        if ($userDept !== null) {
            $department = $userDept; // strictly assign to user's department
        }

        // Insert group
        $now = utc_now();
        Database::execute(
            "INSERT INTO telegram_groups 
             (telegram_chat_id, title, username, type, department, is_active, bot_status, bot_is_admin, bot_can_read_all_messages, last_health_check_at, created_at, updated_at)
             VALUES (:cid, :title, :username, :type, :dept, 1, :bot_status, :is_admin, :can_read, :health_at, :created_at, :updated_at)",
            [
                ':cid' => $chatId,
                ':title' => $title,
                ':username' => $username,
                ':type' => $type,
                ':dept' => $department,
                ':bot_status' => $botStatus,
                ':is_admin' => $botIsAdmin,
                ':can_read' => $canReadAll,
                ':health_at' => $now,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]
        );

        $groupId = (int)Database::lastInsertId();

        // Automatically sync group administrators upon registration
        $syncedAdmins = self::syncGroupAdministrators($groupId, $chatId);

        AuditService::log('group_registered', 'group', (string)$groupId, [
            'chat_id' => $chatId,
            'title' => $title,
            'department' => $department,
            'bot_status' => $botStatus,
            'is_admin' => $botIsAdmin,
            'synced_admins' => $syncedAdmins,
        ]);

        $successMsg = "Group '{$title}' successfully registered under '{$department}' department!";
        if ($syncedAdmins > 0) {
            $successMsg .= " Automatically imported {$syncedAdmins} administrator(s) from Telegram.";
        }
        flash('success', $successMsg);
        redirect(url('groups'));
    }

    /**
     * Update a group's assigned department.
     */
    public function updateDepartment(): void
    {
        AuthService::requireOwner();
        AuthService::verifyCsrf();

        $groupId = (int)($_POST['group_id'] ?? 0);
        $department = trim($_POST['department'] ?? 'General');
        if (empty($department)) {
            $department = 'General';
        }

        AuthService::requireGroupAccess($groupId);

        $group = Database::fetch("SELECT * FROM telegram_groups WHERE id = :id", [':id' => $groupId]);
        if (!$group) {
            flash('danger', 'Group not found.');
            redirect(url('groups'));
        }

        Database::execute(
            "UPDATE telegram_groups SET department = :dept, updated_at = :now WHERE id = :id",
            [':dept' => $department, ':now' => utc_now(), ':id' => $groupId]
        );

        AuditService::log('group_department_updated', 'group', (string)$groupId, [
            'title' => $group['title'],
            'old_department' => $group['department'] ?? 'General',
            'new_department' => $department,
        ]);

        flash('success', "Department for '{$group['title']}' updated to '{$department}'.");
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
        AuthService::requireGroupAccess($groupId);

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
        AuthService::requireGroupAccess($groupId);

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

        // Also sync administrators during health check
        $syncedAdmins = self::syncGroupAdministrators($groupId, $group['telegram_chat_id']);

        AuditService::log('group_health_checked', 'group', (string)$groupId, [
            'bot_status' => $botStatus,
            'is_admin' => $botIsAdmin,
            'can_read_all' => $canReadAll,
            'synced_admins' => $syncedAdmins,
        ]);

        $adminNote = ($syncedAdmins > 0) ? ", Synced Admins: {$syncedAdmins}" : '';
        flash('success', "Health check completed for '{$title}'. Bot Status: {$botStatus}, Admin: " . ($botIsAdmin ? 'Yes' : 'No') . $adminNote);
        redirect(url('groups'));
    }

    /**
     * View group roster and known members.
     */
    public function view(): void
    {
        AuthService::requireLogin();

        $groupId = (int)($_GET['id'] ?? 0);
        AuthService::requireGroupAccess($groupId);

        $group = Database::fetch("SELECT * FROM telegram_groups WHERE id = :id", [':id' => $groupId]);

        if (!$group) {
            flash('danger', 'Group not found.');
            redirect(url('groups'));
        }

        // Fetch live member count from Telegram API
        $telegramTotalMembers = null;
        $botToken = config('telegram.bot_token', '');
        if (!empty($botToken)) {
            $tg = new TelegramService($botToken);
            $countRes = $tg->getChatMemberCount($group['telegram_chat_id']);
            if (!empty($countRes['ok'])) {
                $telegramTotalMembers = (int)$countRes['result'];
            }
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
            'telegramTotalMembers' => $telegramTotalMembers,
        ]);
    }

    /**
     * Synchronize Group Administrators and Creators from Telegram API.
     */
    public function syncAdmins(): void
    {
        AuthService::requireOwner();
        AuthService::verifyCsrf();

        $groupId = (int)($_POST['group_id'] ?? 0);
        AuthService::requireGroupAccess($groupId);

        $group = Database::fetch("SELECT * FROM telegram_groups WHERE id = :id", [':id' => $groupId]);
        if (!$group) {
            flash('danger', 'Group not found.');
            redirect(url('groups'));
        }

        $synced = self::syncGroupAdministrators($groupId, $group['telegram_chat_id']);

        AuditService::log('group_admins_synced', 'group', (string)$groupId, [
            'group_title' => $group['title'],
            'admins_synced' => $synced,
        ]);

        if ($synced > 0) {
            flash('success', "Successfully synced {$synced} administrator(s) / manager(s) from Telegram for '{$group['title']}'!");
        } else {
            flash('info', "Group administrators are already up to date with Telegram.");
        }

        $redirectUrl = !empty($_POST['redirect_to_view']) ? url('groups/view?id=' . $groupId) : url('groups');
        redirect($redirectUrl);
    }

    /**
     * Manually add a member into a group roster.
     */
    public function addMember(): void
    {
        AuthService::requireOwner();
        AuthService::verifyCsrf();

        $groupId = (int)($_POST['group_id'] ?? 0);
        AuthService::requireGroupAccess($groupId);

        $telegramUserId = trim($_POST['telegram_user_id'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $username = trim(ltrim($_POST['username'] ?? '', '@'));

        if (empty($telegramUserId) || !preg_match('/^-?\d+$/', $telegramUserId)) {
            flash('danger', 'Please enter a valid numeric Telegram User ID.');
            redirect(url('groups/view?id=' . $groupId));
        }

        if (empty($fullName)) {
            $fullName = !empty($username) ? $username : 'Member ' . $telegramUserId;
        }

        $now = utc_now();
        $userId = (int)$telegramUserId;

        Database::execute(
            "INSERT INTO members (telegram_user_id, full_name, username, is_bot, created_at, updated_at)
             VALUES (:uid, :name, :uname, 0, :now1, :now2)
             ON DUPLICATE KEY UPDATE full_name = :name_u, username = :uname_u, updated_at = :now_u",
            [
                ':uid' => $userId,
                ':name' => $fullName,
                ':uname' => !empty($username) ? $username : null,
                ':now1' => $now,
                ':now2' => $now,
                ':name_u' => $fullName,
                ':uname_u' => !empty($username) ? $username : null,
                ':now_u' => $now,
            ]
        );

        $member = Database::fetch("SELECT id FROM members WHERE telegram_user_id = :uid", [':uid' => $userId]);
        $memberId = (int)$member['id'];

        Database::execute(
            "INSERT INTO group_members (group_id, member_id, role, joined_at, source, is_active, created_at, updated_at)
             VALUES (:gid, :mid, 'member', :now1, 'admin_added', 1, :now2, :now3)
             ON DUPLICATE KEY UPDATE is_active = 1, updated_at = :now_u",
            [
                ':gid' => $groupId,
                ':mid' => $memberId,
                ':now1' => $now,
                ':now2' => $now,
                ':now3' => $now,
                ':now_u' => $now,
            ]
        );

        AuditService::log('member_manually_added_to_group', 'group', (string)$groupId, [
            'telegram_user_id' => $userId,
            'full_name' => $fullName,
        ]);

        flash('success', "Member '{$fullName}' (ID: {$userId}) added to group roster.");
        redirect(url('groups/view?id=' . $groupId));
    }

    /**
     * Dispatch interactive Roll Call / Check-in message with button to the Telegram group.
     */
    public function sendCheckinPrompt(): void
    {
        AuthService::requireOwner();
        AuthService::verifyCsrf();

        $groupId = (int)($_POST['group_id'] ?? 0);
        AuthService::requireGroupAccess($groupId);

        $group = Database::fetch("SELECT * FROM telegram_groups WHERE id = :id", [':id' => $groupId]);
        if (!$group) {
            flash('danger', 'Group not found.');
            redirect(url('groups'));
        }

        $botToken = config('telegram.bot_token', '');
        if (empty($botToken)) {
            flash('danger', 'Telegram Bot Token is not configured.');
            redirect(url('groups/view?id=' . $groupId));
        }

        $tg = new TelegramService($botToken);
        $keyboard = [
            [
                [
                    'text' => '✋ Tap Here to Register / Check In',
                    'callback_data' => 'checkin:' . $group['id']
                ]
            ]
        ];

        $deptLabel = !empty($group['department']) ? " [{$group['department']}]" : "";
        $res = $tg->sendMessageWithKeyboard(
            $group['telegram_chat_id'],
            "☕ <b>Kaldis Coffee Roster Registration & Attendance Check-In</b>{$deptLabel}\n\n" .
            "Hello team! Please tap the button below to register your profile with the attendance system for <b>" . e($group['title']) . "</b>.\n\n" .
            "<i>(Once you tap the button, your profile will be automatically linked to the roster.)</i>",
            $keyboard
        );

        if (!empty($res['ok'])) {
            AuditService::log('checkin_prompt_sent_to_group', 'group', (string)$groupId, [
                'group_title' => $group['title'],
                'chat_id' => $group['telegram_chat_id'],
            ]);
            flash('success', "Interactive Check-In button sent to '{$group['title']}' on Telegram! Group members simply tap the button to register instantly.");
        } else {
            $err = $res['description'] ?? 'Telegram API error';
            flash('danger', "Failed to send check-in message to Telegram: {$err}. Ensure the bot has permission to post in this group.");
        }

        redirect(url('groups/view?id=' . $groupId));
    }

    /**
     * Bulk import members by pasting text or uploading CSV.
     */
    public function bulkImportMembers(): void
    {
        AuthService::requireOwner();
        AuthService::verifyCsrf();

        $groupId = (int)($_POST['group_id'] ?? 0);
        AuthService::requireGroupAccess($groupId);

        $group = Database::fetch("SELECT * FROM telegram_groups WHERE id = :id", [':id' => $groupId]);
        if (!$group) {
            flash('danger', 'Group not found.');
            redirect(url('groups'));
        }

        $rawText = trim($_POST['raw_members'] ?? '');

        // If file was uploaded, read file content
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $uploadedContent = file_get_contents($_FILES['csv_file']['tmp_name']);
            if ($uploadedContent !== false) {
                $rawText .= "\n" . $uploadedContent;
            }
        }

        if (empty(trim($rawText))) {
            flash('danger', 'Please provide a list of members to import or upload a CSV file.');
            redirect(url('groups/view?id=' . $groupId));
        }

        $lines = preg_split("/\r\n|\n|\r/", $rawText);
        $importedCount = 0;
        $now = utc_now();

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            // CSV or comma/tab separated: "Name, UserID or @username, Role"
            $parts = str_getcsv($line);
            if (count($parts) === 1 && str_contains($line, "\t")) {
                $parts = explode("\t", $line);
            }

            $name = trim($parts[0] ?? '');
            $identifier = trim($parts[1] ?? '');
            $role = strtolower(trim($parts[2] ?? 'member'));
            if (!in_array($role, ['member', 'admin', 'creator'])) {
                $role = 'member';
            }

            // If only identifier is provided (e.g. 814523272 or @username)
            if (empty($identifier) && !empty($name)) {
                if (preg_match('/^-?\d+$/', $name)) {
                    $identifier = $name;
                    $name = 'Member ' . $identifier;
                } elseif (str_starts_with($name, '@')) {
                    $identifier = $name;
                    $name = ltrim($name, '@');
                }
            }

            $userId = null;
            $username = null;

            if (preg_match('/^-?\d+$/', $identifier)) {
                $userId = (int)$identifier;
            } elseif (preg_match('/^-?\d+$/', $name)) {
                $userId = (int)$name;
            }

            if (str_starts_with($identifier, '@') || (!preg_match('/^-?\d+$/', $identifier) && !empty($identifier))) {
                $username = ltrim($identifier, '@');
            } elseif (str_starts_with($name, '@')) {
                $username = ltrim($name, '@');
            }

            if (!$userId && empty($username)) {
                continue; // Cannot import without a Telegram User ID or Username
            }

            // If no user ID is known, generate a negative deterministic placeholder based on username hash
            if (!$userId) {
                // Check if username already exists in members
                $existingMember = Database::fetch("SELECT * FROM members WHERE username = :un", [':un' => $username]);
                if ($existingMember) {
                    $userId = (int)$existingMember['telegram_user_id'];
                } else {
                    $userId = -(abs(crc32($username)) + 1000000);
                }
            }

            if (empty($name)) {
                $name = $username ?: 'Member ' . $userId;
            }

            Database::execute(
                "INSERT INTO members (telegram_user_id, full_name, username, is_bot, created_at, updated_at)
                 VALUES (:uid, :name, :uname, 0, :now1, :now2)
                 ON DUPLICATE KEY UPDATE 
                    full_name = :name_u,
                    username = COALESCE(:uname_u, username),
                    updated_at = :now_u",
                [
                    ':uid' => $userId,
                    ':name' => $name,
                    ':uname' => $username,
                    ':now1' => $now,
                    ':now2' => $now,
                    ':name_u' => $name,
                    ':uname_u' => $username,
                    ':now_u' => $now,
                ]
            );

            $m = Database::fetch("SELECT id FROM members WHERE telegram_user_id = :uid", [':uid' => $userId]);
            if ($m) {
                Database::execute(
                    "INSERT INTO group_members (group_id, member_id, role, joined_at, source, is_active, created_at, updated_at)
                     VALUES (:gid, :mid, :role, :now1, 'imported', 1, :now2, :now3)
                     ON DUPLICATE KEY UPDATE 
                        role = :role_u,
                        is_active = 1,
                        updated_at = :now_u",
                    [
                        ':gid' => $groupId,
                        ':mid' => $m['id'],
                        ':role' => $role,
                        ':now1' => $now,
                        ':now2' => $now,
                        ':now3' => $now,
                        ':role_u' => $role,
                        ':now_u' => $now,
                    ]
                );
                $importedCount++;
            }
        }

        AuditService::log('members_bulk_imported', 'group', (string)$groupId, [
            'group_title' => $group['title'],
            'imported_count' => $importedCount,
        ]);

        flash('success', "Bulk import completed! {$importedCount} member(s) added/updated on the roster for '{$group['title']}'.");
        redirect(url('groups/view?id=' . $groupId));
    }

    /**
     * Static helper: Fetch and upsert administrators from Telegram API into group_members.
     */
    public static function syncGroupAdministrators(int $groupId, int|string $telegramChatId): int
    {
        $botToken = config('telegram.bot_token', '');
        if (empty($botToken)) {
            return 0;
        }

        $tg = new TelegramService($botToken);
        $res = $tg->getChatAdministrators($telegramChatId);
        if (empty($res['ok']) || !is_array($res['result'])) {
            return 0;
        }

        $count = 0;
        $now = utc_now();

        foreach ($res['result'] as $adminItem) {
            $user = $adminItem['user'] ?? null;
            if (!$user || !empty($user['is_bot'])) {
                continue; // Skip bots (including our own bot)
            }

            $userId = (int)$user['id'];
            $fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
            if (empty($fullName)) {
                $fullName = $user['username'] ?? 'User ' . $userId;
            }
            $username = !empty($user['username']) ? $user['username'] : null;

            Database::execute(
                "INSERT INTO members (telegram_user_id, full_name, username, is_bot, created_at, updated_at)
                 VALUES (:uid, :name, :uname, 0, :now1, :now2)
                 ON DUPLICATE KEY UPDATE 
                    full_name = :name_u,
                    username = :uname_u,
                    updated_at = :now_u",
                [
                    ':uid' => $userId,
                    ':name' => $fullName,
                    ':uname' => $username,
                    ':now1' => $now,
                    ':now2' => $now,
                    ':name_u' => $fullName,
                    ':uname_u' => $username,
                    ':now_u' => $now,
                ]
            );

            $member = Database::fetch("SELECT id FROM members WHERE telegram_user_id = :uid", [':uid' => $userId]);
            if ($member) {
                $memberId = (int)$member['id'];
                $adminRole = ($adminItem['status'] === 'creator') ? 'creator' : 'admin';

                Database::execute(
                    "INSERT INTO group_members (group_id, member_id, role, joined_at, source, is_active, created_at, updated_at)
                     VALUES (:gid, :mid, :role, :now1, 'seen_in_group', 1, :now2, :now3)
                     ON DUPLICATE KEY UPDATE 
                        role = :role_u,
                        is_active = 1,
                        updated_at = :now_u",
                    [
                        ':gid' => $groupId,
                        ':mid' => $memberId,
                        ':role' => $adminRole,
                        ':now1' => $now,
                        ':now2' => $now,
                        ':now3' => $now,
                        ':role_u' => $adminRole,
                        ':now_u' => $now,
                    ]
                );
                $count++;
            }
        }

        return $count;
    }
}
