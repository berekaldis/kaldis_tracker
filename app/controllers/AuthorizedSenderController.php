<?php
namespace App\Controllers;

use App\Services\AuthService;
use App\Services\AuditService;
use App\Services\Database;

class AuthorizedSenderController
{
    /**
     * Display list of Authorized Senders grouped or filtered by Group.
     */
    public function index(): void
    {
        AuthService::requireLogin();

        $assignedGroupId = AuthService::assignedGroupId();
        $userDept = AuthService::userDepartment();
        $isGroupAuthorizer = AuthService::isGroupAuthorizer();

        $selectedGroupId = !empty($_GET['group_id']) ? (int)$_GET['group_id'] : null;
        $search = trim($_GET['search'] ?? '');

        // If user is a Group Authorizer with assigned group, strictly force to their group
        if ($assignedGroupId !== null) {
            $selectedGroupId = $assignedGroupId;
        }

        // Fetch accessible groups for dropdown
        if ($assignedGroupId !== null) {
            $groups = Database::fetchAll(
                "SELECT id, title, telegram_chat_id, department FROM telegram_groups WHERE id = :gid",
                [':gid' => $assignedGroupId]
            );
        } elseif ($userDept !== null) {
            $groups = Database::fetchAll(
                "SELECT id, title, telegram_chat_id, department FROM telegram_groups WHERE department = :dept ORDER BY title ASC",
                [':dept' => $userDept]
            );
        } else {
            $groups = Database::fetchAll(
                "SELECT id, title, telegram_chat_id, department FROM telegram_groups ORDER BY title ASC"
            );
        }

        // Build query for authorized senders
        $whereClauses = ["1=1"];
        $params = [];

        if ($assignedGroupId !== null) {
            $whereClauses[] = "gas.group_id = :agid";
            $params[':agid'] = $assignedGroupId;
        } elseif (!empty($selectedGroupId)) {
            $whereClauses[] = "gas.group_id = :sgid";
            $params[':sgid'] = $selectedGroupId;
        } elseif ($userDept !== null) {
            $whereClauses[] = "tg.department = :dept";
            $params[':dept'] = $userDept;
        }

        if (!empty($search)) {
            $whereClauses[] = "(gas.full_name LIKE :q OR gas.username LIKE :q OR gas.telegram_user_id LIKE :q OR gas.notes LIKE :q)";
            $params[':q'] = '%' . $search . '%';
        }

        $whereSql = implode(' AND ', $whereClauses);

        $senders = Database::fetchAll(
            "SELECT gas.*, tg.title AS group_title, tg.telegram_chat_id, tg.department 
             FROM group_authorized_senders gas
             JOIN telegram_groups tg ON gas.group_id = tg.id
             WHERE {$whereSql}
             ORDER BY tg.title ASC, gas.full_name ASC",
            $params
        );

        // Stats
        $totalSenders = count($senders);
        $activeSenders = 0;
        $uniqueGroups = [];
        foreach ($senders as $s) {
            if ($s['is_active']) $activeSenders++;
            $uniqueGroups[$s['group_id']] = true;
        }

        view('senders.index', [
            'senders' => $senders,
            'groups' => $groups,
            'selectedGroupId' => $selectedGroupId,
            'search' => $search,
            'totalSenders' => $totalSenders,
            'activeSenders' => $activeSenders,
            'totalGroupsWithSenders' => count($uniqueGroups),
            'assignedGroupId' => $assignedGroupId,
            'isGroupAuthorizer' => $isGroupAuthorizer,
        ]);
    }

