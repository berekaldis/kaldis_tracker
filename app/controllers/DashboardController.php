<?php
namespace App\Controllers;

use App\Services\AuthService;
use App\Services\Database;
use DateTimeImmutable;
use DateTimeZone;

class DashboardController
{
    public function index(): void
    {
        AuthService::requireLogin();

        // Retrieve and sanitize filters
        $search = trim($_GET['search'] ?? '');
        $groupId = !empty($_GET['group_id']) ? (int)$_GET['group_id'] : null;
        $dateFrom = trim($_GET['date_from'] ?? '');
        $dateTo = trim($_GET['date_to'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 15;
        $offset = ($page - 1) * $perPage;

        // Build filter WHERE clauses
        $whereClauses = ["tm.is_active = 1"];
        $params = [];

        if (!empty($search)) {
            $whereClauses[] = "(tm.message_text LIKE :search OR tm.reference_code LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        if (!empty($groupId)) {
            $whereClauses[] = "tm.group_id = :gid";
            $params[':gid'] = $groupId;
        }

        $appTz = new DateTimeZone(config('app.timezone', 'Africa/Addis_Ababa'));
        $utcTz = new DateTimeZone('UTC');

        if (!empty($dateFrom)) {
            try {
                $dtFrom = new DateTimeImmutable($dateFrom . ' 00:00:00', $appTz);
                $whereClauses[] = "tm.message_date >= :date_from";
                $params[':date_from'] = $dtFrom->setTimezone($utcTz)->format('Y-m-d H:i:s');
            } catch (\Exception) {}
        }

        if (!empty($dateTo)) {
            try {
                $dtTo = new DateTimeImmutable($dateTo . ' 23:59:59', $appTz);
                $whereClauses[] = "tm.message_date <= :date_to";
                $params[':date_to'] = $dtTo->setTimezone($utcTz)->format('Y-m-d H:i:s');
            } catch (\Exception) {}
        }

        $whereSql = implode(' AND ', $whereClauses);

        // Fetch groups for filter dropdown
        $groups = Database::fetchAll("SELECT id, title, telegram_chat_id FROM telegram_groups ORDER BY title ASC");

        // Count total filtered messages
        $totalMessagesCount = (int)Database::fetchColumn(
            "SELECT COUNT(*) FROM tracked_messages tm WHERE {$whereSql}",
            $params
        );

        // Fetch paginated messages with group title
        $messagesSql = "SELECT tm.*, tg.title AS group_title, tg.telegram_chat_id 
                        FROM tracked_messages tm
                        JOIN telegram_groups tg ON tm.group_id = tg.id
                        WHERE {$whereSql}
                        ORDER BY tm.message_date DESC
                        LIMIT {$perPage} OFFSET {$offset}";

        $trackedMessages = Database::fetchAll($messagesSql, $params);

        // Calculate member response stats for each message
        $messagesWithRepliesCount = 0;
        $messagesWithoutRepliesCount = 0;
        $totalPendingMembersCount = 0;

        $messageStats = [];
        foreach ($trackedMessages as &$msg) {
            $stats = $this->calculateMessageStats((int)$msg['id'], (int)$msg['group_id'], $msg['message_date']);
            $msg['stats'] = $stats;
            $messageStats[$msg['id']] = $stats;
        }
        unset($msg);

        // For summary cards respecting active filters across ALL matching messages:
        $summaryCardStats = $this->calculateFilteredSummaryStats($whereSql, $params);

        $totalPages = ceil($totalMessagesCount / $perPage);

        view('dashboard.index', [
            'messages' => $trackedMessages,
            'groups' => $groups,
            'summary' => $summaryCardStats,
            'trackingMode' => (string)config('telegram.tracking_mode', 'all_authorized'),
            'filters' => [
                'search' => $search,
                'group_id' => $groupId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'page' => $page,
                'total_pages' => $totalPages,
                'total_records' => $totalMessagesCount,
            ]
        ]);
    }

    /**
     * "About Monitoring" limitation page per Section 3.2.
     */
    public function about(): void
    {
        AuthService::requireLogin();
        view('dashboard.about');
    }

    /**
     * Calculate response breakdown for a single tracked message.
     */
    public function calculateMessageStats(int $trackedMessageId, int $groupId, string $messageDate): array
    {
        // 1. Eligible members: on roster active in group at message_date (excluding the message author)
        $eligibleMembersCount = (int)Database::fetchColumn(
            "SELECT COUNT(DISTINCT gm.member_id) 
             FROM group_members gm
             JOIN members m ON gm.member_id = m.id
             WHERE gm.group_id = :gid 
               AND gm.joined_at <= :mdate1 
               AND (gm.left_at IS NULL OR gm.left_at > :mdate2)
               AND m.is_bot = 0
               AND m.telegram_user_id != (SELECT tm.telegram_user_id FROM tracked_messages tm WHERE tm.id = :tmid)",
            [':gid' => $groupId, ':mdate1' => $messageDate, ':mdate2' => $messageDate, ':tmid' => $trackedMessageId]
        );

        // 2. Count responses by status
        $statusCounts = Database::fetchAll(
            "SELECT status, COUNT(*) as cnt 
             FROM message_responses 
             WHERE tracked_message_id = :tmid 
             GROUP BY status",
            [':tmid' => $trackedMessageId]
        );

        $replied = 0;
        $pendingReview = 0;
        $unknown = 0;
        $excluded = 0;
        $rejected = 0;

        foreach ($statusCounts as $row) {
            match ($row['status']) {
                'replied' => $replied = (int)$row['cnt'],
                'pending_review' => $pendingReview = (int)$row['cnt'],
                'unknown' => $unknown = (int)$row['cnt'],
                'excluded' => $excluded = (int)$row['cnt'],
                'rejected' => $rejected = (int)$row['cnt'],
                default => null
            };
        }

        // If roster members exist who haven't responded yet and aren't in message_responses,
        // they are unresponded roster members
        $notReplied = max(0, $eligibleMembersCount - $replied - $pendingReview - $unknown - $excluded);

        // Response percentage = Replied / (Eligible - Excluded - Unknown)
        $denominator = $eligibleMembersCount - $excluded - $unknown;
        $percentage = ($denominator > 0) ? (float)round(($replied / $denominator) * 100, 1) : 0.0;

        return [
            'eligible' => $eligibleMembersCount,
            'replied' => $replied,
            'not_replied' => $notReplied,
            'pending_review' => $pendingReview,
            'unknown' => $unknown,
            'excluded' => $excluded,
            'percentage' => $percentage,
            'denominator' => max(0, $denominator),
        ];
    }

    /**
     * Compute aggregated summary cards respecting active filters.
     */
    private function calculateFilteredSummaryStats(string $whereSql, array $params): array
    {
        $sql = "SELECT tm.id, tm.group_id, tm.message_date 
                FROM tracked_messages tm 
                WHERE {$whereSql}";
        
        $messages = Database::fetchAll($sql, $params);
        $totalMessages = count($messages);

        if ($totalMessages === 0) {
            return [
                'total_messages' => 0,
                'messages_with_replies' => 0,
                'messages_without_replies' => 0,
                'total_pending_members' => 0,
            ];
        }

        $withReplies = 0;
        $withoutReplies = 0;
        $totalPendingMembers = 0;

        foreach ($messages as $msg) {
            $stats = $this->calculateMessageStats((int)$msg['id'], (int)$msg['group_id'], $msg['message_date']);
            if ($stats['replied'] > 0) {
                $withReplies++;
            } else {
                $withoutReplies++;
            }
            $totalPendingMembers += $stats['not_replied'];
        }

        return [
            'total_messages' => $totalMessages,
            'messages_with_replies' => $withReplies,
            'messages_without_replies' => $withoutReplies,
            'total_pending_members' => $totalPendingMembers,
        ];
    }

    /**
     * Real-time JSON API endpoint for dashboard summary & message stats.
     */
    public function liveStats(): void
    {
        AuthService::requireLogin();
        header('Content-Type: application/json');

        $search = trim($_GET['search'] ?? '');
        $groupId = !empty($_GET['group_id']) ? (int)$_GET['group_id'] : null;
        $dateFrom = trim($_GET['date_from'] ?? '');
        $dateTo = trim($_GET['date_to'] ?? '');

        $whereClauses = ["tm.is_active = 1"];
        $params = [];

        if (!empty($search)) {
            $whereClauses[] = "(tm.message_text LIKE :search OR tm.reference_code LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        if (!empty($groupId)) {
            $whereClauses[] = "tm.group_id = :gid";
            $params[':gid'] = $groupId;
        }

        $appTz = new DateTimeZone(config('app.timezone', 'Africa/Addis_Ababa'));
        $utcTz = new DateTimeZone('UTC');

        if (!empty($dateFrom)) {
            try {
                $dtFrom = new DateTimeImmutable($dateFrom . ' 00:00:00', $appTz);
                $whereClauses[] = "tm.message_date >= :date_from";
                $params[':date_from'] = $dtFrom->setTimezone($utcTz)->format('Y-m-d H:i:s');
            } catch (\Exception) {}
        }

        if (!empty($dateTo)) {
            try {
                $dtTo = new DateTimeImmutable($dateTo . ' 23:59:59', $appTz);
                $whereClauses[] = "tm.message_date <= :date_to";
                $params[':date_to'] = $dtTo->setTimezone($utcTz)->format('Y-m-d H:i:s');
            } catch (\Exception) {}
        }

        $whereSql = implode(' AND ', $whereClauses);
        $summary = $this->calculateFilteredSummaryStats($whereSql, $params);

        // Also fetch live stats for visible message IDs
        $msgIds = !empty($_GET['message_ids']) ? array_filter(array_map('intval', explode(',', $_GET['message_ids']))) : [];
        $messageStats = [];
        if (!empty($msgIds)) {
            $placeholders = implode(',', array_fill(0, count($msgIds), '?'));
            $rows = Database::fetchAll("SELECT id, group_id, message_date FROM tracked_messages WHERE id IN ({$placeholders})", $msgIds);
            foreach ($rows as $r) {
                $messageStats[$r['id']] = $this->calculateMessageStats((int)$r['id'], (int)$r['group_id'], $r['message_date']);
            }
        }

        echo json_encode([
            'ok' => true,
            'summary' => $summary,
            'message_stats' => $messageStats,
            'synced_at' => format_tz(utc_now(), 'h:i:s A'),
        ]);
        exit;
    }
}
