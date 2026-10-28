<?php
use App\Services\AuthService;
$isOwner = AuthService::isOwner();
?>
<div class="row align-items-center mb-4">
    <div class="col-md-7">
        <h3 class="fw-bold mb-1" style="color: var(--kaldis-primary);">Response Monitoring Dashboard</h3>
        <p class="text-muted small mb-0">
            Real-time tracking of message responses across registered Kaldis Telegram groups.
            <?php if (($trackingMode ?? 'all_authorized') === 'all_authorized'): ?>
                <span class="badge bg-success-subtle text-success border border-success-subtle ms-2">
                    <i class="bi bi-chat-dots-fill me-1"></i> Tracking Each Conversation
                </span>
            <?php elseif (($trackingMode ?? '') === 'all_messages'): ?>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-2">
                    <i class="bi bi-globe me-1"></i> Tracking All Group Messages
                </span>
            <?php else: ?>
                <span class="badge bg-secondary-subtle text-secondary border ms-2">
                    <i class="bi bi-hash me-1"></i> #track Trigger Only
                </span>
            <?php endif; ?>
        </p>
    </div>
    <div class="col-md-5 text-md-end mt-3 mt-md-0">
        <?php if ($isOwner): ?>
            <button type="button" class="btn btn-kaldis shadow-sm" data-bs-toggle="modal" data-bs-target="#modalManualTrack">
                <i class="bi bi-pin-angle me-1"></i> Track Message
            </button>
        <?php endif; ?>
        <a href="<?= url('groups') ?>" class="btn btn-outline-secondary ms-2 shadow-sm">
            <i class="bi bi-people me-1"></i> Manage Groups
        </a>
    </div>
</div>

<!-- 4 Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card card-summary p-3 h-100 shadow-sm">
            <div class="d-flex align-items-center">
                <div class="stat-icon total me-3">
                    <i class="bi bi-chat-left-text"></i>
                </div>
                <div>
                    <div class="stat-label">Total Messages</div>
                    <div class="stat-value text-dark"><?= number_format($summary['total_messages']) ?></div>
                </div>
            </div>
            <div class="text-muted small mt-2">
                Messages tracked matching active filter
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card card-summary p-3 h-100 shadow-sm">
            <div class="d-flex align-items-center">
                <div class="stat-icon replied me-3">
                    <i class="bi bi-check2-circle"></i>
                </div>
                <div>
                    <div class="stat-label">With Replies</div>
                    <div class="stat-value text-success"><?= number_format($summary['messages_with_replies']) ?></div>
                </div>
            </div>
            <div class="text-muted small mt-2">
                At least one member has responded
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card card-summary p-3 h-100 shadow-sm">
            <div class="d-flex align-items-center">
                <div class="stat-icon unreplied me-3">
                    <i class="bi bi-x-circle"></i>
                </div>
                <div>
                    <div class="stat-label">Without Replies</div>
                    <div class="stat-value text-danger"><?= number_format($summary['messages_without_replies']) ?></div>
                </div>
            </div>
            <div class="text-muted small mt-2">
                Zero confirmed replies received
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card card-summary p-3 h-100 shadow-sm">
            <div class="d-flex align-items-center">
                <div class="stat-icon pending me-3">
                    <i class="bi bi-hourglass-split"></i>
                </div>
                <div>
                    <div class="stat-label">Total Pending Members</div>
                    <div class="stat-value text-warning"><?= number_format($summary['total_pending_members']) ?></div>
                </div>
            </div>
            <div class="text-muted small mt-2">
                Confirmed non-replied roster members
            </div>
        </div>
    </div>
</div>

