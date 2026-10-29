<?php
use App\Services\AuthService;
$isOwner = AuthService::isOwner();
?>
<div class="row align-items-center mb-4">
    <div class="col-md-6">
        <div class="d-flex align-items-center">
            <a href="<?= url('groups') ?>" class="btn btn-outline-secondary btn-sm me-3">
                <i class="bi bi-arrow-left"></i> Groups
            </a>
            <div>
                <h3 class="fw-bold mb-0" style="color: var(--kaldis-primary);"><?= e($group['title']) ?></h3>
                <span class="font-monospace small text-muted">Chat ID: <?= e($group['telegram_chat_id']) ?></span>
                <?php if (!empty($group['username'])): ?>
                    <span class="badge bg-light text-secondary border ms-1">@<?= e($group['username']) ?></span>
                <?php endif; ?>
                <span class="badge bg-amber-subtle text-dark border border-warning ms-1">
                    <i class="bi bi-building me-1"></i><?= e($group['department'] ?? 'General') ?>
                </span>
            </div>
        </div>
    </div>
    <div class="col-md-6 text-md-end mt-3 mt-md-0 d-flex flex-wrap align-items-center justify-content-md-end gap-2">
        <?php if ($isOwner): ?>
            <!-- Sync Admins Button -->
            <form method="POST" action="<?= url('groups/sync-admins') ?>" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                <input type="hidden" name="redirect_to_view" value="1">
                <button type="submit" class="btn btn-primary btn-sm shadow-sm" title="Pull Creators and Administrators directly from Telegram">
                    <i class="bi bi-arrow-repeat me-1"></i> Sync Admins from Telegram
                </button>
            </form>

            <!-- Add Member Button -->
            <button type="button" class="btn btn-kaldis btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAddMember">
                <i class="bi bi-person-plus-fill me-1"></i> Add Member
            </button>
        <?php endif; ?>

        <span class="badge bg-<?= $group['is_active'] ? 'success' : 'secondary' ?> fs-6">
            <?= $group['is_active'] ? 'Active' : 'Paused' ?>
        </span>
    </div>
</div>

<!-- Group Info Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm p-3 h-100">
            <div class="small text-muted text-uppercase fw-semibold">Tracked in System Roster</div>
            <div class="fs-3 fw-bold text-dark"><?= count($members) ?></div>
            <div class="small text-muted">Members recorded in database</div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm p-3 h-100">
            <div class="small text-muted text-uppercase fw-semibold">Total Members on Telegram</div>
            <div class="fs-3 fw-bold text-primary">
                <?= $telegramTotalMembers !== null ? number_format($telegramTotalMembers) : '—' ?>
            </div>
            <div class="small text-muted">Live Telegram group count</div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm p-3 h-100">
            <div class="small text-muted text-uppercase fw-semibold">Bot Privileges</div>
            <div class="fs-4 fw-bold <?= $group['bot_is_admin'] ? 'text-success' : 'text-secondary' ?> mt-1">
                <?= $group['bot_is_admin'] ? 'Administrator' : 'Standard Member' ?>
            </div>
            <div class="small text-muted">
                <?= $group['bot_is_admin'] ? 'Can detect member join/leave' : 'Promote bot to admin for full updates' ?>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm p-3 h-100">
            <div class="small text-muted text-uppercase fw-semibold">Department Assignment</div>
            <div class="fs-4 fw-bold text-dark mt-1">
                <?= e($group['department'] ?? 'General') ?>
            </div>
            <div class="small text-muted">Isolated access control scope</div>
        </div>
    </div>
</div>

