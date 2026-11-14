<?php
use App\Services\AuthService;
$user = AuthService::user();
$userName = $user['username'] ?? 'User';
$isOwner = AuthService::isOwner();
$isSuperAdmin = AuthService::isSuperAdmin();
$eatHour = (int)(new DateTime('now', new DateTimeZone(config('app.timezone', 'Africa/Addis_Ababa'))))->format('G');
if ($eatHour < 12) {
    $greeting = 'Good Morning';
} elseif ($eatHour < 18) {
    $greeting = 'Good Afternoon';
} else {
    $greeting = 'Good Evening';
}

$overallPct = $kpis['overall_compliance_rate'] ?? 0;
$overallColor = $overallPct >= 80 ? 'success' : ($overallPct >= 40 ? 'warning' : 'danger');
?>
<!-- Executive Dashboard Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
    <div>
        <h3 class="fw-bold mb-1 text-dark">
            <?= $greeting ?>, <?= e($userName) ?>
        </h3>
        <p class="text-muted small mb-0">
            Executive Operations &amp; Response Compliance Dashboard
            <?php if (!empty($userDept)): ?>
                &bull; <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= e($userDept) ?> Department</span>
            <?php endif; ?>
            <?php if (!empty($assignedGroupId)): ?>
                &bull; <span class="badge bg-success-subtle text-success border border-success-subtle">Assigned Group #<?= (int)$assignedGroupId ?></span>
            <?php endif; ?>
        </p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <div class="live-status-pill shadow-sm" title="Real-time operations active">
            <span class="pulse-dot"></span>
            <span>Live Monitor</span>
        </div>
        <?php if (!empty($canTrack)): ?>
            <button type="button" class="btn btn-kaldis btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#modalManualTrack">
                <i class="bi bi-pin-angle me-1"></i> Track Notice
            </button>
        <?php endif; ?>
        <a href="<?= url('messages') ?>" class="btn btn-outline-dark btn-sm shadow-sm">
            <i class="bi bi-chat-quote-fill me-1"></i> Tracked Group Messages
        </a>
    </div>
</div>

<!-- 6 Executive KPI Metrics Cards -->
<div class="row g-3 mb-4">
    <!-- Overall Compliance Rate -->
    <div class="col-12 col-md-4 col-xl-2">
        <div class="card card-summary p-3 h-100 shadow-sm border-0 border-start border-4 border-<?= $overallColor ?>">
            <div class="d-flex align-items-center justify-content-between mb-1">
                <span class="stat-label">Compliance</span>
                <span class="badge bg-<?= $overallColor ?>-subtle text-<?= $overallColor ?> border border-<?= $overallColor ?>-subtle" style="font-size: 0.68rem;">
                    <?= $overallPct >= 80 ? 'Healthy' : ($overallPct >= 40 ? 'Moderate' : 'Action Req.') ?>
                </span>
            </div>
            <div class="d-flex align-items-baseline gap-1">
                <span class="stat-value text-<?= $overallColor ?>"><?= $overallPct ?>%</span>
            </div>
            <div class="progress mt-2" style="height: 5px;">
                <div class="progress-bar bg-<?= $overallColor ?>" role="progressbar" style="width: <?= $overallPct ?>%"></div>
            </div>
            <div class="text-muted small mt-1" style="font-size: 0.72rem;">Overall staff response rate</div>
        </div>
    </div>

    <!-- Monitored Groups -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card card-summary p-3 h-100 shadow-sm">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-label">Active Groups</div>
                    <div class="stat-value text-dark"><?= number_format($kpis['total_groups']) ?></div>
                </div>
                <div class="stat-icon total">
                    <i class="bi bi-people"></i>
                </div>
            </div>
            <div class="text-muted small mt-1" style="font-size: 0.72rem;">Synchronized channels</div>
        </div>
    </div>

    <!-- Active Staff on Rosters -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card card-summary p-3 h-100 shadow-sm">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-label">Team Rosters</div>
                    <div class="stat-value text-primary"><?= number_format($kpis['total_members']) ?></div>
                </div>
                <div class="stat-icon replied">
                    <i class="bi bi-person-check"></i>
                </div>
            </div>
            <div class="text-muted small mt-1" style="font-size: 0.72rem;">Eligible staff members</div>
        </div>
    </div>

    <!-- Tracked Announcements -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card card-summary p-3 h-100 shadow-sm">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-label">Announcements</div>
                    <div class="stat-value text-dark"><?= number_format($kpis['total_messages']) ?></div>
                </div>
                <div class="stat-icon total">
                    <i class="bi bi-megaphone"></i>
                </div>
            </div>
            <div class="text-muted small mt-1" style="font-size: 0.72rem;">Tracked communications</div>
        </div>
    </div>

    <!-- Pending Staff Responses -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card card-summary p-3 h-100 shadow-sm">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-label">Pending Replies</div>
                    <div class="stat-value text-warning"><?= number_format($kpis['pending_staff']) ?></div>
                </div>
                <div class="stat-icon pending">
                    <i class="bi bi-hourglass-split"></i>
                </div>
            </div>
            <div class="text-muted small mt-1" style="font-size: 0.72rem;">Awaiting staff confirmation</div>
        </div>
    </div>

    <!-- SLA Overdue Notices -->
    <div class="col-12 col-md-4 col-xl-2">
        <div class="card card-summary p-3 h-100 shadow-sm">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-label">SLA Overdue</div>
                    <div class="stat-value text-danger"><?= number_format($kpis['overdue_count']) ?></div>
                </div>
                <div class="stat-icon unreplied">
                    <i class="bi bi-exclamation-octagon"></i>
                </div>
            </div>
            <div class="text-muted small mt-1" style="font-size: 0.72rem;">Past response deadline</div>
        </div>
    </div>
