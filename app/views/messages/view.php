<?php
use App\Services\AuthService;
$isOwner = AuthService::isOwner();
$pct = $stats['percentage'];
$pctColor = $pct >= 80 ? 'success' : ($pct >= 40 ? 'warning' : 'danger');

// Calculate counts for filter tabs
$countAll = count($members);
$countReplied = 0;
$countNotReplied = 0;
$countPending = 0;
$countOther = 0;

$unrepliedMentions = [];
foreach ($members as $m) {
    match ($m['status']) {
        'replied' => $countReplied++,
        'not_replied' => $countNotReplied++,
        'pending_review' => $countPending++,
        default => $countOther++
    };
    if ($m['status'] === 'not_replied') {
        if (!empty($m['username'])) {
            $unrepliedMentions[] = '@' . $m['username'];
        } else {
            $unrepliedMentions[] = $m['full_name'];
        }
    }
}
$unrepliedString = implode(' ', $unrepliedMentions);
?>

<!-- Header / Navigation & Live Indicator -->
<div class="row align-items-center mb-4">
    <div class="col-lg-6">
        <div class="d-flex align-items-center">
            <a href="<?= url('dashboard') ?>" class="btn btn-outline-secondary btn-sm me-3 shadow-sm">
                <i class="bi bi-arrow-left"></i> Dashboard
            </a>
            <div>
                <h3 class="fw-bold mb-0" style="color: var(--kaldis-primary);">
                    Tracked Conversation Details
                </h3>
                <span class="text-muted small">
                    Group: <strong><?= e($message['group_title']) ?></strong> &bull; 
                    Chat ID: <code><?= e($message['telegram_chat_id']) ?></code>
                </span>
            </div>
        </div>
    </div>
    <div class="col-lg-6 text-lg-end mt-3 mt-lg-0 d-flex flex-wrap align-items-center justify-content-lg-end gap-2">
        <!-- Live Sync Status Pill -->
        <div class="d-inline-flex align-items-center bg-white px-3 py-1 rounded-pill border shadow-sm small">
            <span class="pulse-dot" id="livePulseDot"></span>
            <span id="liveStatusText" class="fw-semibold text-dark me-2">Live Sync</span>
            <span id="liveSyncTime" class="text-muted" style="font-size: 0.75rem;">Just now</span>
            <button type="button" class="btn btn-link btn-sm p-0 ms-2 text-decoration-none text-muted" id="btnToggleSync" title="Pause / Resume Live Updates">
                <i class="bi bi-pause-circle" id="syncIcon"></i>
            </button>
        </div>

        <!-- Copy Unreplied Button -->
        <button type="button" class="btn btn-sm btn-outline-danger shadow-sm" onclick="copyUnrepliedList()" title="Copy list of members who have not replied">
            <i class="bi bi-clipboard-check me-1"></i> Copy Unreplied (<?= $countNotReplied ?>)
        </button>

        <!-- Export CSV Button -->
        <a href="<?= url('messages/export?id=' . $message['id']) ?>" class="btn btn-sm btn-outline-secondary shadow-sm" title="Download responses CSV">
            <i class="bi bi-download me-1"></i> Export CSV
        </a>
    </div>
</div>

<!-- Message Original Content Card -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex align-items-center">
            <span class="fs-4 me-2">💬</span>
            <div>
                <h6 class="card-title mb-0 fw-bold text-dark">Original Tracked Message</h6>
                <div class="small text-muted">
                    Sent at <strong><?= format_tz($message['message_date']) ?></strong> (EAT) &bull;
                    Telegram Msg ID: <code>#<?= e($message['telegram_message_id']) ?></code>
                </div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-light text-dark border">
                Source: <?= $message['tracking_source'] === 'auto' ? 'Conversation (Auto)' : e(ucfirst($message['tracking_source'])) ?>
            </span>
            <?php if (!empty($message['reference_code'])): ?>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                    Ref: <?= e($message['reference_code']) ?>
                </span>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body p-4 bg-light bg-opacity-50">
        <div class="p-3 bg-white rounded border shadow-sm text-break font-monospace" style="white-space: pre-wrap; font-size: 0.98rem; line-height: 1.6;">
