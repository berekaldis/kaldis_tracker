<?php
namespace App\Controllers;

use App\Services\AuthService;
use App\Services\AuditService;
use App\Services\Database;
use App\Services\MessageTrackingService;
use DateTimeImmutable;
use DateTimeZone;

class TrackedMessageController
{
    /**
     * Display tracked message response details.
     */
    public function view(): void
    {
        AuthService::requireLogin();

        $messageId = (int)($_GET['id'] ?? 0);
        $message = Database::fetch(
            "SELECT tm.*, tg.title AS group_title, tg.telegram_chat_id 
             FROM tracked_messages tm
             JOIN telegram_groups tg ON tm.group_id = tg.id
             WHERE tm.id = :id",
            [':id' => $messageId]
        );

        if (!$message) {
            flash('danger', 'Tracked message not found.');
            redirect(url('dashboard'));
        }

        // Fetch roster members active at message_date along with any recorded response
        $sql = "SELECT m.id AS member_id, 
                       m.telegram_user_id, 
                       m.full_name, 
                       m.username, 
                       gm.source AS member_source,
                       gm.joined_at,
                       gm.left_at,
                       mr.id AS response_id,
                       mr.response_telegram_message_id,
                       mr.response_text,
                       mr.response_date,
                       mr.status,
                       mr.matching_method,
                       mr.excluded_reason,
                       mr.reviewed_at
                FROM group_members gm
                JOIN members m ON gm.member_id = m.id
                LEFT JOIN message_responses mr ON mr.tracked_message_id = :tmid AND mr.member_telegram_user_id = m.telegram_user_id
                WHERE gm.group_id = :gid 
                  AND gm.joined_at <= :mdate1 
                  AND (gm.left_at IS NULL OR gm.left_at > :mdate2)
                  AND m.is_bot = 0
                  AND m.telegram_user_id != :sender_id
                ORDER BY 
                  CASE 
                    WHEN mr.status = 'replied' THEN 1
                    WHEN mr.status = 'pending_review' THEN 2
                    WHEN mr.status = 'unknown' THEN 4
                    WHEN mr.status = 'excluded' THEN 5
                    ELSE 3
                  END,
                  m.full_name ASC";

        $members = Database::fetchAll($sql, [
            ':tmid' => $messageId,
            ':gid' => $message['group_id'],
            ':mdate1' => $message['message_date'],
            ':mdate2' => $message['message_date'],
            ':sender_id' => $message['telegram_user_id'],
        ]);

        // Normalize status for members who have no response row yet
        foreach ($members as &$m) {
            if (empty($m['status'])) {
                if ($m['member_source'] === 'imported') {
                    $m['status'] = 'not_replied';
                } else {
                    $m['status'] = 'unknown';
                }
            }
        }
        unset($m);

        // Compute stats for header summary
        $dashboardCtrl = new DashboardController();
        $stats = $dashboardCtrl->calculateMessageStats($messageId, (int)$message['group_id'], $message['message_date']);

        view('messages.view', [
            'message' => $message,
            'members' => $members,
            'stats' => $stats,
        ]);
    }

    /**
     * Mark a message as tracked manually from the dashboard.
     */
    public function create(): void
    {
        AuthService::requireOwner();
        AuthService::verifyCsrf();

        $groupId = (int)($_POST['group_id'] ?? 0);
        $telegramMessageId = trim($_POST['telegram_message_id'] ?? '');
        $telegramUserId = trim($_POST['telegram_user_id'] ?? '');
        $messageText = trim($_POST['message_text'] ?? '');
        $dateInput = trim($_POST['message_date'] ?? '');
        $threadId = !empty($_POST['thread_id']) ? (int)$_POST['thread_id'] : null;

        if (!$groupId || empty($telegramMessageId) || empty($messageText)) {
            flash('danger', 'Please provide Group, Telegram Message ID, and Message Content.');
            redirect(url('dashboard'));
        }

        $group = Database::fetch("SELECT * FROM telegram_groups WHERE id = :id", [':id' => $groupId]);
        if (!$group) {
            flash('danger', 'Selected group does not exist.');
            redirect(url('dashboard'));
        }

        // Determine sender ID
        if (empty($telegramUserId)) {
            $authUsers = (array)config('telegram.authorized_user_ids', []);
            $telegramUserId = $authUsers[0] ?? 0;
        }

        // Calculate UTC message date
        $utcDate = utc_now();
        if (!empty($dateInput)) {
            try {
                $appTz = new DateTimeZone(config('app.timezone', 'Africa/Addis_Ababa'));
                $utcTz = new DateTimeZone('UTC');
                $dt = new DateTimeImmutable($dateInput, $appTz);
                $utcDate = $dt->setTimezone($utcTz)->format('Y-m-d H:i:s');
            } catch (\Exception) {}
        }

        $tracker = new MessageTrackingService();
        try {
            $result = $tracker->registerTrackedMessage(
                $groupId,
                (int)$telegramMessageId,
                (int)$telegramUserId,
                $threadId,
                $messageText,
                $utcDate,
                'dashboard'
            );

            flash('success', "Message #{$telegramMessageId} in '{$group['title']}' is now tracked!");
            redirect(url('messages/view?id=' . $result['tracked_message_id']));
        } catch (\Throwable $e) {
            flash('danger', 'Error tracking message: ' . $e->getMessage());
            redirect(url('dashboard'));
        }
    }
}