</div>

<!-- Monitored Groups Compliance Matrix Table -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5 class="card-title mb-0 fw-bold" style="color: var(--kaldis-primary);">
                <i class="bi bi-grid-3x3-gap-fill me-2"></i> Monitored Groups Compliance Matrix
            </h5>
            <small class="text-muted">Real-time status, team roster count, and response rates by Telegram group</small>
        </div>
        <a href="<?= url('groups') ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-gear me-1"></i> Manage Groups
        </a>
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-kaldis align-middle mb-0">
            <thead>
                <tr>
                    <th style="min-width: 220px;">Telegram Group</th>
                    <th>Department</th>
                    <th class="text-center" style="min-width: 110px;">Roster Size</th>
                    <th class="text-center" style="min-width: 120px;">Notices Tracked</th>
                    <th class="text-center" style="min-width: 180px;">Compliance Rate</th>
                    <th class="text-center" style="min-width: 110px;">Pending</th>
                    <th class="text-center" style="min-width: 100px;">Bot Status</th>
                    <th class="text-end" style="min-width: 140px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($groupPerformance)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-people fs-1 d-block mb-2 text-secondary"></i>
                            <div class="fw-semibold">No monitored groups registered yet.</div>
                            <small class="text-muted">Register your first Telegram group to start tracking response compliance.</small>
                            <div class="mt-3">
                                <a href="<?= url('groups') ?>" class="btn btn-kaldis btn-sm">
                                    <i class="bi bi-plus-circle me-1"></i> Register Telegram Group
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($groupPerformance as $gp): ?>
                        <?php 
                            $rate = $gp['compliance_rate'];
                            $rateColor = $rate >= 80 ? 'success' : ($rate >= 40 ? 'warning' : 'danger');
                        ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-dark"><?= e($gp['title']) ?></div>
                                <div class="d-flex align-items-center gap-1 mt-0.5">
                                    <span class="badge bg-light text-secondary border font-monospace" style="font-size: 0.65rem;">
                                        <?= e($gp['telegram_chat_id']) ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                    <?= e($gp['department'] ?? 'General') ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark border font-monospace px-2 py-1">
                                    <i class="bi bi-person me-1 text-muted"></i><?= number_format($gp['roster_count']) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="fw-semibold text-secondary">
                                    <?= number_format($gp['message_count']) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="d-flex align-items-center justify-content-center gap-2">
                                    <div class="progress flex-grow-1" style="height: 6px; max-width: 90px;">
                                        <div class="progress-bar bg-<?= $rateColor ?>" role="progressbar" style="width: <?= $rate ?>%"></div>
                                    </div>
                                    <span class="fw-bold small text-<?= $rateColor ?>"><?= $rate ?>%</span>
                                </div>
                            </td>
                            <td class="text-center">
                                <?php if ($gp['pending_count'] > 0): ?>
                                    <span class="badge badge-status-not_replied rounded-pill px-2 py-1">
                                        <?= $gp['pending_count'] ?> pending
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-status-replied rounded-pill px-2 py-1">
                                        <i class="bi bi-check2"></i> All Clear
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($gp['bot_status'] === 'active'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle" title="Bot is active in group">
                                        <i class="bi bi-check-circle-fill me-1"></i>Active
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle" title="Bot status: <?= e($gp['bot_status']) ?>">
                                        <?= e(ucfirst($gp['bot_status'] ?? 'unknown')) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="<?= url('messages?group_id=' . $gp['id']) ?>" class="btn btn-sm btn-outline-dark">
                                    <i class="bi bi-chat-quote me-1"></i> Messages &rarr;
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Department Analytics & Bot Status Section -->
<div class="row g-4 mb-4">
    <!-- Department Breakdown -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h6 class="card-title mb-0 fw-bold" style="color: var(--kaldis-primary);">
                    <i class="bi bi-building me-2"></i> Department Compliance Breakdown
                </h6>
            </div>
            <div class="card-body p-3">
                <?php if (empty($departmentStats)): ?>
                    <p class="text-muted small text-center my-4">No department statistics available.</p>
                <?php else: ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($departmentStats as $dept): ?>
                            <?php 
                                $dRate = $dept['compliance_rate'];
                                $dColor = $dRate >= 80 ? 'success' : ($dRate >= 40 ? 'warning' : 'danger');
                            ?>
                            <div class="p-3 border rounded bg-light">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-bold text-dark"><?= e($dept['department']) ?></span>
                                    <span class="fw-bold text-<?= $dColor ?>"><?= $dRate ?>% Compliance</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center small text-muted mb-2">
                                    <span><?= $dept['group_count'] ?> group(s) &bull; <?= $dept['member_count'] ?> roster members</span>
                                    <span><?= $dept['message_count'] ?> notice(s)</span>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-<?= $dColor ?>" role="progressbar" style="width: <?= $dRate ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bot & System Health -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="card-title mb-0 fw-bold" style="color: var(--kaldis-primary);">
                    <i class="bi bi-hdd-network me-2"></i> System &amp; Bot Health
                </h6>
                <span class="badge bg-success-subtle text-success border border-success-subtle">
                    Operational
                </span>
            </div>
            <div class="card-body p-3">
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                        <span class="text-muted"><i class="bi bi-clock me-2"></i>Timezone</span>
                        <span class="fw-semibold text-dark font-monospace"><?= e(config('app.timezone', 'Africa/Addis_Ababa')) ?> (EAT)</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                        <span class="text-muted"><i class="bi bi-pin-angle me-2"></i>Tracking Mode</span>
                        <span class="badge bg-secondary-subtle text-secondary font-monospace">
                            <?= e(config('telegram.tracking_mode', 'all_authorized')) ?>
                        </span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                        <span class="text-muted"><i class="bi bi-robot me-2"></i>Telegram Bot</span>
                        <span class="text-success fw-semibold"><i class="bi bi-check-circle-fill me-1"></i>Connected</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                        <span class="text-muted"><i class="bi bi-globe me-2"></i>Webhook Endpoint</span>
                        <span class="text-truncate font-monospace" style="max-width: 180px;" title="<?= url('webhook.php') ?>">
                            <?= url('webhook.php') ?>
                        </span>
                    </li>
                </ul>

                <?php if ($isSuperAdmin): ?>
                    <div class="mt-3 pt-2 border-top">
                        <a href="<?= url('settings/bot') ?>" class="btn btn-outline-secondary btn-sm w-100">
                            <i class="bi bi-robot me-1"></i> Configure Bot &amp; Webhook Settings
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recent Announcements Live Stream (Latest 5 Notices) -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5 class="card-title mb-0 fw-bold" style="color: var(--kaldis-primary);">
                <i class="bi bi-broadcast me-2"></i> Recent Announcements Live Stream
            </h5>
            <small class="text-muted">Latest 5 notices monitored across your accessible groups</small>
        </div>
        <a href="<?= url('messages') ?>" class="btn btn-sm btn-kaldis">
            <i class="bi bi-chat-quote-fill me-1"></i> View All Messages (<?= $kpis['total_messages'] ?>) &rarr;
        </a>
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-kaldis align-middle mb-0">
            <thead>
                <tr>
                    <th style="min-width: 140px;">Date &amp; Time (EAT)</th>
                    <th>Group</th>
                    <th style="min-width: 250px;">Announcement</th>
                    <th class="text-center" style="min-width: 160px;">Replies / Roster</th>
                    <th class="text-center" style="min-width: 130px;">Response Rate</th>
                    <th class="text-center" style="min-width: 110px;">SLA Status</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentMessages)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="bi bi-chat-left-dots fs-2 d-block mb-1 text-secondary"></i>
                            <div>No announcements recorded yet.</div>
                            <small>Post an announcement in a monitored Telegram group to begin tracking.</small>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentMessages as $msg): ?>
                        <?php 
                            $stats = $msg['stats']; 
                            $pct = $stats['percentage'];
                            $pctColor = $pct >= 80 ? 'success' : ($pct >= 40 ? 'warning' : 'danger');
                            $isOverdue = !empty($stats['is_overdue']);
                        ?>
                        <tr>
                            <td>
                                <div class="fw-semibold small"><?= format_tz($msg['message_date'], 'M j, Y') ?></div>
                                <div class="text-muted small"><?= format_tz($msg['message_date'], 'h:i A') ?></div>
                            </td>
                            <td>
                                <div class="fw-semibold text-truncate" style="max-width: 170px;" title="<?= e($msg['group_title']) ?>">
                                    <?= e($msg['group_title']) ?>
                                </div>
                                <span class="badge bg-light text-secondary border font-monospace" style="font-size: 0.65rem;">
                                    <?= e($msg['telegram_chat_id']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($msg['reference_code'])): ?>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle me-1">
                                        <?= e($msg['reference_code']) ?>
                                    </span>
                                <?php endif; ?>
                                <span class="small text-dark">
                                    <?= e(mb_strimwidth($msg['message_text'], 0, 100, '...')) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge badge-status-replied rounded-pill px-2 py-0.5">
                                    <?= $stats['replied'] ?>
                                </span>
                                <span class="text-muted small">/</span>
                                <span class="fw-semibold text-secondary small">
                                    <?= $stats['eligible'] ?>
                                </span>
                                <span class="badge badge-status-not_replied rounded-pill px-2 py-0.5 ms-1" style="font-size: 0.72rem;">
                                    <?= $stats['not_replied'] ?> pending
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="d-flex align-items-center justify-content-center gap-2">
                                    <div class="progress flex-grow-1" style="height: 6px; width: 50px;">
                                        <div class="progress-bar bg-<?= $pctColor ?>" role="progressbar" style="width: <?= $pct ?>%"></div>
                                    </div>
                                    <span class="fw-bold small text-<?= $pctColor ?>"><?= $pct ?>%</span>
                                </div>
                            </td>
                            <td class="text-center">
                                <?php if ($isOverdue): ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                        <i class="bi bi-exclamation-triangle-fill me-1"></i>Overdue
                                    </span>
                                <?php elseif (!empty($stats['deadline_at'])): ?>
                                    <span class="badge bg-info-subtle text-info border border-info-subtle">
                                        <i class="bi bi-clock me-1"></i>Active SLA
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

    <!-- Bottom Action Card Footer -->
    <div class="card-footer bg-light p-3 text-center">
        <a href="<?= url('messages') ?>" class="btn btn-kaldis px-4 shadow-sm">
            <i class="bi bi-chat-quote-fill me-2"></i> Open Full Tracked Group Messages Screen (<?= $kpis['total_messages'] ?> Total) &rarr;
        </a>
    </div>
</div>

<!-- Modal: Manually Track Message -->
<?php if (!empty($canTrack)): ?>
<div class="modal fade" id="modalManualTrack" tabindex="-1" aria-labelledby="modalManualTrackLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="<?= url('messages/create') ?>" class="modal-content">
            <?= csrf_field() ?>
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="modalManualTrackLabel">
                    <i class="bi bi-pin-angle text-primary me-2"></i> Track Notice Manually
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
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
                    <textarea name="message_text" class="form-control form-control-sm" rows="4" placeholder="Enter notice or instructions..." required></textarea>
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
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label small fw-semibold">Message Date (EAT)</label>
                        <input type="datetime-local" name="custom_date" class="form-control form-control-sm">
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
