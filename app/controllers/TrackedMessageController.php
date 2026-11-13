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
        AuthService::requireMessageAccess($messageId);

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

        // Compute stats for header summary
        $dashboardCtrl = new DashboardController();
        $stats = $dashboardCtrl->calculateMessageStats($messageId, (int)$message['group_id'], $message['message_date']);

        $msgTimestamp = strtotime($message['message_date']);

        // Normalize status & calculate individual turnaround times
        foreach ($members as &$m) {
            if (empty($m['status'])) {
                if ($m['member_source'] === 'imported') {
                    $m['status'] = 'not_replied';
                } else {
                    $m['status'] = 'unknown';
                }
            }

            $m['turnaround_seconds'] = null;
            $m['turnaround_formatted'] = '—';
            if ($m['status'] === 'replied' && !empty($m['response_date'])) {
                $respTime = strtotime($m['response_date']);
                $diff = max(0, $respTime - $msgTimestamp);
                $m['turnaround_seconds'] = $diff;
                $m['turnaround_formatted'] = format_duration($diff);
            }

            $m['is_overdue'] = (!empty($stats['is_overdue']) && in_array($m['status'], ['not_replied', 'unknown']));
        }
        unset($m);

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
        AuthService::requireCanTrack();
        AuthService::verifyCsrf();

        $groupId = (int)($_POST['group_id'] ?? 0);
        AuthService::requireGroupAccess($groupId);
        $telegramMessageId = trim($_POST['telegram_message_id'] ?? '');
        $telegramUserId = trim($_POST['telegram_user_id'] ?? '');
        $messageText = trim($_POST['message_text'] ?? '');
        $dateInput = trim($_POST['message_date'] ?? '');
        $threadId = !empty($_POST['thread_id']) ? (int)$_POST['thread_id'] : null;
        $deadlineInput = trim($_POST['deadline_at'] ?? '');

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

        $deadlineUtc = null;
        if (!empty($deadlineInput)) {
            try {
                $appTz = new DateTimeZone(config('app.timezone', 'Africa/Addis_Ababa'));
                $utcTz = new DateTimeZone('UTC');
                $dt = new DateTimeImmutable($deadlineInput, $appTz);
                $deadlineUtc = $dt->setTimezone($utcTz)->format('Y-m-d H:i:s');
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
                'dashboard',
                $deadlineUtc
            );

            flash('success', "Message #{$telegramMessageId} in '{$group['title']}' is now tracked!");
            redirect(url('messages/view?id=' . $result['tracked_message_id']));
        } catch (\Throwable $e) {
            flash('danger', 'Error tracking message: ' . $e->getMessage());
            redirect(url('dashboard'));
        }
    }

    /**
     * Real-time JSON API endpoint for message response details.
     */
    public function liveDetails(): void
    {
        AuthService::requireLogin();
        header('Content-Type: application/json');

        $messageId = (int)($_GET['id'] ?? 0);
        if (!AuthService::canAccessMessage($messageId)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'forbidden', 'message' => 'Access denied: You do not have permission to view conversations outside your department.']);
            exit;
        }

        $message = Database::fetch(
            "SELECT tm.*, tg.title AS group_title, tg.telegram_chat_id 
             FROM tracked_messages tm
             JOIN telegram_groups tg ON tm.group_id = tg.id
             WHERE tm.id = :id",
            [':id' => $messageId]
        );

        if (!$message) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'not_found']);
            exit;
        }

        $sql = "SELECT m.id AS member_id, 
                       m.telegram_user_id, 
                       m.full_name, 
                       m.username, 
                       gm.source AS member_source,
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

        $dashboardCtrl = new DashboardController();
        $stats = $dashboardCtrl->calculateMessageStats($messageId, (int)$message['group_id'], $message['message_date']);

        $msgTime = strtotime($message['message_date']);
        foreach ($members as &$m) {
            if (empty($m['status'])) {
                $m['status'] = ($m['member_source'] === 'imported') ? 'not_replied' : 'unknown';
            }
            $m['formatted_date'] = !empty($m['response_date']) ? format_tz($m['response_date']) : '—';
            $m['display_text'] = !empty($m['response_text']) ? e(mb_strimwidth($m['response_text'], 0, 140, '...')) : '—';

            $m['turnaround_seconds'] = null;
            $m['turnaround_formatted'] = '—';
            if ($m['status'] === 'replied' && !empty($m['response_date'])) {
                $respTime = strtotime($m['response_date']);
                $diff = max(0, $respTime - $msgTime);
                $m['turnaround_seconds'] = $diff;
                $m['turnaround_formatted'] = format_duration($diff);
            }

            $m['is_overdue'] = (!empty($stats['is_overdue']) && in_array($m['status'], ['not_replied', 'unknown']));
        }
        unset($m);

        echo json_encode([
            'ok' => true,
            'stats' => $stats,
            'members' => $members,
            'synced_at' => format_tz(utc_now(), 'h:i:s A'),
        ]);
        exit;
    }

    /**
     * Send an interactive reminder to non-repliers directly in the Telegram group.
     */
    public function sendReminder(): void
    {
        AuthService::requireCanTrack();
        AuthService::verifyCsrf();

        $messageId = (int)($_POST['message_id'] ?? 0);
        AuthService::requireMessageAccess($messageId);
        $customNote = trim($_POST['custom_note'] ?? '');

        $message = Database::fetch(
            "SELECT tm.*, tg.telegram_chat_id, tg.title AS group_title 
             FROM tracked_messages tm
             JOIN telegram_groups tg ON tm.group_id = tg.id
             WHERE tm.id = :id",
            [':id' => $messageId]
        );

        if (!$message) {
            flash('danger', 'Tracked message not found.');
            redirect(url('dashboard'));
        }

        // Fetch pending / unreplied members
        $sql = "SELECT m.full_name, m.username, m.telegram_user_id
                FROM group_members gm
                JOIN members m ON gm.member_id = m.id
                LEFT JOIN message_responses mr ON mr.tracked_message_id = :tmid AND mr.member_telegram_user_id = m.telegram_user_id
                WHERE gm.group_id = :gid 
                  AND gm.joined_at <= :mdate1 
                  AND (gm.left_at IS NULL OR gm.left_at > :mdate2)
                  AND m.is_bot = 0
                  AND m.telegram_user_id != :sender_id
                  AND (mr.status IS NULL OR mr.status = 'unknown')
                ORDER BY m.full_name ASC";

        $pendingMembers = Database::fetchAll($sql, [
            ':tmid' => $messageId,
            ':gid' => $message['group_id'],
            ':mdate1' => $message['message_date'],
            ':mdate2' => $message['message_date'],
            ':sender_id' => $message['telegram_user_id'],
        ]);

        if (empty($pendingMembers)) {
            flash('info', 'All eligible members have already replied! No reminder needed.');
            redirect(url('messages/view?id=' . $messageId));
        }

        $mentions = [];
        foreach ($pendingMembers as $pm) {
            if (!empty($pm['username'])) {
                $mentions[] = '@' . $pm['username'];
            } else {
                $mentions[] = $pm['full_name'];
            }
        }
        $mentionStr = implode(' ', $mentions);

        $text = "⏰ <b>Kaldi's Coffee Reminder: Response Required</b>\n\n";
        $text .= "Team members pending acknowledgment for this announcement:\n";
        $text .= "👉 " . e($mentionStr) . "\n\n";

        if (!empty($customNote)) {
            $text .= "📝 <b>Note:</b> " . e($customNote) . "\n\n";
        }

        $text .= "<i>Please reply directly to the message above to confirm.</i>";

        $tg = new \App\Services\TelegramService();
        $resp = $tg->sendMessage(
            $message['telegram_chat_id'],
            $text,
            (int)$message['telegram_message_id'],
            !empty($message['thread_id']) ? (int)$message['thread_id'] : null
        );

        if (!empty($resp['ok'])) {
            AuditService::log('telegram_reminder_sent', 'tracked_message', (string)$messageId, [
                'pending_count' => count($pendingMembers),
                'group_id' => $message['group_id'],
                'chat_id' => $message['telegram_chat_id'],
            ]);
            flash('success', "Reminder sent to " . count($pendingMembers) . " pending member(s) in Telegram!");
        } else {
            flash('danger', 'Failed to send Telegram reminder: ' . ($resp['description'] ?? 'Unknown error'));
        }

        redirect(url('messages/view?id=' . $messageId));
    }

    /**
     * Broadcast an official status report directly into the Telegram group.
     */
    public function sendSummaryReport(): void
    {
        AuthService::requireCanTrack();
        AuthService::verifyCsrf();

        $messageId = (int)($_POST['message_id'] ?? 0);
        AuthService::requireMessageAccess($messageId);

        $message = Database::fetch(
            "SELECT tm.*, tg.telegram_chat_id, tg.title AS group_title 
             FROM tracked_messages tm
             JOIN telegram_groups tg ON tm.group_id = tg.id
             WHERE tm.id = :id",
            [':id' => $messageId]
        );

        if (!$message) {
            flash('danger', 'Tracked message not found.');
            redirect(url('dashboard'));
        }

        $dashboardCtrl = new DashboardController();
        $stats = $dashboardCtrl->calculateMessageStats($messageId, (int)$message['group_id'], $message['message_date']);

        $excerpt = mb_strimwidth($message['message_text'], 0, 90, '...');

        $text = "📊 <b>Kaldi's Coffee &bull; Reply Status Report</b>\n\n";
        $text .= "<b>Group:</b> " . e($message['group_title']) . "\n";
        $text .= "<b>Notice:</b> <i>\"" . e($excerpt) . "\"</i>\n\n";
        $text .= "👥 <b>Eligible Roster:</b> {$stats['eligible']}\n";
        $text .= "✅ <b>Replied:</b> {$stats['replied']} (" . number_format($stats['percentage'], 1) . "%)\n";
        $text .= "⏳ <b>Pending:</b> {$stats['not_replied']}\n";

        if (!empty($stats['avg_turnaround_formatted']) && $stats['avg_turnaround_formatted'] !== '—') {
            $text .= "⚡ <b>Avg Turnaround:</b> {$stats['avg_turnaround_formatted']}\n";
        }
        if (!empty($stats['is_overdue'])) {
            $text .= "🔴 <b>Status:</b> SLA Overdue\n";
        }

        $text .= "\n<i>Generated from Kaldi's Management Portal</i>";

        $tg = new \App\Services\TelegramService();
        $resp = $tg->sendMessage(
            $message['telegram_chat_id'],
            $text,
            (int)$message['telegram_message_id'],
            !empty($message['thread_id']) ? (int)$message['thread_id'] : null
        );

        if (!empty($resp['ok'])) {
            AuditService::log('telegram_summary_posted', 'tracked_message', (string)$messageId, [
                'stats' => $stats,
                'chat_id' => $message['telegram_chat_id']
            ]);
            flash('success', 'Status summary posted directly to Telegram group!');
        } else {
            flash('danger', 'Failed to post summary to Telegram: ' . ($resp['description'] ?? 'Unknown error'));
        }

        redirect(url('messages/view?id=' . $messageId));
    }

    /**
     * Set or update message deadline / SLA.
     */
    public function setDeadline(): void
    {
        AuthService::requireCanTrack();
        AuthService::verifyCsrf();

        $messageId = (int)($_POST['message_id'] ?? 0);
        AuthService::requireMessageAccess($messageId);
        $preset = trim($_POST['preset'] ?? '');
        $customDate = trim($_POST['deadline_at'] ?? '');

        $message = Database::fetch("SELECT * FROM tracked_messages WHERE id = :id", [':id' => $messageId]);
        if (!$message) {
            flash('danger', 'Tracked message not found.');
            redirect(url('dashboard'));
        }

        $deadlineUtc = null;
        $msgUtc = new DateTimeImmutable($message['message_date'], new DateTimeZone('UTC'));

        if ($preset === '30m') {
            $deadlineUtc = $msgUtc->modify('+30 minutes')->format('Y-m-d H:i:s');
        } elseif ($preset === '1h') {
            $deadlineUtc = $msgUtc->modify('+1 hour')->format('Y-m-d H:i:s');
        } elseif ($preset === '2h') {
            $deadlineUtc = $msgUtc->modify('+2 hours')->format('Y-m-d H:i:s');
        } elseif ($preset === '4h') {
            $deadlineUtc = $msgUtc->modify('+4 hours')->format('Y-m-d H:i:s');
        } elseif ($preset === '24h') {
            $deadlineUtc = $msgUtc->modify('+24 hours')->format('Y-m-d H:i:s');
        } elseif (!empty($customDate)) {
            try {
                $appTz = new DateTimeZone(config('app.timezone', 'Africa/Addis_Ababa'));
                $utcTz = new DateTimeZone('UTC');
                $dt = new DateTimeImmutable($customDate, $appTz);
                $deadlineUtc = $dt->setTimezone($utcTz)->format('Y-m-d H:i:s');
            } catch (\Exception) {}
        }

        Database::execute(
            "UPDATE tracked_messages SET deadline_at = :dl, updated_at = :now WHERE id = :id",
            [':dl' => $deadlineUtc, ':now' => utc_now(), ':id' => $messageId]
        );

        AuditService::log('tracked_message_deadline_updated', 'tracked_message', (string)$messageId, [
            'deadline_at' => $deadlineUtc
        ]);

        if ($deadlineUtc) {
            flash('success', 'Deadline updated to ' . format_tz($deadlineUtc) . ' (EAT).');
        } else {
            flash('info', 'Deadline cleared.');
        }

        redirect(url('messages/view?id=' . $messageId));
    }

    /**
     * Confirm a pending review response.
     */
    public function confirmResponse(): void
    {
        AuthService::requireCanTrack();
        AuthService::verifyCsrf();

        $responseId = (int)($_POST['response_id'] ?? 0);
        $messageId = (int)($_POST['message_id'] ?? 0);
        AuthService::requireMessageAccess($messageId);

        $response = Database::fetch("SELECT * FROM message_responses WHERE id = :id", [':id' => $responseId]);
        if (!$response) {
            flash('danger', 'Response record not found.');
            redirect(url('messages/view?id=' . $messageId));
        }

        $adminId = $_SESSION['admin_user']['id'] ?? null;
        Database::execute(
            "UPDATE message_responses 
             SET status = 'replied', reviewed_by_admin_id = :aid, reviewed_at = :now, updated_at = :now
             WHERE id = :id",
            [':aid' => $adminId, ':now' => utc_now(), ':id' => $responseId]
        );

        AuditService::log('response_manually_confirmed', 'message_response', (string)$responseId, [
            'tracked_message_id' => $response['tracked_message_id'],
            'member_telegram_user_id' => $response['member_telegram_user_id'],
            'previous_status' => $response['status']
        ]);

        flash('success', 'Member response confirmed as Replied.');
        redirect(url('messages/view?id=' . $messageId));
    }

    /**
     * Reject a pending response.
     */
    public function rejectResponse(): void
    {
        AuthService::requireCanTrack();
        AuthService::verifyCsrf();

        $responseId = (int)($_POST['response_id'] ?? 0);
        $messageId = (int)($_POST['message_id'] ?? 0);
        AuthService::requireMessageAccess($messageId);

        $response = Database::fetch("SELECT * FROM message_responses WHERE id = :id", [':id' => $responseId]);
        if (!$response) {
            flash('danger', 'Response record not found.');
            redirect(url('messages/view?id=' . $messageId));
        }

        $adminId = $_SESSION['admin_user']['id'] ?? null;
        Database::execute(
            "UPDATE message_responses 
             SET status = 'rejected', reviewed_by_admin_id = :aid, reviewed_at = :now, updated_at = :now
             WHERE id = :id",
            [':aid' => $adminId, ':now' => utc_now(), ':id' => $responseId]
        );

        AuditService::log('response_rejected', 'message_response', (string)$responseId, [
            'tracked_message_id' => $response['tracked_message_id'],
            'member_telegram_user_id' => $response['member_telegram_user_id'],
            'previous_status' => $response['status']
        ]);

        flash('warning', 'Response marked as Rejected.');
        redirect(url('messages/view?id=' . $messageId));
    }

    /**
     * Exclude member for this message calculation only.
     */
    public function excludeMember(): void
    {
        AuthService::requireCanTrack();
        AuthService::verifyCsrf();

        $messageId = (int)($_POST['message_id'] ?? 0);
        AuthService::requireMessageAccess($messageId);
        $memberId = (int)($_POST['member_id'] ?? 0);
        $telegramUserId = (int)($_POST['telegram_user_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? 'Excluded by administrator');

        $adminId = $_SESSION['admin_user']['id'] ?? null;
        $now = utc_now();

        $existing = Database::fetch(
            "SELECT id FROM message_responses WHERE tracked_message_id = :tmid AND member_telegram_user_id = :uid",
            [':tmid' => $messageId, ':uid' => $telegramUserId]
        );

        if ($existing) {
            Database::execute(
                "UPDATE message_responses 
                 SET status = 'excluded', excluded_reason = :reason, reviewed_by_admin_id = :aid, reviewed_at = :now, updated_at = :now
                 WHERE id = :id",
                [':reason' => $reason, ':aid' => $adminId, ':now' => $now, ':id' => $existing['id']]
            );
        } else {
            Database::execute(
                "INSERT INTO message_responses 
                 (tracked_message_id, member_id, member_telegram_user_id, status, excluded_reason, reviewed_by_admin_id, reviewed_at, created_at, updated_at)
                 VALUES (:tmid, :mid, :uid, 'excluded', :reason, :aid, :now, :now, :now)",
                [
                    ':tmid' => $messageId,
                    ':mid' => $memberId,
                    ':uid' => $telegramUserId,
                    ':reason' => $reason,
                    ':aid' => $adminId,
                    ':now' => $now
                ]
            );
        }

        AuditService::log('member_excluded_from_tracked_message', 'tracked_message', (string)$messageId, [
            'member_telegram_user_id' => $telegramUserId,
            'reason' => $reason,
            'admin_id' => $adminId,
        ]);

        flash('info', 'Member excluded from this calculation.');
        redirect(url('messages/view?id=' . $messageId));
    }

    /**
     * Restore an excluded member.
     */
    public function includeMember(): void
    {
        AuthService::requireCanTrack();
        AuthService::verifyCsrf();

        $messageId = (int)($_POST['message_id'] ?? 0);
        AuthService::requireMessageAccess($messageId);
        $telegramUserId = (int)($_POST['telegram_user_id'] ?? 0);

        Database::execute(
            "DELETE FROM message_responses 
             WHERE tracked_message_id = :tmid AND member_telegram_user_id = :uid AND status = 'excluded'",
            [':tmid' => $messageId, ':uid' => $telegramUserId]
        );

        AuditService::log('member_reincluded_in_tracked_message', 'tracked_message', (string)$messageId, [
            'member_telegram_user_id' => $telegramUserId
        ]);

        flash('success', 'Member restored to active calculation.');
        redirect(url('messages/view?id=' . $messageId));
    }

    /**
     * Manually mark a member as replied (e.g. offline confirmation).
     */
    public function manualMarkReplied(): void
    {
        AuthService::requireCanTrack();
        AuthService::verifyCsrf();

        $messageId = (int)($_POST['message_id'] ?? 0);
        AuthService::requireMessageAccess($messageId);
        $memberId = (int)($_POST['member_id'] ?? 0);
        $telegramUserId = (int)($_POST['telegram_user_id'] ?? 0);
        $note = trim($_POST['note'] ?? 'Confirmed verbally / offline');

        $adminId = $_SESSION['admin_user']['id'] ?? null;
        $now = utc_now();

        $existing = Database::fetch(
            "SELECT id FROM message_responses WHERE tracked_message_id = :tmid AND member_telegram_user_id = :uid",
            [':tmid' => $messageId, ':uid' => $telegramUserId]
        );

        if ($existing) {
            Database::execute(
                "UPDATE message_responses 
                 SET status = 'replied', matching_method = 'manual', response_text = :text, response_date = :now,
                     reviewed_by_admin_id = :aid, reviewed_at = :now, updated_at = :now
                 WHERE id = :id",
                [':text' => $note, ':aid' => $adminId, ':now' => $now, ':id' => $existing['id']]
            );
        } else {
            Database::execute(
                "INSERT INTO message_responses 
                 (tracked_message_id, member_id, member_telegram_user_id, status, matching_method, response_text, response_date, reviewed_by_admin_id, reviewed_at, created_at, updated_at)
                 VALUES (:tmid, :mid, :uid, 'replied', 'manual', :text, :now, :aid, :now, :now, :now)",
                [
                    ':tmid' => $messageId,
                    ':mid' => $memberId,
                    ':uid' => $telegramUserId,
                    ':text' => $note,
                    ':aid' => $adminId,
                    ':now' => $now
                ]
            );
        }

        AuditService::log('member_manually_marked_replied', 'tracked_message', (string)$messageId, [
            'member_telegram_user_id' => $telegramUserId,
            'note' => $note
        ]);

        flash('success', 'Member marked as Replied manually.');
        redirect(url('messages/view?id=' . $messageId));
    }

    /**
     * Export message responses as CSV.
     */
    public function exportCsv(): void
    {
        AuthService::requireLogin();

        $messageId = (int)($_GET['id'] ?? 0);
        AuthService::requireMessageAccess($messageId);

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

        $sql = "SELECT m.full_name, m.telegram_user_id, m.username, gm.source AS member_source,
                       mr.status, mr.matching_method, mr.response_text, mr.response_date, mr.excluded_reason
                FROM group_members gm
                JOIN members m ON gm.member_id = m.id
                LEFT JOIN message_responses mr ON mr.tracked_message_id = :tmid AND mr.member_telegram_user_id = m.telegram_user_id
                WHERE gm.group_id = :gid 
                  AND gm.joined_at <= :mdate1 
                  AND (gm.left_at IS NULL OR gm.left_at > :mdate2)
                  AND m.is_bot = 0
                  AND m.telegram_user_id != :sender_id
                ORDER BY m.full_name ASC";

        $members = Database::fetchAll($sql, [
            ':tmid' => $messageId,
            ':gid' => $message['group_id'],
            ':mdate1' => $message['message_date'],
            ':mdate2' => $message['message_date'],
            ':sender_id' => $message['telegram_user_id'],
        ]);

        $filename = 'kaldis_replies_msg_' . $messageId . '_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Full Name', 'Telegram User ID', 'Username', 'Status', 'Turnaround Time', 'Matching Method', 'Reply Text', 'Reply Date (Addis Ababa)', 'Reason / Notes']);

        $msgTime = strtotime($message['message_date']);

        foreach ($members as $m) {
            $status = $m['status'];
            if (empty($status)) {
                $status = ($m['member_source'] === 'imported') ? 'not_replied' : 'unknown';
            }

            $turnaround = '—';
            if ($status === 'replied' && !empty($m['response_date'])) {
                $turnaround = format_duration(max(0, strtotime($m['response_date']) - $msgTime));
            }

            fputcsv($output, [
                $m['full_name'],
                $m['telegram_user_id'],
                !empty($m['username']) ? '@' . $m['username'] : '',
                ucfirst(str_replace('_', ' ', $status)),
                $turnaround,
                !empty($m['matching_method']) ? ucfirst(str_replace('_', ' ', $m['matching_method'])) : '',
                $m['response_text'] ?? '',
                !empty($m['response_date']) ? format_tz($m['response_date']) : '',
                $m['excluded_reason'] ?? ''
            ]);
        }
        fclose($output);
        exit;
    }
}
