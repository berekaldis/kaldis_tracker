<?php
use App\Services\AuthService;
$user = AuthService::user();
$userName = $user['username'] ?? 'User';
$isOwner = AuthService::isOwner();
$canTrack = AuthService::canTrack();
$assignedGroupId = AuthService::assignedGroupId();
$userDept = AuthService::userDepartment();
?>
<!-- Page Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
    <div>
        <h3 class="fw-bold mb-1 text-dark">
            <i class="bi bi-chat-quote-fill me-2" style="color: var(--kaldis-primary);"></i>Tracked Group Messages
        </h3>
        <p class="text-muted small mb-0">
            Real-time announcement log, staff reply status, and SLA compliance monitoring.
            <?php if (!empty($userDept)): ?>
                &bull; <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= e($userDept) ?> Department</span>
            <?php endif; ?>
            <?php if (!empty($assignedGroupId)): ?>
                &bull; <span class="badge bg-success-subtle text-success border border-success-subtle">Assigned Group #<?= (int)$assignedGroupId ?></span>
            <?php endif; ?>
        </p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <div class="live-status-pill shadow-sm" title="Real-time live sync active">
            <span class="pulse-dot"></span>
            <span id="dashSyncTime">Live</span>
        </div>
        <?php if ($canTrack): ?>
            <button type="button" class="btn btn-kaldis btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#modalManualTrack">
                <i class="bi bi-pin-angle me-1"></i> Track Message
            </button>
        <?php endif; ?>
        <a href="<?= url('groups') ?>" class="btn btn-outline-secondary btn-sm shadow-sm">
            <i class="bi bi-people me-1"></i> Groups
        </a>
    </div>
</div>