<?= e($message['message_text']) ?>
        </div>
        <?php if (!empty($message['edited_at'])): ?>
            <div class="small text-muted mt-2">
                <i class="bi bi-pencil-square"></i> Last edited at <?= format_tz($message['edited_at']) ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Response Progress & Metrics Row -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="fw-bold text-dark">Overall Response Completion</span>
            <span class="badge bg-<?= $pctColor ?>-subtle text-<?= $pctColor ?> border fs-6 fw-bold px-3 py-1" id="headerPercentageBadge">
                <?= $pct ?>% Complete
            </span>
        </div>

        <!-- Multi-segment visual progress bar -->
        <?php
            $repliedPct = ($stats['denominator'] > 0) ? round(($stats['replied'] / $stats['denominator']) * 100, 1) : 0;
            $pendingPct = ($stats['denominator'] > 0) ? round(($stats['pending_review'] / $stats['denominator']) * 100, 1) : 0;
        ?>
        <div class="multi-segment-bar mb-3" id="progressBarContainer">
            <div class="bg-success progress-bar-animated" id="barReplied" role="progressbar" style="width: <?= $repliedPct ?>%;" title="Replied: <?= $stats['replied'] ?>"></div>
            <div class="bg-warning progress-bar-animated" id="barPending" role="progressbar" style="width: <?= $pendingPct ?>%;" title="Pending: <?= $stats['pending_review'] ?>"></div>
        </div>

        <!-- 4 Summary Counter Cards -->
        <div class="row g-3 text-center">
            <div class="col-6 col-md-3">
                <div class="p-3 bg-light rounded border">
                    <div class="text-muted small text-uppercase fw-semibold">Eligible Members</div>
                    <div class="fs-3 fw-bold text-dark" id="statEligible"><?= $stats['eligible'] ?></div>
                    <div class="small text-muted">Active on roster</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-3 bg-success-subtle rounded border border-success-subtle">
                    <div class="text-success small text-uppercase fw-semibold">Confirmed Replied</div>
                    <div class="fs-3 fw-bold text-success" id="statReplied"><?= $stats['replied'] ?></div>
                    <div class="small text-success">Direct reply / ref</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-3 bg-danger-subtle rounded border border-danger-subtle">
                    <div class="text-danger small text-uppercase fw-semibold">Not Replied</div>
                    <div class="fs-3 fw-bold text-danger" id="statNotReplied"><?= $stats['not_replied'] ?></div>
                    <div class="small text-danger">Awaiting response</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-3 bg-warning-subtle rounded border border-warning-subtle">
                    <div class="text-warning-emphasis small text-uppercase fw-semibold">Pending / Unknown</div>
                    <div class="fs-3 fw-bold text-warning-emphasis" id="statPending"><?= $stats['pending_review'] + $stats['unknown'] ?></div>
                    <div class="small text-muted">Review required</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Member Responses Table Card -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <div class="row g-2 align-items-center">
            <!-- Filter Tabs -->
            <div class="col-md-7">
                <div class="nav nav-pills flex-nowrap overflow-auto" id="filterTabs" role="tablist">
                    <button class="nav-link active btn-sm px-3 py-1 me-1" onclick="filterMembers('all')">
                        All <span class="badge bg-secondary ms-1" id="tabCountAll"><?= $countAll ?></span>
                    </button>
                    <button class="nav-link btn-sm px-3 py-1 me-1 text-success" onclick="filterMembers('replied')">
                        Replied <span class="badge bg-success ms-1" id="tabCountReplied"><?= $countReplied ?></span>
                    </button>
                    <button class="nav-link btn-sm px-3 py-1 me-1 text-danger" onclick="filterMembers('not_replied')">
                        Not Replied <span class="badge bg-danger ms-1" id="tabCountNotReplied"><?= $countNotReplied ?></span>
                    </button>
                    <button class="nav-link btn-sm px-3 py-1 me-1 text-warning-emphasis" onclick="filterMembers('pending_review')">
                        Review <span class="badge bg-warning text-dark ms-1" id="tabCountPending"><?= $countPending ?></span>
                    </button>
                    <button class="nav-link btn-sm px-3 py-1 me-1 text-secondary" onclick="filterMembers('other')">
                        Other / Excluded <span class="badge bg-secondary ms-1" id="tabCountOther"><?= $countOther ?></span>
                    </button>
                </div>
            </div>
            <!-- Search Filter -->
            <div class="col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                    <input type="text" id="memberSearchInput" class="form-control" placeholder="Search members or reply text..." oninput="searchMembers()">
                </div>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="membersTable">
            <thead class="table-light">
                <tr>
                    <th style="min-width: 220px;">Group Member</th>
                    <th class="text-center" style="width: 130px;">Status</th>
                    <th style="min-width: 260px;">Response / Content</th>
                    <th style="min-width: 160px;">Reply Time (EAT)</th>
                    <th class="text-center" style="width: 120px;">Method</th>
                    <th class="text-end" style="width: 180px;">Actions</th>
                </tr>
            </thead>
            <tbody id="membersTableBody">
                <?php if (empty($members)): ?>
                    <tr id="emptyRow">
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-people fs-1 d-block mb-2 text-secondary"></i>
                            <div>No eligible members found active at the message date.</div>
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
                            $initials = mb_substr($m['full_name'], 0, 2);
                        ?>
                        <tr data-user-id="<?= $m['telegram_user_id'] ?>" data-member-id="<?= $m['member_id'] ?>" data-status="<?= $status ?>" id="row_<?= $m['telegram_user_id'] ?>">
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="member-avatar-circle me-2">
                                        <?= e($initials) ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark member-name"><?= e($m['full_name']) ?></div>
                                        <div class="small">
                                            <?php if (!empty($m['username'])): ?>
                                                <a href="https://t.me/<?= e($m['username']) ?>" target="_blank" class="text-decoration-none text-primary">
                                                    @<?= e($m['username']) ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted font-monospace" style="font-size: 0.75rem;">ID: <?= e($m['telegram_user_id']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-center status-col">
                                <span class="badge <?= $badgeClass ?> px-2 py-1">
                                    <?= $statusLabel ?>
                                </span>
                            </td>
                            <td class="text-col">
                                <?php if (!empty($m['response_text'])): ?>
                                    <div class="speech-bubble <?= $status === 'replied' ? 'replied' : '' ?>">
                                        <div class="small text-dark text-break">
                                            <?= e(mb_strimwidth($m['response_text'], 0, 140, '...')) ?>
                                        </div>
                                        <?php if (!empty($m['response_telegram_message_id'])): ?>
                                            <span class="text-muted" style="font-size: 0.65rem;">
                                                Msg #<?= e($m['response_telegram_message_id']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif ($status === 'excluded'): ?>
                                    <span class="badge bg-light text-secondary border small">
                                        Reason: <?= e($m['excluded_reason'] ?? 'Not specified') ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small fst-italic">Awaiting response...</span>
                                <?php endif; ?>
                            </td>
                            <td class="date-col small text-muted">
                                <?= !empty($m['response_date']) ? format_tz($m['response_date']) : '—' ?>
                            </td>
                            <td class="text-center method-col">
                                <?php if (!empty($m['matching_method'])): ?>
                                    <span class="badge bg-light text-dark border small text-capitalize">
                                        <?= str_replace('_', ' ', e($m['matching_method'])) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end actions-col">
                                <?php if ($isOwner): ?>
                                    <?php if ($status === 'pending_review'): ?>
                                        <div class="d-inline-flex gap-1">
                                            <form action="<?= url('messages/confirm-response') ?>" method="POST" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="message_id" value="<?= $message['id'] ?>">
                                                <input type="hidden" name="response_id" value="<?= $m['response_id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-success px-2 py-0" title="Confirm as Replied">
                                                    <i class="bi bi-check-lg"></i> Confirm
                                                </button>
                                            </form>
                                            <form action="<?= url('messages/reject-response') ?>" method="POST" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="message_id" value="<?= $message['id'] ?>">
                                                <input type="hidden" name="response_id" value="<?= $m['response_id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger px-2 py-0" title="Reject Response">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            </form>
                                        </div>
                                    <?php elseif ($status === 'excluded'): ?>
                                        <form action="<?= url('messages/include-member') ?>" method="POST" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="message_id" value="<?= $message['id'] ?>">
                                            <input type="hidden" name="telegram_user_id" value="<?= $m['telegram_user_id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-primary px-2 py-0" title="Restore to Calculation">
                                                <i class="bi bi-arrow-counterclockwise"></i> Re-include
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <div class="dropdown d-inline">
                                            <button class="btn btn-sm btn-light border px-2 py-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="bi bi-three-dots"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm small">
                                                <li>
                                                    <a class="dropdown-item" href="javascript:void(0)" onclick="openManualReplyModal(<?= $m['member_id'] ?>, <?= $m['telegram_user_id'] ?>, '<?= e(addslashes($m['full_name'])) ?>')">
                                                        <i class="bi bi-check2-circle text-success me-2"></i> Mark as Replied (Manual)
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="javascript:void(0)" onclick="openExcludeModal(<?= $m['member_id'] ?>, <?= $m['telegram_user_id'] ?>, '<?= e(addslashes($m['full_name'])) ?>')">
                                                        <i class="bi bi-person-x text-danger me-2"></i> Exclude Member...
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Exclude Member -->
<div class="modal fade" id="modalExcludeMember" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="<?= url('messages/exclude-member') ?>" method="POST" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="message_id" value="<?= $message['id'] ?>">
            <input type="hidden" name="member_id" id="excludeMemberId" value="">
            <input type="hidden" name="telegram_user_id" id="excludeTelegramUserId" value="">
            <div class="modal-header bg-light">
                <h6 class="modal-title fw-bold text-dark">
                    <i class="bi bi-person-x text-danger me-1"></i> Exclude Member from Calculation
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">
                    Excluding <strong id="excludeMemberName" class="text-dark"></strong> removes them from this message's response percentage denominator.
                </p>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Reason for Exclusion <span class="text-danger">*</span></label>
                    <input type="text" name="reason" class="form-control form-control-sm" placeholder="e.g. On annual leave, sick leave, off duty" required>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm btn-danger">
                    <i class="bi bi-check-lg me-1"></i> Exclude Member
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Manually Mark as Replied -->
<div class="modal fade" id="modalManualReply" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="<?= url('messages/manual-reply') ?>" method="POST" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="message_id" value="<?= $message['id'] ?>">
            <input type="hidden" name="member_id" id="manualReplyMemberId" value="">
            <input type="hidden" name="telegram_user_id" id="manualReplyUserId" value="">
            <div class="modal-header bg-light">
                <h6 class="modal-title fw-bold text-dark">
                    <i class="bi bi-check2-circle text-success me-1"></i> Manually Mark as Replied
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">
                    Manually confirm that <strong id="manualReplyMemberName" class="text-dark"></strong> replied (e.g. via phone call or direct message).
                </p>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Response Note / Summary</label>
                    <input type="text" name="note" class="form-control form-control-sm" placeholder="e.g. Confirmed verbally by phone" value="Confirmed verbally / offline">
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm btn-success">
                    <i class="bi bi-check-lg me-1"></i> Save as Replied
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Hidden Unreplied Mentions Text -->
<input type="hidden" id="rawUnrepliedMentions" value="<?= e($unrepliedString) ?>">