<!-- Search & Filter Card -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="<?= url('dashboard') ?>" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted mb-1">Search Message Text / Ref</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" value="<?= e($filters['search']) ?>" placeholder="Search text or #task...">
                </div>
            </div>

            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted mb-1">Telegram Group</label>
                <select name="group_id" class="form-select form-select-sm">
                    <option value="">All Registered Groups</option>
                    <?php foreach ($groups as $g): ?>
                        <option value="<?= $g['id'] ?>" <?= $filters['group_id'] == $g['id'] ? 'selected' : '' ?>>
                            <?= e($g['title']) ?> (<?= e($g['telegram_chat_id']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Date From (Addis Time)</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($filters['date_from']) ?>">
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Date To (Addis Time)</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($filters['date_to']) ?>">
            </div>

            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-dark flex-grow-1">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
                <?php if (!empty($filters['search']) || !empty($filters['group_id']) || !empty($filters['date_from']) || !empty($filters['date_to'])): ?>
                    <a href="<?= url('dashboard') ?>" class="btn btn-sm btn-outline-secondary" title="Reset Filters">
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
        <h5 class="card-title mb-0 fw-bold" style="color: var(--kaldis-primary);">
            <i class="bi bi-table me-2"></i> Tracked Group Messages
        </h5>
        <span class="badge bg-light text-dark border">
            Total Records: <?= number_format($filters['total_records']) ?>
        </span>
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-kaldis align-middle mb-0">
            <thead>
                <tr>
                    <th style="min-width: 150px;">Date &amp; Time (EAT)</th>
                    <th>Group</th>
                    <th style="min-width: 250px;">Tracked Message</th>
                    <th class="text-center">Eligible</th>
                    <th class="text-center">Replied</th>
                    <th class="text-center">Not Replied</th>
                    <th class="text-center">Review / Unknown</th>
                    <th class="text-center" style="min-width: 140px;">Response %</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($messages)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                            <div>No tracked messages found matching your criteria.</div>
                            <?php if (($trackingMode ?? 'all_authorized') === 'all_authorized'): ?>
                                <small class="text-muted">Post any message in a registered group from your authorized Telegram account to start tracking responses automatically.</small>
                            <?php elseif (($trackingMode ?? '') === 'all_messages'): ?>
                                <small class="text-muted">Post any message in a registered group to start tracking responses automatically.</small>
                            <?php else: ?>
                                <small class="text-muted">Post a message with <code><?= e(config('telegram.track_hashtag', '#track')) ?></code> in a registered group or track one manually.</small>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($messages as $msg): ?>
                        <?php 
                            $stats = $msg['stats']; 
                            $pct = $stats['percentage'];
                            $pctColor = $pct >= 80 ? 'success' : ($pct >= 40 ? 'warning' : 'danger');
                        ?>
                        <tr>
                            <td>
                                <div class="fw-semibold small"><?= format_tz($msg['message_date'], 'M j, Y') ?></div>
                                <div class="text-muted small"><?= format_tz($msg['message_date'], 'h:i:s A') ?></div>
                            </td>
                            <td>
                                <div class="fw-semibold text-truncate" style="max-width: 180px;" title="<?= e($msg['group_title']) ?>">
                                    <?= e($msg['group_title']) ?>
                                </div>
                                <span class="badge bg-light text-secondary border font-monospace" style="font-size: 0.7rem;">
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
                                    <?= e(mb_strimwidth($msg['message_text'], 0, 110, '...')) ?>
                                </span>
                                <?php if (!empty($msg['edited_at'])): ?>
                                    <span class="badge bg-secondary-subtle text-secondary ms-1" title="Edited on <?= format_tz($msg['edited_at']) ?>">
                                        <i class="bi bi-pencil-square"></i> edited
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center fw-semibold">
                                <?= $stats['eligible'] ?>
                            </td>
                            <td class="text-center">
                                <span class="badge badge-status-replied px-2 py-1">
                                    <?= $stats['replied'] ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge badge-status-not_replied px-2 py-1">
                                    <?= $stats['not_replied'] ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if ($stats['pending_review'] > 0): ?>
                                    <span class="badge badge-status-pending_review me-1" title="Pending Review">
                                        <?= $stats['pending_review'] ?> review
                                    </span>
                                <?php endif; ?>
                                <?php if ($stats['unknown'] > 0): ?>
                                    <span class="badge badge-status-unknown" title="Unknown Visibility">
                                        <?= $stats['unknown'] ?> unk
                                    </span>
                                <?php endif; ?>
                                <?php if ($stats['pending_review'] == 0 && $stats['unknown'] == 0): ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="d-flex align-items-center justify-content-center gap-2">
                                    <div class="progress flex-grow-1" style="height: 6px; width: 60px;">
                                        <div class="progress-bar bg-<?= $pctColor ?>" role="progressbar" style="width: <?= $pct ?>%"></div>
                                    </div>
                                    <span class="fw-bold small text-<?= $pctColor ?>"><?= $pct ?>%</span>
                                </div>
                            </td>
                            <td class="text-end">
                                <a href="<?= url('messages/view?id=' . $msg['id']) ?>" class="btn btn-sm btn-outline-dark">
                                    <i class="bi bi-eye me-1"></i> View Details
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
                Showing page <?= $filters['page'] ?> of <?= $filters['total_pages'] ?>
            </span>
            <nav aria-label="Page navigation">
                <ul class="pagination pagination-sm mb-0">
                    <?php if ($filters['page'] > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?= url('dashboard?' . http_build_query(array_merge($_GET, ['page' => $filters['page'] - 1]))) ?>">Previous</a>
                        </li>
                    <?php endif; ?>

                    <?php for ($i = max(1, $filters['page'] - 2); $i <= min($filters['total_pages'], $filters['page'] + 2); $i++): ?>
                        <li class="page-item <?= $i == $filters['page'] ? 'active' : '' ?>">
                            <a class="page-link" href="<?= url('dashboard?' . http_build_query(array_merge($_GET, ['page' => $i]))) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($filters['page'] < $filters['total_pages']): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?= url('dashboard?' . http_build_query(array_merge($_GET, ['page' => $filters['page'] + 1]))) ?>">Next</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: Manually Track Message -->
<?php if ($isOwner): ?>
<div class="modal fade" id="modalManualTrack" tabindex="-1" aria-labelledby="modalManualTrackLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="<?= url('messages/create') ?>" class="modal-content">
            <?= csrf_field() ?>
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="modalManualTrackLabel">
                    <i class="bi bi-pin-angle text-primary me-2"></i> Track Message Manually
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Target Telegram Group <span class="text-danger">*</span></label>
                    <select name="group_id" class="form-select" required>
                        <option value="">Select Group...</option>
                        <?php foreach ($groups as $g): ?>
                            <option value="<?= $g['id'] ?>"><?= e($g['title']) ?> (<?= e($g['telegram_chat_id']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Telegram Message ID <span class="text-danger">*</span></label>
                        <input type="number" name="telegram_message_id" class="form-control" required placeholder="e.g. 1042">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Thread ID (Forum topics only)</label>
                        <input type="number" name="thread_id" class="form-control" placeholder="Optional topic ID">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Message Content <span class="text-danger">*</span></label>
                    <textarea name="message_text" class="form-control" rows="4" required placeholder="Enter or paste the original message text... (can include #task-123 reference)"></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Date &amp; Time (Africa/Addis_Ababa)</label>
                    <input type="datetime-local" name="message_date" class="form-control" value="<?= date('Y-m-d\TH:i') ?>">
                    <div class="form-text small">Leave as current time or select the actual message send time.</div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-kaldis btn-sm">
                    <i class="bi bi-check-lg me-1"></i> Register as Tracked
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
