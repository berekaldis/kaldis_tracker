<div class="row align-items-center mb-4">
    <div class="col-md-7">
        <h3 class="fw-bold mb-1" style="color: var(--kaldis-primary);">Telegram Bot Configuration</h3>
        <p class="text-muted small mb-0">Manage bot credentials, authorized senders, and one-click webhook registration.</p>
    </div>
    <div class="col-md-5 text-md-end mt-3 mt-md-0">
        <span class="badge bg-warning text-dark border px-3 py-2">
            <i class="bi bi-shield-lock-fill me-1"></i> Super Admin Privileges
        </span>
    </div>
</div>

<!-- Bot Live Health & Webhook Status Cards -->
<div class="row g-3 mb-4">
    <!-- Bot API Status Card -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="card-title mb-0 fw-bold text-dark">
                    <i class="bi bi-robot text-primary me-2"></i> Bot Connection Status
                </h6>
                <?php if (!empty($botInfo['ok'])): ?>
                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                        <i class="bi bi-check-circle-fill me-1"></i> Connected
                    </span>
                <?php else: ?>
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                        <i class="bi bi-x-circle-fill me-1"></i> Not Connected
                    </span>
                <?php endif; ?>
            </div>
            <div class="card-body p-3">
                <?php if (!empty($botInfo['ok'])): ?>
                    <?php $b = $botInfo['result']; ?>
                    <div class="d-flex align-items-center mb-3 p-2 bg-light rounded">
                        <div class="fs-1 me-3">🤖</div>
                        <div>
                            <div class="fw-bold text-dark fs-6"><?= e($b['first_name'] ?? 'Bot') ?></div>
                            <div class="text-primary small">@<?= e($b['username'] ?? '') ?> &bull; ID: <code><?= e($b['id'] ?? '') ?></code></div>
                        </div>
                    </div>

                    <div class="small">
                        <div class="d-flex justify-content-between py-1 border-bottom">
                            <span class="text-muted">Can Join Groups:</span>
                            <span class="fw-semibold <?= !empty($b['can_join_groups']) ? 'text-success' : 'text-danger' ?>">
                                <?= !empty($b['can_join_groups']) ? 'Yes' : 'No' ?>
                            </span>
                        </div>
                        <div class="d-flex justify-content-between py-1 border-bottom">
                            <span class="text-muted">Group Privacy Mode:</span>
                            <?php if (!empty($b['can_read_all_group_messages'])): ?>
                                <span class="badge bg-success">Disabled (Can Read Messages)</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark" title="Disable privacy mode in @BotFather via /setprivacy">
                                    Enabled (Must Disable in BotFather)
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-exclamation-circle fs-2 text-warning d-block mb-2"></i>
                        <div>Bot token is missing or invalid.</div>
                        <small class="text-muted"><?= e($botInfo['description'] ?? 'Please configure your Bot Token below.') ?></small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Webhook Live Status Card -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="card-title mb-0 fw-bold text-dark">
                    <i class="bi bi-broadcast text-primary me-2"></i> Telegram Webhook Status
                </h6>
                <?php if (!empty($webhookInfo['ok']) && !empty($webhookInfo['result']['url'])): ?>
                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                        <i class="bi bi-link-45deg me-1"></i> Active
                    </span>
                <?php else: ?>
                    <span class="badge bg-secondary-subtle text-secondary border">
                        No Webhook Registered
                    </span>
                <?php endif; ?>
            </div>
            <div class="card-body p-3">
                <?php if (!empty($webhookInfo['ok'])): ?>
                    <?php $w = $webhookInfo['result']; ?>
                    <div class="small">
                        <div class="mb-2">
                            <span class="text-muted d-block">Registered Webhook URL:</span>
                            <code class="text-break bg-light p-1 rounded d-block mt-1">
                                <?= !empty($w['url']) ? e($w['url']) : 'None (updates will not be received)' ?>
                            </code>
                        </div>
                        <div class="d-flex justify-content-between py-1 border-bottom">
                            <span class="text-muted">Pending Update Count:</span>
                            <span class="fw-bold <?= ($w['pending_update_count'] ?? 0) > 0 ? 'text-warning' : 'text-success' ?>">
                                <?= number_format($w['pending_update_count'] ?? 0) ?>
                            </span>
                        </div>
                        <?php if (!empty($w['last_error_message'])): ?>
                            <div class="alert alert-danger py-2 px-3 mt-2 small mb-0">
                                <strong>Last Delivery Error:</strong><br>
                                <?= e($w['last_error_message']) ?>
                                <?php if (!empty($w['last_error_date'])): ?>
                                    <div class="text-muted mt-1">At: <?= date('Y-m-d H:i:s', $w['last_error_date']) ?> UTC</div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex gap-2 mt-3">
                        <form action="<?= url('settings/set-webhook') ?>" method="POST" class="flex-grow-1">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-kaldis w-100" <?= empty($botToken) ? 'disabled' : '' ?>>
                                <i class="bi bi-cloud-arrow-up me-1"></i> Register / Update Webhook
                            </button>
                        </form>
                        <?php if (!empty($w['url'])): ?>
                            <form action="<?= url('settings/delete-webhook') ?>" method="POST" onsubmit="return confirm('Are you sure you want to delete the Telegram webhook?');">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove Webhook">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 text-muted">
                        <div>Configure Bot Token first to inspect webhook status.</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Bot Settings Form Card -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="card-title mb-0 fw-bold" style="color: var(--kaldis-primary);">
            <i class="bi bi-sliders me-2"></i> Bot Credentials &amp; Tracking Configuration
        </h5>
    </div>
    <div class="card-body p-4">
        <form method="POST" action="<?= url('settings/bot') ?>">
            <?= csrf_field() ?>

            <!-- Conversation Tracking Mode Selection -->
            <div class="mb-4 p-3 rounded border bg-light bg-opacity-50">
                <label class="form-label fw-bold text-dark mb-2 d-flex align-items-center">
                    <i class="bi bi-chat-dots-fill text-primary me-2 fs-5"></i> Conversation Tracking Mode
                </label>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-check p-3 bg-white rounded border h-100 d-block cursor-pointer <?= $trackingMode === 'all_authorized' ? 'border-primary shadow-sm bg-primary-subtle' : '' ?>" style="cursor: pointer;">
                            <input class="form-check-input ms-0 me-2" type="radio" name="tracking_mode" id="mode_all_authorized" value="all_authorized" <?= $trackingMode === 'all_authorized' ? 'checked' : '' ?>>
                            <span class="fw-semibold text-dark">
                                Track Each Conversation
                                <?php if ($trackingMode === 'all_authorized'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle ms-1">Active</span>
                                <?php endif; ?>
                            </span>
                            <span class="d-block text-muted small mt-2">
                                Automatically tracks <strong>every message</strong> sent by authorized sender(s) in monitored groups as a conversation. No need to type <code>#track</code>.
                            </span>
                        </label>
                    </div>
                    <div class="col-md-4">
                        <label class="form-check p-3 bg-white rounded border h-100 d-block cursor-pointer <?= $trackingMode === 'trigger_only' ? 'border-primary shadow-sm' : '' ?>" style="cursor: pointer;">
                            <input class="form-check-input ms-0 me-2" type="radio" name="tracking_mode" id="mode_trigger_only" value="trigger_only" <?= $trackingMode === 'trigger_only' ? 'checked' : '' ?>>
                            <span class="fw-semibold text-dark">
                                Trigger Only (#track / /track)
                                <?php if ($trackingMode === 'trigger_only'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle ms-1">Active</span>
                                <?php endif; ?>
                            </span>
                            <span class="d-block text-muted small mt-2">
                                Only tracks messages that explicitly contain the <code>#track</code> hashtag or are replied to with the <code>/track</code> command.
                            </span>
                        </label>
                    </div>
                    <div class="col-md-4">
                        <label class="form-check p-3 bg-white rounded border h-100 d-block cursor-pointer <?= $trackingMode === 'all_messages' ? 'border-primary shadow-sm' : '' ?>" style="cursor: pointer;">
                            <input class="form-check-input ms-0 me-2" type="radio" name="tracking_mode" id="mode_all_messages" value="all_messages" <?= $trackingMode === 'all_messages' ? 'checked' : '' ?>>
                            <span class="fw-semibold text-dark">
                                Track All Group Messages
                                <?php if ($trackingMode === 'all_messages'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle ms-1">Active</span>
                                <?php endif; ?>
                            </span>
                            <span class="d-block text-muted small mt-2">
                                Automatically tracks every top-level message posted by any member in monitored groups.
                            </span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Telegram Bot Token <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-key"></i></span>
                        <input type="password" id="botTokenInput" name="bot_token" class="form-control font-monospace" value="<?= e($botToken) ?>" placeholder="1234567890:ABCdefGhIjk..." required>
                        <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('botTokenInput')">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <div class="form-text small">Obtained from <code>@BotFather</code> when creating the bot.</div>
                </div>

                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Bot Username (without @)</label>
                    <div class="input-group">
                        <span class="input-group-text">@</span>
                        <input type="text" name="bot_username" class="form-control" value="<?= e($botUsername) ?>" placeholder="kaldis_reply_tracker_bot">
                    </div>
                    <div class="form-text small">Your bot's public username on Telegram.</div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Webhook Secret Token <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-shield-lock"></i></span>
                        <input type="text" id="secretTokenInput" name="webhook_secret" class="form-control font-monospace" value="<?= e($webhookSecret) ?>" placeholder="SecretToken123" required>
                        <button class="btn btn-outline-secondary" type="button" onclick="generateSecretToken()">
                            <i class="bi bi-shuffle me-1"></i> Generate
                        </button>
                    </div>
                    <div class="form-text small">Sent in <code>X-Telegram-Bot-Api-Secret-Token</code> to verify authenticity.</div>
                </div>

                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Public HTTPS Webhook URL <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-link"></i></span>
                        <input type="url" name="webhook_url" class="form-control font-monospace" value="<?= e($webhookUrl) ?>" placeholder="https://yourdomain.com/webhook.php" required>
                    </div>
                    <div class="form-text small">Must be a public <strong>HTTPS</strong> URL accessible by Telegram servers.</div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Authorized Sender Telegram User ID(s) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person-check"></i></span>
                        <input type="text" name="authorized_user_ids" class="form-control font-monospace" value="<?= e($authUsersStr) ?>" placeholder="123456789, 987654321" required>
                    </div>
                    <div class="form-text small">
                        Comma-separated numeric Telegram User IDs. Messages from these users will be tracked. Find your ID by messaging <code>@userinfobot</code>.
                    </div>
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Track Trigger Hashtag</label>
                    <div class="input-group">
                        <span class="input-group-text">#</span>
                        <input type="text" name="track_hashtag" class="form-control" value="<?= e(ltrim($trackHashtag, '#')) ?>" placeholder="track">
                    </div>
                    <div class="form-text small">e.g. <code>#track</code></div>
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Track Reply Command</label>
                    <div class="input-group">
                        <span class="input-group-text">/</span>
                        <input type="text" name="track_command" class="form-control" value="<?= e(ltrim($trackCommand, '/')) ?>" placeholder="track">
                    </div>
                    <div class="form-text small">e.g. <code>/track</code></div>
                </div>
            </div>

            <div class="border-top pt-3 d-flex justify-content-end gap-2">
                <button type="submit" class="btn btn-kaldis shadow-sm px-4">
                    <i class="bi bi-check2-circle me-1"></i> Save Configuration
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function togglePasswordVisibility(id) {
    const el = document.getElementById(id);
    el.type = el.type === 'password' ? 'text' : 'password';
}

function generateSecretToken() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_-';
    let token = '';
    for (let i = 0; i < 32; i++) {
        token += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('secretTokenInput').value = 'Kaldis_' + token;
}
</script>
