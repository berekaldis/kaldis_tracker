<?php
use App\Services\AuthService;
$isOwner = AuthService::isOwner();
$pct = $stats['percentage'];
$pctColor = $pct >= 80 ? 'success' : ($pct >= 40 ? 'warning' : 'danger');
?>
<div class="row align-items-center mb-4">
    <div class="col-md-8">
        <div class="d-flex align-items-center">
            <a href="<?= url('dashboard') ?>" class="btn btn-outline-secondary btn-sm me-3">
                <i class="bi bi-arrow-left"></i> Dashboard
            </a>
            <div>
                <h3 class="fw-bold mb-0" style="color: var(--kaldis-primary);">Tracked Message Response Details</h3>
                <span class="text-muted small">
                    Group: <strong><?= e($message['group_title']) ?></strong> (<?= e($message['telegram_chat_id']) ?>) &bull; 
                    Message ID: <code><?= e($message['telegram_message_id']) ?></code>
                </span>
            </div>
        </div>
    </div>
    <div class="col-md-4 text-md-end mt-3 mt-md-0">
        <span class="badge bg-light text-dark border px-3 py-2 fs-6">
            Response Rate: <span class="fw-bold text-<?= $pctColor ?>"><?= $pct ?>%</span>
        </span>
    </div>
</div>

<!-- Message Original Content Card -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="card-title mb-0 fw-bold text-dark">
            <i class="bi bi-chat-quote-fill text-primary me-2"></i> Original Tracked Message Content
        </h6>
        <div class="small text-muted">
            Sent: <?= format_tz($message['message_date']) ?> (EAT)
            &bull; Source: <span class="badge bg-light text-dark border"><?= $message['tracking_source'] === 'auto' ? 'Conversation (Auto)' : e(ucfirst($message['tracking_source'])) ?></span>
            <?php if (!empty($message['reference_code'])): ?>
                &bull; Reference: <span class="badge bg-primary-subtle text-primary border"><?= e($message['reference_code']) ?></span>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body p-4 bg-light bg-opacity-50">
        <div class="p-3 bg-white rounded border text-break font-monospace" style="white-space: pre-wrap; font-size: 0.95rem;">
<?= e($message['message_text']) ?>
        </div>
        <?php if (!empty($message['edited_at'])): ?>
            <div class="small text-muted mt-2">
                <i class="bi bi-pencil-square"></i> Last edited at <?= format_tz($message['edited_at']) ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Response Stats Counters -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-md-3">
        <div class="card border-0 shadow-sm p-3 text-center">
            <div class="small text-muted text-uppercase fw-semibold">Eligible Members</div>
            <div class="fs-3 fw-bold text-dark"><?= $stats['eligible'] ?></div>
            <div class="small text-muted">Active on message date</div>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="card border-0 shadow-sm p-3 text-center">
            <div class="small text-muted text-uppercase fw-semibold">Confirmed Replied</div>
            <div class="fs-3 fw-bold text-success"><?= $stats['replied'] ?></div>
            <div class="small text-muted">Direct reply or reference</div>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="card border-0 shadow-sm p-3 text-center">
            <div class="small text-muted text-uppercase fw-semibold">Not Replied</div>
            <div class="fs-3 fw-bold text-danger"><?= $stats['not_replied'] ?></div>
            <div class="small text-muted">Confirmed non-response</div>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="card border-0 shadow-sm p-3 text-center">
            <div class="small text-muted text-uppercase fw-semibold">Review / Unknown</div>
            <div class="fs-3 fw-bold text-warning"><?= $stats['pending_review'] + $stats['unknown'] ?></div>
            <div class="small text-muted">Ambiguous or unconfirmed</div>
        </div>
    </div>
</div>

<!-- Member Responses Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0 fw-bold" style="color: var(--kaldis-primary);">
            <i class="bi bi-person-lines-fill me-2"></i> Member Response Breakdown
        </h5>
        <span class="badge bg-light text-secondary border">Total Roster: <?= count($members) ?></span>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Member Name</th>
                    <th>Telegram Username</th>
                    <th class="text-center">Status</th>
                    <th style="min-width: 250px;">Reply Text</th>
                    <th>Reply Time (EAT)</th>
                    <th class="text-center">Method</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($members)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-people fs-1 d-block mb-2 text-secondary"></i>
                            <div>No eligible members found active at the message date.</div>
                            <small class="text-muted">Import a roster or ensure group members are registered.</small>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($members as $m): ?>
                        <?php 
                            $status = $m['status'];
                            $badgeClass = match($status) {
                                'replied' => 'badge-status-replied',
                                'not_replied' => 'badge-status-not_replied',
                                'pending_review' => 'badge-status-pending_review',
                                'unknown' => 'badge-status-unknown',
                                'excluded' => 'badge-status-excluded',
                                default => 'badge-status-unknown'
                            };
                            $statusLabel = match($status) {
                                'replied' => 'Replied',
                                'not_replied' => 'Not Replied',
                                'pending_review' => 'Pending Review',
                                'unknown' => 'Unknown',
                                'excluded' => 'Excluded',
                                default => ucfirst($status)
                            };
                        ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-dark"><?= e($m['full_name']) ?></div>
                                <span class="font-monospace small text-muted">ID: <?= e($m['telegram_user_id']) ?></span>
                            </td>
                            <td>
                                <?php if (!empty($m['username'])): ?>
                                    <span class="text-primary small">@<?= e($m['username']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge <?= $badgeClass ?> px-2 py-1">
                                    <?= $statusLabel ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($m['response_text'])): ?>
                                    <div class="small text-dark text-break">
                                        <?= e(mb_strimwidth($m['response_text'], 0, 140, '...')) ?>
                                    </div>
                                    <?php if (!empty($m['response_telegram_message_id'])): ?>
                                        <span class="font-monospace text-muted" style="font-size: 0.65rem;">
                                            Msg #<?= e($m['response_telegram_message_id']) ?>
                                        </span>
                                    <?php endif; ?>
                                <?php elseif ($status === 'excluded'): ?>
                                    <span class="badge bg-light text-secondary border">
                                        Reason: <?= e($m['excluded_reason'] ?? 'Not specified') ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="small text-muted">
                                    <?= format_tz($m['response_date']) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if (!empty($m['matching_method'])): ?>
                                    <span class="badge bg-light text-dark border small text-capitalize">
                                        <?= str_replace('_', ' ', e($m['matching_method'])) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <span class="text-muted small">Phase 2</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
