<div class="row align-items-center mb-4">
    <div class="col-md-8">
        <div class="d-flex align-items-center">
            <a href="<?= url('groups') ?>" class="btn btn-outline-secondary btn-sm me-3">
                <i class="bi bi-arrow-left"></i> Groups
            </a>
            <div>
                <h3 class="fw-bold mb-0" style="color: var(--kaldis-primary);"><?= e($group['title']) ?></h3>
                <span class="font-monospace small text-muted">Telegram Chat ID: <?= e($group['telegram_chat_id']) ?></span>
                <?php if (!empty($group['username'])): ?>
                    <span class="badge bg-light text-secondary border ms-2">@<?= e($group['username']) ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-4 text-md-end mt-3 mt-md-0">
        <span class="badge bg-<?= $group['is_active'] ? 'success' : 'secondary' ?> fs-6 me-2">
            <?= $group['is_active'] ? 'Monitoring Active' : 'Monitoring Paused' ?>
        </span>
        <span class="badge bg-<?= $group['bot_status'] === 'active' ? 'primary' : 'warning text-dark' ?> fs-6">
            Bot: <?= ucfirst(e($group['bot_status'])) ?>
        </span>
    </div>
</div>

<!-- Group Info Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm p-3">
            <div class="small text-muted text-uppercase fw-semibold">Known Roster Members</div>
            <div class="fs-3 fw-bold text-dark"><?= count($members) ?></div>
            <div class="small text-muted">Imported + Seen in Group</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm p-3">
            <div class="small text-muted text-uppercase fw-semibold">Bot Admin Privileges</div>
            <div class="fs-3 fw-bold <?= $group['bot_is_admin'] ? 'text-success' : 'text-secondary' ?>">
                <?= $group['bot_is_admin'] ? 'Administrator' : 'Standard Member' ?>
            </div>
            <div class="small text-muted">
                <?= $group['bot_is_admin'] ? 'Receives join/leave member updates' : 'Cannot detect member join/leave' ?>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm p-3">
            <div class="small text-muted text-uppercase fw-semibold">Last Activity Recorded</div>
            <div class="fs-5 fw-bold text-dark mt-1">
                <?= format_tz($group['last_update_at'] ?? $group['created_at']) ?>
            </div>
            <div class="small text-muted">Africa/Addis_Ababa</div>
        </div>
    </div>
</div>

<!-- Known Members Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0 fw-bold" style="color: var(--kaldis-primary);">
            <i class="bi bi-people-fill me-2"></i> Known Group Members
        </h5>
        <span class="badge bg-light text-secondary border">Total: <?= count($members) ?></span>
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
                            <small>Members will appear automatically as they send messages or when a CSV roster is imported.</small>
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
                                <span class="badge bg-<?= $m['source'] === 'imported' ? 'info text-dark' : 'secondary-subtle text-secondary' ?>">
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