<!-- Explanatory Notice: How Telegram Member Discovery Works -->
<div class="alert alert-info border-info-subtle shadow-sm mb-4">
    <div class="d-flex align-items-start">
        <div class="fs-3 text-info me-3 mt-1"><i class="bi bi-info-circle-fill"></i></div>
        <div>
            <h6 class="fw-bold mb-1 text-dark">Why aren't all 25 members listed immediately?</h6>
            <p class="small text-secondary mb-2">
                Telegram's strict privacy policy <strong>does not allow any bot to download a group's full member list at once</strong>. Instead, members are populated in the system using the following methods:
            </p>
            <div class="row g-2 small">
                <div class="col-md-4">
                    <div class="p-2 bg-white rounded border h-100">
                        <strong class="text-primary"><i class="bi bi-arrow-repeat me-1"></i> 1. Sync Admins (Instant)</strong>
                        <div class="text-muted mt-1">Click <strong>Sync Admins from Telegram</strong> to immediately import all group creators, managers, and administrators.</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-2 bg-white rounded border h-100">
                        <strong class="text-success"><i class="bi bi-chat-dots me-1"></i> 2. Send Any Message (Auto)</strong>
                        <div class="text-muted mt-1">Ask the team to send a quick greeting (e.g. <em>"Hi team"</em>). Each member is <strong>automatically registered forever</strong> the instant they send a message.</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-2 bg-white rounded border h-100">
                        <strong class="text-dark"><i class="bi bi-person-plus me-1"></i> 3. Add Member Manually</strong>
                        <div class="text-muted mt-1">Click <strong>Add Member</strong> to register staff members in advance using their Telegram User ID (from <code>@userinfobot</code>) or username.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Known Members Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0 fw-bold" style="color: var(--kaldis-primary);">
            <i class="bi bi-people-fill me-2"></i> Known Group Members
        </h5>
        <span class="badge bg-light text-secondary border">Total Roster: <?= count($members) ?></span>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Member Name</th>
                    <th>Username</th>
                    <th>Telegram User ID</th>
                    <th class="text-center">Role</th>
                    <th class="text-center">Source</th>
                    <th>First Seen / Joined (EAT)</th>
                    <th class="text-center">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($members)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-person-x fs-1 d-block mb-2 text-secondary"></i>
                            <div>No members recorded for this group yet.</div>
                            <small class="d-block mt-1">Click <strong>Sync Admins from Telegram</strong> above or have team members post a message in the group to populate this list.</small>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($members as $m): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold text-dark"><?= e($m['full_name']) ?></div>
                                <?php if ($m['is_bot']): ?>
                                    <span class="badge bg-secondary-subtle text-secondary" style="font-size: 0.65rem;">BOT</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($m['username'])): ?>
                                    <span class="text-primary">@<?= e($m['username']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="font-monospace small bg-light px-2 py-1 rounded border">
                                    <?= e($m['telegram_user_id']) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-<?= $m['role'] === 'admin' || $m['role'] === 'creator' ? 'primary' : 'light text-dark border' ?> text-capitalize">
                                    <?= e($m['role']) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-<?= $m['source'] === 'admin_added' ? 'info-subtle text-info border border-info-subtle' : 'secondary-subtle text-secondary' ?>">
                                    <?= e($m['source']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="small text-muted"><?= format_tz($m['joined_at']) ?></span>
                            </td>
                            <td class="text-center">
                                <?php if ($m['membership_active']): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Left</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add Member Manually -->
<?php if ($isOwner): ?>
<div class="modal fade" id="modalAddMember" tabindex="-1" aria-labelledby="modalAddMemberLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="<?= url('groups/add-member') ?>" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="modalAddMemberLabel">
                    <i class="bi bi-person-plus-fill text-primary me-2"></i> Add Member to Roster
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Telegram User ID <span class="text-danger">*</span></label>
                    <input type="text" name="telegram_user_id" class="form-control font-monospace" required placeholder="e.g. 814523272">
                    <div class="form-text small">Each Telegram account has a unique numeric ID. Obtainable via <code>@userinfobot</code>.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Full Name</label>
                    <input type="text" name="full_name" class="form-control" placeholder="e.g. Abebe Bikila (Waiter)">
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Telegram Username (without @)</label>
                    <div class="input-group">
                        <span class="input-group-text">@</span>
                        <input type="text" name="username" class="form-control" placeholder="abebikila">
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-kaldis btn-sm">
                    <i class="bi bi-check2 me-1"></i> Add to Roster
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
