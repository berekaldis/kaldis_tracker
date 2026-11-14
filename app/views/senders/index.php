<?php
use App\Services\AuthService;
$canTrack = AuthService::canTrack();
?>

<!-- Page Header -->
<div class="row align-items-center mb-4">
    <div class="col-md-7">
        <h3 class="fw-bold mb-1" style="color: var(--kaldis-primary);">
            <i class="bi bi-person-check-fill me-2"></i> Authorized Senders by Group
        </h3>
        <p class="text-muted small mb-0">
            Designate supervisors and managers whose announcements in Telegram groups will automatically be tracked for member responses.
        </p>
    </div>
    <div class="col-md-5 text-md-end mt-3 mt-md-0">
        <?php if ($canTrack): ?>
            <button type="button" class="btn btn-kaldis shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAddSender">
                <i class="bi bi-person-plus-fill me-1"></i> Add Authorized Sender
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- 3 KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card card-summary p-3 h-100 shadow-sm border-0">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-label">Total Authorized Senders</div>
                    <div class="stat-value text-dark"><?= number_format($totalSenders) ?></div>
                    <div class="small text-muted">Across monitored groups</div>
                </div>
                <div class="stat-icon total">
                    <i class="bi bi-people-fill"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card card-summary p-3 h-100 shadow-sm border-0">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-label">Monitored Groups</div>
                    <div class="stat-value text-primary"><?= number_format($totalGroupsWithSenders) ?></div>
                    <div class="small text-muted">With active senders</div>
                </div>
                <div class="stat-icon replied">
                    <i class="bi bi-collection-fill"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card card-summary p-3 h-100 shadow-sm border-0">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-label">Active Status</div>
                    <div class="stat-value text-success"><?= number_format($activeSenders) ?></div>
                    <div class="small text-muted">Live message triggers</div>
                </div>
                <div class="stat-icon pending">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Card -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="<?= url('senders') ?>" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search by name, @username, or User ID..." value="<?= e($search) ?>">
                </div>
            </div>
            <div class="col-md-4">
                <select name="group_id" class="form-select form-select-sm" onchange="this.form.submit()" <?= $assignedGroupId !== null ? 'disabled' : '' ?>>
                    <option value="">-- All Accessible Groups --</option>
                    <?php foreach ($groups as $g): ?>
                        <option value="<?= $g['id'] ?>" <?= ($selectedGroupId === (int)$g['id']) ? 'selected' : '' ?>>
                            Group: <?= e($g['title']) ?> (<?= e($g['department'] ?? 'General') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($assignedGroupId !== null): ?>
                    <input type="hidden" name="group_id" value="<?= $assignedGroupId ?>">
                <?php endif; ?>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-secondary flex-grow-1">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
                <?php if (!empty($search) || (!empty($selectedGroupId) && $assignedGroupId === null)): ?>
                    <a href="<?= url('senders') ?>" class="btn btn-sm btn-light border" title="Reset Filters">
                        <i class="bi bi-x-lg"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Senders Table Card -->
<div class="card border-0 shadow-sm mb-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="min-width: 220px;">Assigned Telegram Group</th>
                    <th style="min-width: 220px;">Authorized Sender</th>
                    <th style="min-width: 140px;">Telegram User ID</th>
                    <th style="min-width: 160px;">Role / Designation</th>
                    <th class="text-center" style="width: 110px;">Status</th>
                    <th style="min-width: 150px;">Configured (EAT)</th>
                    <th class="text-end" style="width: 140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($senders)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-person-x fs-1 d-block mb-2 text-secondary"></i>
                            <div>No authorized senders found for the selected filter.</div>
                            <?php if ($canTrack): ?>
                                <button type="button" class="btn btn-sm btn-outline-primary mt-2" data-bs-toggle="modal" data-bs-target="#modalAddSender">
                                    <i class="bi bi-plus-lg me-1"></i> Add Sender Now
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($senders as $s): ?>
                        <?php $initials = mb_substr($s['full_name'] ?? 'AS', 0, 2); ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-dark"><?= e($s['group_title']) ?></div>
                                <div class="small text-muted font-monospace">
                                    Chat ID: <code><?= e($s['telegram_chat_id']) ?></code>
                                    &bull; <span class="badge bg-light text-secondary border"><?= e($s['department'] ?? 'General') ?></span>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="member-avatar-circle me-2 bg-primary-subtle text-primary">
                                        <?= e($initials) ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark"><?= e($s['full_name']) ?></div>
                                        <div class="small">
                                            <?php if (!empty($s['username'])): ?>
                                                <a href="https://t.me/<?= e($s['username']) ?>" target="_blank" class="text-decoration-none text-primary">
                                                    @<?= e($s['username']) ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">No Telegram username</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <code class="fs-6 fw-bold text-dark">#<?= e($s['telegram_user_id']) ?></code>
                            </td>
                            <td>
                                <?php if (!empty($s['notes'])): ?>
                                    <span class="badge bg-light text-dark border">
                                        <i class="bi bi-tag me-1"></i><?= e($s['notes']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small">Supervisor</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($s['is_active']): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">Disabled</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted">
                                <?= format_tz($s['created_at']) ?>
                            </td>
                            <td class="text-end">
                                <?php if ($canTrack): ?>
                                    <div class="d-inline-flex gap-1">
                                        <!-- Edit Sender -->
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-primary px-2 py-0 btn-edit-sender" 
                                                title="Edit Authorized Sender"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#modalEditSender"
                                                data-id="<?= (int)$s['id'] ?>"
                                                data-group-id="<?= (int)$s['group_id'] ?>"
                                                data-group-title="<?= e($s['group_title']) ?>"
                                                data-user-id="<?= e($s['telegram_user_id']) ?>"
                                                data-full-name="<?= e($s['full_name']) ?>"
                                                data-username="<?= e($s['username'] ?? '') ?>"
                                                data-notes="<?= e($s['notes'] ?? '') ?>"
                                                data-active="<?= (int)$s['is_active'] ?>">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <!-- Toggle Status -->
                                        <form method="POST" action="<?= url('senders/toggle') ?>" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="sender_id" value="<?= $s['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-<?= $s['is_active'] ? 'secondary' : 'success' ?> px-2 py-0" title="<?= $s['is_active'] ? 'Pause Tracking for this Sender' : 'Activate Sender' ?>">
                                                <i class="bi bi-<?= $s['is_active'] ? 'pause-fill' : 'play-fill' ?>"></i>
                                            </button>
                                        </form>
                                        <!-- Delete Sender -->
                                        <form method="POST" action="<?= url('senders/delete') ?>" class="d-inline" onsubmit="return confirm('Remove this authorized sender from this group?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="sender_id" value="<?= $s['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger px-2 py-0" title="Delete Sender">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
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

<!-- Modal: Add Authorized Sender -->
<?php if ($canTrack): ?>
<div class="modal fade" id="modalAddSender" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="<?= url('senders/create') ?>" class="modal-content">
            <?= csrf_field() ?>
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold text-dark">
                    <i class="bi bi-person-plus-fill text-primary me-2"></i> Add Authorized Sender
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">
                    Authorized senders can post announcements in their designated Telegram group and have them tracked automatically.
                </p>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Target Telegram Group <span class="text-danger">*</span></label>
                    <select name="group_id" id="modalTargetGroup" class="form-select" required onchange="loadGroupMembersForSender(this.value)">
                        <?php if ($assignedGroupId === null): ?>
                            <option value="">-- Select Target Group --</option>
                        <?php endif; ?>
                        <?php foreach ($groups as $g): ?>
                            <option value="<?= $g['id'] ?>" <?= ($selectedGroupId === (int)$g['id'] || count($groups) === 1) ? 'selected' : '' ?>>
                                <?= e($g['title']) ?> (<?= e($g['department'] ?? 'General') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3" id="quickMemberSelectContainer">
                    <label class="form-label small fw-semibold">Quick Pick from Group Members <span class="text-muted">(Optional)</span></label>
                    <select id="modalGroupMemberSelect" class="form-select small font-monospace" onchange="applyQuickMember(this)">
                        <option value="">-- Choose known member in group --</option>
                    </select>
                    <div class="form-text small">Select a member who has already checked in or chatted in the group.</div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-md-7">
                        <label class="form-label small fw-semibold">Telegram Numeric User ID <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text font-monospace">#</span>
                            <input type="text" name="telegram_user_id" id="inputSenderUserId" class="form-control font-monospace" placeholder="e.g. 814523272" required>
                        </div>
                        <div class="form-text small">From Telegram @userinfobot</div>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small fw-semibold">Telegram Username</label>
                        <div class="input-group">
                            <span class="input-group-text">@</span>
                            <input type="text" name="username" id="inputSenderUsername" class="form-control" placeholder="username">
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Sender Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="full_name" id="inputSenderFullName" class="form-control" placeholder="e.g. Abebe Bikila (Airport Supervisor)" required>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Role / Designation Notes</label>
                    <input type="text" name="notes" class="form-control" placeholder="e.g. Airport Morning Shift Supervisor">
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-kaldis btn-sm shadow-sm">
                    <i class="bi bi-check-lg me-1"></i> Save Authorized Sender
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Authorized Sender -->
<div class="modal fade" id="modalEditSender" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="<?= url('senders/update') ?>" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="sender_id" id="editSenderId" value="">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold text-dark">
                    <i class="bi bi-pencil-square text-primary me-2"></i> Edit Authorized Sender
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">
                    Update authorized sender details, assigned Telegram group, or tracking status.
                </p>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Target Telegram Group <span class="text-danger">*</span></label>
                    <select name="group_id" id="editSenderGroupId" class="form-select" required <?= $assignedGroupId !== null ? 'disabled' : '' ?>>
                        <?php foreach ($groups as $g): ?>
                            <option value="<?= $g['id'] ?>">
                                <?= e($g['title']) ?> (<?= e($g['department'] ?? 'General') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($assignedGroupId !== null): ?>
                        <input type="hidden" name="group_id" value="<?= $assignedGroupId ?>">
                    <?php endif; ?>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-md-7">
                        <label class="form-label small fw-semibold">Telegram Numeric User ID <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text font-monospace">#</span>
                            <input type="text" name="telegram_user_id" id="editSenderUserId" class="form-control font-monospace" required>
                        </div>
                        <div class="form-text small">From Telegram @userinfobot</div>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small fw-semibold">Telegram Username</label>
                        <div class="input-group">
                            <span class="input-group-text">@</span>
                            <input type="text" name="username" id="editSenderUsername" class="form-control" placeholder="username">
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Sender Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="full_name" id="editSenderFullName" class="form-control" placeholder="e.g. Abebe Bikila (Airport Supervisor)" required>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Role / Designation Notes</label>
                    <input type="text" name="notes" id="editSenderNotes" class="form-control" placeholder="e.g. Airport Morning Shift Supervisor">
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Tracking Status <span class="text-danger">*</span></label>
                    <select name="is_active" id="editSenderIsActive" class="form-select">
                        <option value="1">Active (Trigger automatic response tracking)</option>
                        <option value="0">Disabled (Pause tracking for this sender)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-kaldis btn-sm shadow-sm">
                    <i class="bi bi-check-lg me-1"></i> Update Authorized Sender
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Dynamic loading of group members into modal quick-select
async function loadGroupMembersForSender(groupId) {
    const select = document.getElementById('modalGroupMemberSelect');
    if (!select || !groupId) return;

    select.innerHTML = '<option value="">Loading members...</option>';

    try {
        const resp = await fetch('<?= url("api/senders/group-members?group_id=") ?>' + encodeURIComponent(groupId));
        if (!resp.ok) return;
        const data = await resp.json();
        if (!data.ok || !data.members) return;

        select.innerHTML = '<option value="">-- Choose known member in group (' + data.members.length + ') --</option>';
        data.members.forEach(m => {
            const opt = document.createElement('option');
            opt.value = m.telegram_user_id;
            opt.setAttribute('data-name', m.full_name);
            opt.setAttribute('data-username', m.username || '');
            opt.innerText = m.full_name + (m.username ? ' (@' + m.username + ')' : '') + ' [ID: ' + m.telegram_user_id + ']';
            select.appendChild(opt);
        });
    } catch (e) {
        select.innerHTML = '<option value="">Failed to load members</option>';
    }
}

function applyQuickMember(select) {
    const opt = select.options[select.selectedIndex];
    if (!opt || !opt.value) return;

    document.getElementById('inputSenderUserId').value = opt.value;
    document.getElementById('inputSenderFullName').value = opt.getAttribute('data-name') || '';
    document.getElementById('inputSenderUsername').value = opt.getAttribute('data-username') || '';
}

// Auto load for currently selected group on open & Edit Sender Button wiring
document.addEventListener('DOMContentLoaded', function() {
    const groupSelect = document.getElementById('modalTargetGroup');
    if (groupSelect && groupSelect.value) {
        loadGroupMembersForSender(groupSelect.value);
    }

    // Populate Edit Sender Modal
    document.querySelectorAll('.btn-edit-sender').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const groupId = this.getAttribute('data-group-id');
            const userId = this.getAttribute('data-user-id');
            const fullName = this.getAttribute('data-full-name');
            const username = this.getAttribute('data-username');
            const notes = this.getAttribute('data-notes');
            const active = this.getAttribute('data-active');

            const idInput = document.getElementById('editSenderId');
            const groupSelect = document.getElementById('editSenderGroupId');
            const userIdInput = document.getElementById('editSenderUserId');
            const nameInput = document.getElementById('editSenderFullName');
            const usernameInput = document.getElementById('editSenderUsername');
            const notesInput = document.getElementById('editSenderNotes');
            const activeSelect = document.getElementById('editSenderIsActive');

            if (idInput) idInput.value = id || '';
            if (groupSelect) groupSelect.value = groupId || '';
            if (userIdInput) userIdInput.value = userId || '';
            if (nameInput) nameInput.value = fullName || '';
            if (usernameInput) usernameInput.value = username || '';
            if (notesInput) notesInput.value = notes || '';
            if (activeSelect) activeSelect.value = active !== undefined ? active : '1';
        });
    });
});
</script>
<?php endif; ?>
