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

        $assignedGroup = AuthService::assignedGroupId();
        $userDept = AuthService::userDepartment();
        $isGroupAuthorizer = AuthService::isGroupAuthorizer();

        // 1. Group / Department Scoping
        $groupParams = [];
        $groupWhere = "WHERE is_active = 1";
        if ($assignedGroup !== null) {
            $groupWhere .= " AND id = :agid";
            $groupParams[':agid'] = $assignedGroup;
        } elseif ($userDept !== null) {
            $groupWhere .= " AND department = :udept";
            $groupParams[':udept'] = $userDept;
        }

        $groups = Database::fetchAll(
            "SELECT id, telegram_chat_id, title, department, bot_status, bot_is_admin, last_update_at 
             FROM telegram_groups {$groupWhere} 
             ORDER BY title ASC",
            $groupParams
        );

        $accessibleGroupIds = array_column($groups, 'id');

        // If user has no accessible groups
        if (empty($accessibleGroupIds)) {
            view('dashboard.index', [
                'kpis' => [
                    'total_groups' => 0,
                    'total_members' => 0,
                    'total_messages' => 0,
                    'overall_compliance_rate' => 0,
                    'pending_staff' => 0,
                    'overdue_count' => 0,
                ],
                'groups' => [],
                'groupPerformance' => [],
                'departmentStats' => [],
                'recentMessages' => [],
                'userDept' => $userDept,
                'assignedGroupId' => $assignedGroup,
                'isGroupAuthorizer' => $isGroupAuthorizer,
                'canTrack' => AuthService::canTrack(),
            ]);
            return;
        }

        $inGroupPlaceholders = implode(',', array_fill(0, count($accessibleGroupIds), '?'));

        // 2. Total active members across accessible groups
        $totalMembers = (int)Database::fetchColumn(
            "SELECT COUNT(DISTINCT member_id) FROM group_members WHERE group_id IN ({$inGroupPlaceholders}) AND is_active = 1",
            $accessibleGroupIds
        );

        // 3. Tracked messages across accessible groups
        $allMessages = Database::fetchAll(
            "SELECT tm.id, tm.group_id, tm.message_date, tm.deadline_at 
             FROM tracked_messages tm 
             WHERE tm.group_id IN ({$inGroupPlaceholders}) AND tm.is_active = 1 
             ORDER BY tm.message_date DESC",
            $accessibleGroupIds
        );

        $totalMessages = count($allMessages);
        $totalReplies = 0;
        $totalEligible = 0;
        $totalPending = 0;
        $overdueCount = 0;

        // Group-level aggregation
        $groupStatsMap = [];
        foreach ($accessibleGroupIds as $gid) {
            $groupStatsMap[$gid] = [
                'message_count' => 0,
                'total_replies' => 0,
                'total_eligible' => 0,
                'pending' => 0,
                'overdue' => 0,
            ];
        }

        foreach ($allMessages as $msg) {
            $st = $this->calculateMessageStats((int)$msg['id'], (int)$msg['group_id'], $msg['message_date']);
            $totalReplies += $st['replied'];
            $totalEligible += $st['eligible'];
            $totalPending += $st['not_replied'];
            if (!empty($st['is_overdue'])) {
                $overdueCount++;
            }

            $gid = (int)$msg['group_id'];
            if (isset($groupStatsMap[$gid])) {
                $groupStatsMap[$gid]['message_count']++;
                $groupStatsMap[$gid]['total_replies'] += $st['replied'];
                $groupStatsMap[$gid]['total_eligible'] += $st['eligible'];
                $groupStatsMap[$gid]['pending'] += $st['not_replied'];
                if (!empty($st['is_overdue'])) {
                    $groupStatsMap[$gid]['overdue']++;
                }
            }
        }

        $overallComplianceRate = ($totalEligible > 0) ? round(($totalReplies / $totalEligible) * 100, 1) : 0;

        // 4. Build Group Performance Matrix
        $groupPerformance = [];
        $deptAggregation = [];

        foreach ($groups as $g) {
            $gid = (int)$g['id'];
            $rosterCount = (int)Database::fetchColumn(
                "SELECT COUNT(*) FROM group_members WHERE group_id = :gid AND is_active = 1",
                [':gid' => $gid]
            );

            $gStats = $groupStatsMap[$gid] ?? ['message_count' => 0, 'total_replies' => 0, 'total_eligible' => 0, 'pending' => 0, 'overdue' => 0];
            $gRate = ($gStats['total_eligible'] > 0) ? round(($gStats['total_replies'] / $gStats['total_eligible']) * 100, 1) : 0;

            $groupPerformance[] = [
                'id' => $gid,
                'title' => $g['title'],
                'telegram_chat_id' => $g['telegram_chat_id'],
                'department' => $g['department'] ?? 'General',
                'bot_status' => $g['bot_status'],
                'bot_is_admin' => $g['bot_is_admin'],
                'roster_count' => $rosterCount,
                'message_count' => $gStats['message_count'],
                'compliance_rate' => $gRate,
                'pending_count' => $gStats['pending'],
                'overdue_count' => $gStats['overdue'],
                'last_update_at' => $g['last_update_at'],
            ];

            // Department aggregation
            $deptName = $g['department'] ?: 'General';
            if (!isset($deptAggregation[$deptName])) {
                $deptAggregation[$deptName] = [
                    'department' => $deptName,
                    'group_count' => 0,
                    'member_count' => 0,
                    'message_count' => 0,
                    'total_replies' => 0,
                    'total_eligible' => 0,
                ];
            }
            $deptAggregation[$deptName]['group_count']++;
            $deptAggregation[$deptName]['member_count'] += $rosterCount;
            $deptAggregation[$deptName]['message_count'] += $gStats['message_count'];
            $deptAggregation[$deptName]['total_replies'] += $gStats['total_replies'];
            $deptAggregation[$deptName]['total_eligible'] += $gStats['total_eligible'];
        }

        foreach ($deptAggregation as &$d) {
            $d['compliance_rate'] = ($d['total_eligible'] > 0) ? round(($d['total_replies'] / $d['total_eligible']) * 100, 1) : 0;
        }
        unset($d);

        // 5. Recent Announcements (Latest 5)
        $recentMessagesRaw = Database::fetchAll(
            "SELECT tm.*, tg.title AS group_title, tg.telegram_chat_id, tg.department 
             FROM tracked_messages tm 
             JOIN telegram_groups tg ON tm.group_id = tg.id 
             WHERE tm.group_id IN ({$inGroupPlaceholders}) AND tm.is_active = 1 
             ORDER BY tm.message_date DESC 
             LIMIT 5",
            $accessibleGroupIds
        );

        $recentMessages = [];
        foreach ($recentMessagesRaw as $rm) {
            $rm['stats'] = $this->calculateMessageStats((int)$rm['id'], (int)$rm['group_id'], $rm['message_date']);
            $recentMessages[] = $rm;
        }

        view('dashboard.index', [
            'kpis' => [
                'total_groups' => count($groups),
                'total_members' => $totalMembers,
                'total_messages' => $totalMessages,
                'overall_compliance_rate' => $overallComplianceRate,
                'pending_staff' => $totalPending,
                'overdue_count' => $overdueCount,
            ],
            'groups' => $groups,
            'groupPerformance' => $groupPerformance,
            'departmentStats' => array_values($deptAggregation),
            'recentMessages' => $recentMessages,
            'userDept' => $userDept,
            'assignedGroupId' => $assignedGroup,
            'isGroupAuthorizer' => $isGroupAuthorizer,
            'canTrack' => AuthService::canTrack(),
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

        // Turnaround metrics for replied members
        $responseTimes = Database::fetchAll(
            "SELECT TIMESTAMPDIFF(SECOND, :mdate, response_date) AS diff_seconds 
             FROM message_responses 
             WHERE tracked_message_id = :tmid AND status = 'replied' AND response_date IS NOT NULL",
            [':mdate' => $messageDate, ':tmid' => $trackedMessageId]
        );

        $avgTurnaroundSeconds = null;
        $fastestTurnaroundSeconds = null;
        if (!empty($responseTimes)) {
            $totalSeconds = 0;
            foreach ($responseTimes as $rt) {
                $sec = max(0, (int)$rt['diff_seconds']);
                $totalSeconds += $sec;
                if ($fastestTurnaroundSeconds === null || $sec < $fastestTurnaroundSeconds) {
                    $fastestTurnaroundSeconds = $sec;
                }
            }
            $avgTurnaroundSeconds = (int)round($totalSeconds / count($responseTimes));
        }

        // Deadline and Overdue calculation
        $deadlineRow = Database::fetch("SELECT deadline_at FROM tracked_messages WHERE id = :id", [':id' => $trackedMessageId]);
        $deadlineAt = $deadlineRow['deadline_at'] ?? null;
        $isOverdue = false;
        $deadlineDiff = null;

        if (!empty($deadlineAt)) {
            $deadlineTime = strtotime($deadlineAt . ' UTC');
            $nowTime = time();
            $deadlineDiff = $deadlineTime - $nowTime;
            if ($deadlineDiff < 0 && $notReplied > 0) {
                $isOverdue = true;
            }
        }

        return [
            'eligible' => $eligibleMembersCount,
            'replied' => $replied,
            'not_replied' => $notReplied,
            'pending_review' => $pendingReview,
            'unknown' => $unknown,
            'excluded' => $excluded,
            'percentage' => $percentage,
            'denominator' => max(0, $denominator),
            'avg_turnaround_seconds' => $avgTurnaroundSeconds,
            'avg_turnaround_formatted' => format_duration($avgTurnaroundSeconds),
            'fastest_turnaround_seconds' => $fastestTurnaroundSeconds,
            'fastest_turnaround_formatted' => format_duration($fastestTurnaroundSeconds),
            'deadline_at' => $deadlineAt,
            'is_overdue' => $isOverdue,
            'deadline_diff_seconds' => $deadlineDiff,
        ];
    }

    /**
     * Compute aggregated summary cards respecting active filters.
     */
    public function calculateFilteredSummaryStats(string $whereSql, array $params): array
    {
        $sql = "SELECT tm.id, tm.group_id, tm.message_date 
                FROM tracked_messages tm 
                JOIN telegram_groups tg ON tm.group_id = tg.id 
                WHERE {$whereSql}";
        
        $messages = Database::fetchAll($sql, $params);
        $totalMessages = count($messages);

        if ($totalMessages === 0) {
            return [
                'total_messages' => 0,
                'messages_with_replies' => 0,
                'messages_without_replies' => 0,
                'total_pending_members' => 0,
                'overdue_messages' => 0,
            ];
        }

        $withReplies = 0;
        $withoutReplies = 0;
        $totalPendingMembers = 0;
        $overdueMessages = 0;

        foreach ($messages as $msg) {
            $stats = $this->calculateMessageStats((int)$msg['id'], (int)$msg['group_id'], $msg['message_date']);
            if ($stats['replied'] > 0) {
                $withReplies++;
            } else {
                $withoutReplies++;
            }
            $totalPendingMembers += $stats['not_replied'];
            if (!empty($stats['is_overdue'])) {
                $overdueMessages++;
            }
        }

        return [
            'total_messages' => $totalMessages,
            'messages_with_replies' => $withReplies,
            'messages_without_replies' => $withoutReplies,
            'total_pending_members' => $totalPendingMembers,
            'overdue_messages' => $overdueMessages,
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

        // Apply Department / Group Isolation in API
        $assignedGroup = AuthService::assignedGroupId();
        $userDept = AuthService::userDepartment();
        if ($assignedGroup !== null) {
            $whereClauses[] = "tm.group_id = :assigned_gid";
            $params[':assigned_gid'] = $assignedGroup;
        } elseif ($userDept !== null) {
            $whereClauses[] = "tg.department = :user_dept";
            $params[':user_dept'] = $userDept;
        } elseif (!empty($_GET['department'])) {
            $whereClauses[] = "tg.department = :req_dept";
            $params[':req_dept'] = trim($_GET['department']);
        }

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

        // Also fetch live stats for visible message IDs (enforcing department isolation)
        $msgIds = !empty($_GET['message_ids']) ? array_filter(array_map('intval', explode(',', $_GET['message_ids']))) : [];
        $messageStats = [];
        if (!empty($msgIds)) {
            $placeholders = implode(',', array_fill(0, count($msgIds), '?'));
            $sql = "SELECT tm.id, tm.group_id, tm.message_date 
                    FROM tracked_messages tm 
                    JOIN telegram_groups tg ON tm.group_id = tg.id 
                    WHERE tm.id IN ({$placeholders})";
            if ($assignedGroup !== null) {
                $sql .= " AND tm.group_id = " . (int)$assignedGroup;
            } elseif ($userDept !== null) {
                $sql .= " AND tg.department = " . Database::pdo()->quote($userDept);
            }
            $rows = Database::fetchAll($sql, $msgIds);
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
