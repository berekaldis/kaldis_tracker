<?php
use App\Services\AuthService;
$isOwner = AuthService::isOwner();
?>
<div class="row align-items-center mb-4">
    <div class="col-md-7">
        <h3 class="fw-bold mb-1" style="color: var(--kaldis-primary);">Registered Telegram Groups</h3>
        <p class="text-muted small mb-0">Manage monitored groups, verify bot permissions, and run health checks.</p>
    </div>
    <div class="col-md-5 text-md-end mt-3 mt-md-0">
        <?php if ($isOwner): ?>
            <button type="button" class="btn btn-kaldis shadow-sm" data-bs-toggle="modal" data-bs-target="#modalRegisterGroup">
                <i class="bi bi-plus-circle me-1"></i> Register New Group
            </button>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Group Title &amp; ID</th>
                    <th class="text-center">Bot Status</th>
                    <th class="text-center">Admin Rights</th>
                    <th class="text-center">Monitoring</th>
                    <th class="text-center">Members</th>
                    <th class="text-center">Tracked Msgs</th>
                    <th>Last Update (EAT)</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($groups)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-chat-square-quote fs-1 d-block mb-2 text-secondary"></i>
                            <div>No Telegram groups registered yet.</div>
                            <?php if ($isOwner): ?>
                                <button type="button" class="btn btn-sm btn-kaldis mt-2" data-bs-toggle="modal" data-bs-target="#modalRegisterGroup">
                                    Register First Group
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($groups as $g): ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-dark"><?= e($g['title']) ?></div>
                                <div class="font-monospace small text-muted">
                                    ID: <?= e($g['telegram_chat_id']) ?>
                                    <?php if (!empty($g['username'])): ?>
                                        &bull; @<?= e($g['username']) ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-center">
                                <?php if ($g['bot_status'] === 'active'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                        <i class="bi bi-check-circle me-1"></i> In Group
                                    </span>
                                <?php elseif ($g['bot_status'] === 'restricted'): ?>
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle">
                                        <i class="bi bi-exclamation-triangle me-1"></i> Restricted
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                        <?= e($g['bot_status']) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($g['bot_is_admin']): ?>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle" title="Can track chat_member events">
                                        <i class="bi bi-shield-check"></i> Bot Admin
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary" title="Recommended to promote bot to admin for full roster updates">
                                        Member
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($g['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Paused</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center fw-semibold">
                                <?= number_format($g['member_count']) ?>
                            </td>
                            <td class="text-center fw-semibold">
                                <?= number_format($g['tracked_count']) ?>
                            </td>
                            <td>
                                <span class="small text-muted">
                                    <?= format_tz($g['last_update_at'] ?? $g['created_at']) ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <a href="<?= url('groups/view?id=' . $g['id']) ?>" class="btn btn-sm btn-outline-dark" title="View Members">
                                        <i class="bi bi-people"></i> Roster
                                    </a>

                                    <?php if ($isOwner): ?>
                                        <form method="POST" action="<?= url('groups/health-check') ?>" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="group_id" value="<?= $g['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-primary" title="Test Bot API Health Check">
                                                <i class="bi bi-activity"></i>
                                            </button>
                                        </form>

                                        <form method="POST" action="<?= url('groups/toggle-active') ?>" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="group_id" value="<?= $g['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-<?= $g['is_active'] ? 'warning' : 'success' ?>" title="<?= $g['is_active'] ? 'Pause Monitoring' : 'Resume Monitoring' ?>">
                                                <i class="bi bi-<?= $g['is_active'] ? 'pause-circle' : 'play-circle' ?>"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Register Group -->
<?php if ($isOwner): ?>
<div class="modal fade" id="modalRegisterGroup" tabindex="-1" aria-labelledby="modalRegisterGroupLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="<?= url('groups/register') ?>" class="modal-content">
            <?= csrf_field() ?>
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="modalRegisterGroupLabel">
                    <i class="bi bi-plus-circle text-primary me-2"></i> Register Telegram Group
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2 px-3 small">
                    <i class="bi bi-info-circle-fill me-1"></i> Make sure the bot is already added to the Telegram group before registering.
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Telegram Chat ID <span class="text-danger">*</span></label>
                    <input type="text" name="telegram_chat_id" class="form-control font-monospace" required placeholder="e.g. -1001928374650">
                    <div class="form-text small">
                        Standard Telegram group chat IDs are negative numbers (supergroups start with <code>-100</code>).
                    </div>
                </div>

                <div class="card bg-light border-0 p-3 small text-muted">
                    <strong>How to find group chat ID:</strong>
                    <ul class="mb-0 ps-3 mt-1">
                        <li>Forward any message from the group to <code>@userinfobot</code> or <code>@RawDataBot</code>.</li>
                        <li>Or add the bot to the group and send <code>/track</code>; the webhook captures the chat ID automatically.</li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-kaldis btn-sm">
                    <i class="bi bi-check-lg me-1"></i> Verify &amp; Register
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