    /**
     * Add a new Authorized Sender to a specific group.
     */
    public function create(): void
    {
        AuthService::requireCanTrack();
        AuthService::verifyCsrf();

        $groupId = (int)($_POST['group_id'] ?? 0);
        AuthService::requireGroupAccess($groupId);

        $telegramUserId = trim($_POST['telegram_user_id'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if (!$groupId || empty($telegramUserId)) {
            flash('danger', 'Please select a Target Group and provide a Telegram User ID.');
            redirect(url('senders'));
        }

        // Clean user ID and username
        $numericUserId = (int)preg_replace('/[^0-9]/', '', $telegramUserId);
        $cleanUsername = ltrim($username, '@');

        if (!$numericUserId) {
            flash('danger', 'Invalid numeric Telegram User ID.');
            redirect(url('senders'));
        }

        // If name is empty, auto-lookup from members table
        if (empty($fullName)) {
            $existing = Database::fetch(
                "SELECT full_name, username FROM members WHERE telegram_user_id = :uid",
                [':uid' => $numericUserId]
            );
            if ($existing) {
                $fullName = $existing['full_name'];
                if (empty($cleanUsername) && !empty($existing['username'])) {
                    $cleanUsername = $existing['username'];
                }
            } else {
                $fullName = 'Authorized Sender #' . $numericUserId;
            }
        }

        $now = utc_now();

        try {
            Database::execute(
                "INSERT INTO group_authorized_senders 
                  (group_id, telegram_user_id, full_name, username, notes, is_active, created_at, updated_at)
                 VALUES (:gid, :uid, :name, :uname, :notes, 1, :now1, :now2)
                 ON DUPLICATE KEY UPDATE 
                  full_name = VALUES(full_name),
                  username = VALUES(username),
                  notes = VALUES(notes),
                  is_active = 1,
                  updated_at = VALUES(updated_at)",
                [
                    ':gid' => $groupId,
                    ':uid' => $numericUserId,
                    ':name' => $fullName,
                    ':uname' => $cleanUsername ?: null,
                    ':notes' => $notes ?: null,
                    ':now1' => $now,
                    ':now2' => $now,
                ]
            );

            AuditService::log('authorized_sender_created', 'group_authorized_sender', (string)$numericUserId, [
                'group_id' => $groupId,
                'telegram_user_id' => $numericUserId,
                'full_name' => $fullName,
            ]);

            flash('success', "Authorized sender '{$fullName}' added to group successfully!");
        } catch (\Throwable $e) {
            flash('danger', 'Failed to add authorized sender: ' . $e->getMessage());
        }

        redirect(url('senders?group_id=' . $groupId));
    }

    /**
     * Toggle active / disabled status of an authorized sender.
     */
    public function toggle(): void
    {
        AuthService::requireCanTrack();
        AuthService::verifyCsrf();

        $senderId = (int)($_POST['sender_id'] ?? 0);
        $sender = Database::fetch("SELECT * FROM group_authorized_senders WHERE id = :id", [':id' => $senderId]);

        if (!$sender) {
            flash('danger', 'Authorized sender record not found.');
            redirect(url('senders'));
        }

        AuthService::requireGroupAccess((int)$sender['group_id']);

        $newStatus = $sender['is_active'] ? 0 : 1;
        Database::execute(
            "UPDATE group_authorized_senders SET is_active = :st, updated_at = :now WHERE id = :id",
            [':st' => $newStatus, ':now' => utc_now(), ':id' => $senderId]
        );

        AuditService::log('authorized_sender_status_toggled', 'group_authorized_sender', (string)$senderId, [
            'group_id' => $sender['group_id'],
            'new_status' => $newStatus,
        ]);

        flash('success', 'Authorized sender status updated.');
        redirect(url('senders?group_id=' . $sender['group_id']));
    }

    /**
     * Remove an authorized sender from a group.
     */
    public function delete(): void
    {
        AuthService::requireCanTrack();
        AuthService::verifyCsrf();

        $senderId = (int)($_POST['sender_id'] ?? 0);
        $sender = Database::fetch("SELECT * FROM group_authorized_senders WHERE id = :id", [':id' => $senderId]);

        if (!$sender) {
            flash('danger', 'Authorized sender record not found.');
            redirect(url('senders'));
        }

        AuthService::requireGroupAccess((int)$sender['group_id']);

        Database::execute("DELETE FROM group_authorized_senders WHERE id = :id", [':id' => $senderId]);

        AuditService::log('authorized_sender_deleted', 'group_authorized_sender', (string)$senderId, [
            'group_id' => $sender['group_id'],
            'telegram_user_id' => $sender['telegram_user_id'],
            'full_name' => $sender['full_name'],
        ]);

        flash('success', "Authorized sender '{$sender['full_name']}' removed from group.");
        redirect(url('senders?group_id=' . $sender['group_id']));
    }

    /**
     * API: Get group members for quick-adding in modal.
     */
    public function groupMembers(): void
    {
        AuthService::requireLogin();
        header('Content-Type: application/json');

        $groupId = (int)($_GET['group_id'] ?? 0);
        if (!$groupId || !AuthService::canAccessGroup($groupId)) {
            echo json_encode(['ok' => false, 'error' => 'forbidden']);
            exit;
        }

        $members = Database::fetchAll(
            "SELECT m.id, m.telegram_user_id, m.full_name, m.username, gm.role 
             FROM group_members gm
             JOIN members m ON gm.member_id = m.id
             WHERE gm.group_id = :gid AND gm.is_active = 1 AND m.is_bot = 0
             ORDER BY m.full_name ASC",
            [':gid' => $groupId]
        );

        echo json_encode(['ok' => true, 'members' => $members]);
        exit;
    }
}