<script>
// Live Real-Time Polling Engine
let isLiveSyncActive = true;
const messageId = <?= (int)$message['id'] ?>;
const liveDetailsUrl = '<?= url("api/messages/live-details?id=" . $message['id']) ?>';
let syncInterval = null;

function startLiveSync() {
    if (syncInterval) clearInterval(syncInterval);
    syncInterval = setInterval(fetchLiveDetails, 3500);
}

function stopLiveSync() {
    if (syncInterval) clearInterval(syncInterval);
    syncInterval = null;
}

document.getElementById('btnToggleSync').addEventListener('click', function() {
    isLiveSyncActive = !isLiveSyncActive;
    const dot = document.getElementById('livePulseDot');
    const text = document.getElementById('liveStatusText');
    const icon = document.getElementById('syncIcon');

    if (isLiveSyncActive) {
        dot.classList.remove('paused');
        text.innerText = 'Live Sync';
        icon.className = 'bi bi-pause-circle';
        startLiveSync();
    } else {
        dot.classList.add('paused');
        text.innerText = 'Sync Paused';
        icon.className = 'bi bi-play-circle';
        stopLiveSync();
    }
});

async function fetchLiveDetails() {
    if (!isLiveSyncActive) return;

    try {
        const response = await fetch(liveDetailsUrl, {
            headers: { 'Accept': 'application/json' }
        });
        if (!response.ok) return;

        const data = await response.json();
        if (!data.ok) return;

        // 1. Update Sync Time
        document.getElementById('liveSyncTime').innerText = data.synced_at || 'Just now';

        // 2. Update Stats Counters
        const stats = data.stats;
        document.getElementById('statEligible').innerText = stats.eligible;
        document.getElementById('statReplied').innerText = stats.replied;
        document.getElementById('statNotReplied').innerText = stats.not_replied;
        document.getElementById('statPending').innerText = stats.pending_review + stats.unknown;

        // 3. Update Progress Bar & Percentage Badge
        const pct = stats.percentage;
        const pctColor = pct >= 80 ? 'success' : (pct >= 40 ? 'warning' : 'danger');
        const badge = document.getElementById('headerPercentageBadge');
        badge.className = `badge bg-${pctColor}-subtle text-${pctColor} border fs-6 fw-bold px-3 py-1`;
        badge.innerText = `${pct}% Complete`;

        const denom = stats.denominator || 1;
        const repWidth = Math.min(100, Math.round((stats.replied / denom) * 100));
        const pendWidth = Math.min(100, Math.round((stats.pending_review / denom) * 100));
        document.getElementById('barReplied').style.width = repWidth + '%';
        document.getElementById('barPending').style.width = pendWidth + '%';

        // 4. Update Member Rows & Detect New Responses
        let unrepliedList = [];
        let countReplied = 0, countNotReplied = 0, countPending = 0, countOther = 0;

        data.members.forEach(m => {
            const uid = m.telegram_user_id;
            const row = document.getElementById('row_' + uid);

            if (m.status === 'replied') countReplied++;
            else if (m.status === 'not_replied') {
                countNotReplied++;
                unrepliedList.push(m.username ? '@' + m.username : m.full_name);
            } else if (m.status === 'pending_review') countPending++;
            else countOther++;

            if (row) {
                const prevStatus = row.getAttribute('data-status');
                if (prevStatus !== m.status) {
                    // Status changed live! Flash row
                    row.setAttribute('data-status', m.status);
                    row.classList.add('row-highlight-new');
                    setTimeout(() => row.classList.remove('row-highlight-new'), 2500);

                    // Update Status Badge
                    const statusBadgeMap = {
                        'replied': 'badge-status-replied',
                        'not_replied': 'badge-status-not_replied',
                        'pending_review': 'badge-status-pending_review',
                        'unknown': 'badge-status-unknown',
                        'excluded': 'badge-status-excluded'
                    };
                    const statusLabelMap = {
                        'replied': 'Replied',
                        'not_replied': 'Not Replied',
                        'pending_review': 'Pending Review',
                        'unknown': 'Unknown',
                        'excluded': 'Excluded'
                    };

                    const statusCell = row.querySelector('.status-col');
                    if (statusCell) {
                        statusCell.innerHTML = `<span class="badge ${statusBadgeMap[m.status] || 'badge-status-unknown'} px-2 py-1">${statusLabelMap[m.status] || m.status}</span>`;
                    }

                    // Update Text Cell
                    const textCell = row.querySelector('.text-col');
                    if (textCell && m.response_text) {
                        textCell.innerHTML = `
                            <div class="speech-bubble ${m.status === 'replied' ? 'replied' : ''}">
                                <div class="small text-dark text-break">${m.display_text}</div>
                                ${m.response_telegram_message_id ? `<span class="text-muted" style="font-size: 0.65rem;">Msg #${m.response_telegram_message_id}</span>` : ''}
                            </div>
                        `;
                    }

                    // Update Date Cell
                    const dateCell = row.querySelector('.date-col');
                    if (dateCell && m.formatted_date) {
                        dateCell.innerText = m.formatted_date;
                    }

                    // Update Method Cell
                    const methodCell = row.querySelector('.method-col');
                    if (methodCell && m.matching_method) {
                        methodCell.innerHTML = `<span class="badge bg-light text-dark border small text-capitalize">${m.matching_method.replace('_', ' ')}</span>`;
                    }
                }
            }
        });

        // Update Tab Badges
        document.getElementById('tabCountAll').innerText = data.members.length;
        document.getElementById('tabCountReplied').innerText = countReplied;
        document.getElementById('tabCountNotReplied').innerText = countNotReplied;
        document.getElementById('tabCountPending').innerText = countPending;
        document.getElementById('tabCountOther').innerText = countOther;

        // Update Raw Mentions
        document.getElementById('rawUnrepliedMentions').value = unrepliedList.join(' ');

    } catch (e) {
        console.warn('Live sync poll error:', e);
    }
}

// Start live sync on page load
startLiveSync();

// Copy Unreplied List to Clipboard
function copyUnrepliedList() {
    const raw = document.getElementById('rawUnrepliedMentions').value.trim();
    if (!raw) {
        alert('All members have already replied or none are pending!');
        return;
    }
    navigator.clipboard.writeText(raw).then(() => {
        alert('Copied unreplied members to clipboard:\n\n' + raw + '\n\nYou can paste this into Telegram!');
    }).catch(err => {
        prompt('Copy unreplied members:', raw);
    });
}

// Filter Tabs
let activeFilter = 'all';
function filterMembers(filter) {
    activeFilter = filter;
    const tabs = document.querySelectorAll('#filterTabs .nav-link');
    tabs.forEach(t => t.classList.remove('active'));
    event.currentTarget.classList.add('active');

    const rows = document.querySelectorAll('#membersTableBody tr[data-status]');
    rows.forEach(r => {
        const status = r.getAttribute('data-status');
        if (filter === 'all') {
            r.style.display = '';
        } else if (filter === 'other') {
            r.style.display = (status === 'unknown' || status === 'excluded') ? '' : 'none';
        } else {
            r.style.display = (status === filter) ? '' : 'none';
        }
    });
}

// Live Search Filter
function searchMembers() {
    const q = document.getElementById('memberSearchInput').value.toLowerCase().trim();
    const rows = document.querySelectorAll('#membersTableBody tr[data-status]');

    rows.forEach(r => {
        const text = r.innerText.toLowerCase();
        const matchesSearch = !q || text.includes(q);

        const status = r.getAttribute('data-status');
        let matchesFilter = true;
        if (activeFilter === 'other') {
            matchesFilter = (status === 'unknown' || status === 'excluded');
        } else if (activeFilter !== 'all') {
            matchesFilter = (status === activeFilter);
        }

        r.style.display = (matchesSearch && matchesFilter) ? '' : 'none';
    });
}

// Modal Openers
function openExcludeModal(memberId, userId, name) {
    document.getElementById('excludeMemberId').value = memberId;
    document.getElementById('excludeTelegramUserId').value = userId;
    document.getElementById('excludeMemberName').innerText = name;
    new bootstrap.Modal(document.getElementById('modalExcludeMember')).show();
}

function openManualReplyModal(memberId, userId, name) {
    document.getElementById('manualReplyMemberId').value = memberId;
    document.getElementById('manualReplyUserId').value = userId;
    document.getElementById('manualReplyMemberName').innerText = name;
    new bootstrap.Modal(document.getElementById('modalManualReply')).show();
}
</script>