<!-- 4 Key Summary KPI Cards for Filtered Messages -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card card-summary p-3 h-100 shadow-sm">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-label">Filtered Messages</div>
                    <div class="stat-value text-dark" id="dashTotalMessages"><?= number_format($summary['total_messages']) ?></div>
                </div>
                <div class="stat-icon total">
                    <i class="bi bi-chat-left-text"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-6 col-lg-3">
        <div class="card card-summary p-3 h-100 shadow-sm">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-label">With Replies</div>
                    <div class="stat-value text-success" id="dashWithReplies"><?= number_format($summary['messages_with_replies']) ?></div>
                </div>
                <div class="stat-icon replied">
                    <i class="bi bi-check2-circle"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-6 col-lg-3">
        <div class="card card-summary p-3 h-100 shadow-sm">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-label">Pending Staff</div>
                    <div class="stat-value text-warning" id="dashPendingMembers"><?= number_format($summary['total_pending_members']) ?></div>
                </div>
                <div class="stat-icon pending">
                    <i class="bi bi-hourglass-split"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-6 col-lg-3">
        <div class="card card-summary p-3 h-100 shadow-sm">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-label">SLA Overdue</div>
                    <div class="stat-value text-danger" id="dashOverdue"><?= number_format($summary['overdue_messages'] ?? 0) ?></div>
                </div>
                <div class="stat-icon unreplied">
                    <i class="bi bi-exclamation-octagon"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Search & Filter Bar -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="<?= url('messages') ?>" class="row g-2 align-items-center">
            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" value="<?= e($filters['search'] ?? '') ?>" placeholder="Search message text or #ref...">
                </div>
            </div>

            <div class="col-md-3">
                <select name="group_id" class="form-select form-select-sm">
                    <option value="">All Accessible Groups</option>
                    <?php foreach ($groups as $g): ?>
                        <option value="<?= $g['id'] ?>" <?= ($filters['group_id'] ?? '') == $g['id'] ? 'selected' : '' ?>>
                            <?= e($g['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if (empty($userDept) && count($departments ?? []) > 1): ?>
                <div class="col-md-2">
                    <select name="department" class="form-select form-select-sm">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= e($d) ?>" <?= ($selectedDepartment === $d) ? 'selected' : '' ?>>
                                <?= e($d) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="col-md-2">
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($filters['date_from'] ?? '') ?>" title="From Date (EAT)" placeholder="From Date">
            </div>

            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-dark flex-grow-1">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
                <?php if (!empty($filters['search']) || !empty($filters['group_id']) || !empty($filters['date_from']) || !empty($filters['date_to']) || (!empty($selectedDepartment) && empty($userDept))): ?>
                    <a href="<?= url('messages') ?>" class="btn btn-sm btn-outline-secondary" title="Reset Filters">
                        <i class="bi bi-x-lg"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Tracked Messages Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <div>
            <h5 class="card-title mb-0 fw-bold" style="color: var(--kaldis-primary);">
                <i class="bi bi-list-check me-2"></i> All Monitored Announcements
            </h5>
            <small class="text-muted">Filtered by current criteria &bull; Showing 15 records per page</small>
        </div>
        <span class="badge bg-light text-secondary border font-monospace">
            <?= number_format($filters['total_records']) ?> Total Messages
        </span>
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-kaldis align-middle mb-0">
            <thead>
                <tr>
                    <th style="min-width: 140px;">Date &amp; Time (EAT)</th>
                    <th>Group / Dept</th>
                    <th style="min-width: 260px;">Tracked Announcement</th>
                    <th class="text-center" style="min-width: 190px;">Replies / Roster</th>
                    <th class="text-center" style="min-width: 140px;">Response Rate</th>
                    <th class="text-center" style="min-width: 120px;">SLA Status</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($messages)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                            <div class="fw-semibold">No tracked messages found matching your criteria.</div>
                            <small class="text-muted">Post a message in any monitored Telegram group to begin tracking automatically, or use the "Track Message" button above.</small>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($messages as $msg): ?>
                        <?php 
                            $stats = $msg['stats']; 
                            $pct = $stats['percentage'];
                            $pctColor = $pct >= 80 ? 'success' : ($pct >= 40 ? 'warning' : 'danger');
                            $isOverdue = !empty($stats['is_overdue']);
                        ?>
                        <tr id="dashRow_<?= $msg['id'] ?>" data-msg-id="<?= $msg['id'] ?>">
                            <td>
                                <div class="fw-semibold small"><?= format_tz($msg['message_date'], 'M j, Y') ?></div>
                                <div class="text-muted small"><?= format_tz($msg['message_date'], 'h:i A') ?></div>
                            </td>
                            <td>
                                <div class="fw-semibold text-truncate" style="max-width: 180px;" title="<?= e($msg['group_title']) ?>">
                                    <?= e($msg['group_title']) ?>
                                </div>
                                <div class="d-flex align-items-center gap-1 mt-0.5">
                                    <span class="badge bg-light text-secondary border font-monospace" style="font-size: 0.65rem;">
                                        <?= e($msg['telegram_chat_id']) ?>
                                    </span>
                                    <?php if (!empty($msg['department'])): ?>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size: 0.65rem;">
                                            <?= e($msg['department']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($msg['reference_code'])): ?>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle me-1">
                                        <?= e($msg['reference_code']) ?>
                                    </span>
                                <?php endif; ?>
                                <span class="small text-dark">
                                    <?= e(mb_strimwidth($msg['message_text'], 0, 110, '...')) ?>
                                </span>
                                <?php if (!empty($msg['edited_at'])): ?>
                                    <span class="badge bg-secondary-subtle text-secondary ms-1" title="Edited on <?= format_tz($msg['edited_at']) ?>">
                                        <i class="bi bi-pencil-square"></i>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="d-inline-flex align-items-center gap-1">
                                    <span class="badge badge-status-replied rounded-pill px-2 py-0.5" id="dashReplied_<?= $msg['id'] ?>" title="Replied">
                                        <?= $stats['replied'] ?>
                                    </span>
                                    <span class="text-muted small">/</span>
                                    <span class="fw-semibold text-secondary small" id="dashEligible_<?= $msg['id'] ?>" title="Eligible Roster">
                                        <?= $stats['eligible'] ?>
                                    </span>
                                    <span class="badge badge-status-not_replied rounded-pill px-2 py-0.5 ms-1" id="dashNotReplied_<?= $msg['id'] ?>" title="Pending Reply" style="font-size: 0.72rem;">
                                        <?= $stats['not_replied'] ?> pending
                                    </span>
                                    <span id="dashUnknown_<?= $msg['id'] ?>" class="d-none">
                                        <?= $stats['pending_review'] + $stats['unknown'] ?>
                                    </span>
                                </div>
                            </td>
                            <td class="text-center">
                                <div class="d-flex align-items-center justify-content-center gap-2">
                                    <div class="progress flex-grow-1" style="height: 6px; width: 60px;">
                                        <div class="progress-bar bg-<?= $pctColor ?>" id="dashBar_<?= $msg['id'] ?>" role="progressbar" style="width: <?= $pct ?>%"></div>
                                    </div>
                                    <span class="fw-bold small text-<?= $pctColor ?>" id="dashPct_<?= $msg['id'] ?>"><?= $pct ?>%</span>
                                </div>
                            </td>
                            <td class="text-center">
                                <?php if ($isOverdue): ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle" title="SLA Deadline passed with pending responses">
                                        <i class="bi bi-exclamation-triangle-fill me-1"></i>Overdue
                                    </span>
                                <?php elseif (!empty($stats['deadline_at'])): ?>
                                    <span class="badge bg-info-subtle text-info border border-info-subtle" title="Deadline: <?= format_tz($stats['deadline_at']) ?>">
                                        <i class="bi bi-clock me-1"></i>Active SLA
                                    </span>
                                <?php elseif (!empty($stats['avg_turnaround_formatted']) && $stats['avg_turnaround_formatted'] !== '—'): ?>
                                    <span class="badge bg-light text-secondary border" title="Average turnaround time">
                                        <i class="bi bi-stopwatch me-1"></i><?= e($stats['avg_turnaround_formatted']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small">&mdash;</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="<?= url('messages/view?id=' . $msg['id']) ?>" class="btn btn-sm btn-outline-dark">
                                    <i class="bi bi-arrow-right"></i> Details
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($filters['total_pages'] > 1): ?>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center py-3">
            <span class="small text-muted">
                Showing page <?= $filters['page'] ?> of <?= $filters['total_pages'] ?> (<?= number_format($filters['total_records']) ?> Total Messages)
            </span>
            <nav aria-label="Page navigation">
                <ul class="pagination pagination-sm mb-0">
                    <?php if ($filters['page'] > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?= url('messages?' . http_build_query(array_merge($_GET, ['page' => $filters['page'] - 1]))) ?>">Previous</a>
                        </li>
                    <?php endif; ?>

                    <?php for ($i = max(1, $filters['page'] - 2); $i <= min($filters['total_pages'], $filters['page'] + 2); $i++): ?>
                        <li class="page-item <?= $i == $filters['page'] ? 'active' : '' ?>">
                            <a class="page-link" href="<?= url('messages?' . http_build_query(array_merge($_GET, ['page' => $i]))) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($filters['page'] < $filters['total_pages']): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?= url('messages?' . http_build_query(array_merge($_GET, ['page' => $filters['page'] + 1]))) ?>">Next</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: Manually Track Message -->
<?php if ($canTrack): ?>
<div class="modal fade" id="modalManualTrack" tabindex="-1" aria-labelledby="modalManualTrackLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="<?= url('messages/create') ?>" class="modal-content">
            <?= csrf_field() ?>
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="modalManualTrackLabel">
                    <i class="bi bi-pin-angle text-primary me-2"></i> Track Group Message Manually
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">
                    Manually track an important announcement, instruction, or notice posted in a registered Telegram group.
                </p>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Telegram Group <span class="text-danger">*</span></label>
                    <select name="group_id" class="form-select form-select-sm" required>
                        <option value="">Select Group...</option>
                        <?php foreach ($groups as $g): ?>
                            <option value="<?= $g['id'] ?>" <?= (!empty($assignedGroupId) && $assignedGroupId == $g['id']) ? 'selected' : '' ?>>
                                <?= e($g['title']) ?> (<?= e($g['telegram_chat_id']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Message Text <span class="text-danger">*</span></label>
                    <textarea name="message_text" class="form-control form-control-sm" rows="4" placeholder="Enter message text or announcement..." required></textarea>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-sm-6">
                        <label class="form-label small fw-semibold">Telegram Message ID</label>
                        <input type="number" name="telegram_message_id" class="form-control form-control-sm" placeholder="e.g. 10452 (optional)">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label small fw-semibold">Reference Code</label>
                        <input type="text" name="reference_code" class="form-control form-control-sm" placeholder="e.g. #ANNC-042 (optional)">
                    </div>
                </div>

                <div class="row g-2">
                    <div class="col-sm-6">
                        <label class="form-label small fw-semibold">SLA Deadline (Hours)</label>
                        <input type="number" name="deadline_hours" class="form-control form-control-sm" placeholder="e.g. 24" min="1" max="720">
                        <div class="form-text" style="font-size: 0.72rem;">Optional SLA response deadline in hours.</div>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label small fw-semibold">Message Date (EAT)</label>
                        <input type="datetime-local" name="custom_date" class="form-control form-control-sm">
                        <div class="form-text" style="font-size: 0.72rem;">Leave blank for current time.</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-kaldis btn-sm">
                    <i class="bi bi-check2-circle me-1"></i> Begin Tracking
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Real-time Live Polling Engine -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const rows = document.querySelectorAll('tr[data-msg-id]');
    if (!rows.length) return;

    const messageIds = Array.from(rows).map(r => r.getAttribute('data-msg-id')).join(',');
    const syncTimeEl = document.getElementById('dashSyncTime');

    function syncLiveStats() {
        const urlParams = new URLSearchParams(window.location.search);
        urlParams.set('message_ids', messageIds);

        fetch('<?= url("api/dashboard/live-stats") ?>?' + urlParams.toString(), {
            headers: { 'Accept': 'application/json' }
        })
        .then(response => response.json())
        .then(data => {
            if (!data.ok) return;

            if (syncTimeEl && data.synced_at) {
                syncTimeEl.textContent = 'Synced ' + data.synced_at;
            }

            if (data.summary) {
                const totalEl = document.getElementById('dashTotalMessages');
                const repliedEl = document.getElementById('dashWithReplies');
                const pendingEl = document.getElementById('dashPendingMembers');
                const overdueEl = document.getElementById('dashOverdue');

                if (totalEl) totalEl.textContent = Number(data.summary.total_messages).toLocaleString();
                if (repliedEl) repliedEl.textContent = Number(data.summary.messages_with_replies).toLocaleString();
                if (pendingEl) pendingEl.textContent = Number(data.summary.total_pending_members).toLocaleString();
                if (overdueEl && data.summary.overdue_messages !== undefined) {
                    overdueEl.textContent = Number(data.summary.overdue_messages).toLocaleString();
                }
            }

            if (data.message_stats) {
                for (const [id, stats] of Object.entries(data.message_stats)) {
                    const repBadge = document.getElementById('dashReplied_' + id);
                    const eligBadge = document.getElementById('dashEligible_' + id);
                    const notRepBadge = document.getElementById('dashNotReplied_' + id);
                    const barEl = document.getElementById('dashBar_' + id);
                    const pctEl = document.getElementById('dashPct_' + id);

                    if (repBadge) repBadge.textContent = stats.replied;
                    if (eligBadge) eligBadge.textContent = stats.eligible;
                    if (notRepBadge) notRepBadge.textContent = stats.not_replied + ' pending';

                    const pct = Number(stats.percentage);
                    let color = pct >= 80 ? 'success' : (pct >= 40 ? 'warning' : 'danger');

                    if (barEl) {
                        barEl.style.width = pct + '%';
                        barEl.className = 'progress-bar bg-' + color;
                    }
                    if (pctEl) {
                        pctEl.textContent = pct + '%';
                        pctEl.className = 'fw-bold small text-' + color;
                    }
                }
            }
        })
        .catch(err => console.debug('Live sync heartbeat:', err));
    }

    // Run poll every 12 seconds
    setInterval(syncLiveStats, 12000);
});
</script>
