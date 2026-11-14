<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
    <div>
        <h3 class="fw-bold mb-1 text-dark">Telegram Bot Setup</h3>
        <p class="text-muted small mb-0">Connect your Telegram bot to track staff conversations and reply rates.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge rounded-pill bg-warning text-dark border px-3 py-1.5 fw-semibold">
            <i class="bi bi-shield-lock-fill me-1"></i> Super Admin
        </span>
    </div>
</div>

<!-- 1. Connection & Webhook Status Overview Card -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="row align-items-center g-3">
            <div class="col-md-7">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon <?= !empty($botInfo['ok']) ? 'replied' : 'unreplied' ?>" style="width: 44px; height: 44px;">
                        <i class="bi bi-robot fs-4"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="fw-bold text-dark fs-6">
                                <?= !empty($botInfo['ok']) ? e($botInfo['result']['first_name'] ?? 'Telegram Bot') : 'Bot Not Connected' ?>
                            </span>
                            <?php if (!empty($botInfo['ok'])): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">
                                    <i class="bi bi-check-circle-fill me-1"></i> Online (@<?= e($botInfo['result']['username'] ?? '') ?>)
                                </span>
                            <?php else: ?>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill">
                                    Token Required
                                </span>
                            <?php endif; ?>

                            <?php if (!empty($webhookInfo['ok']) && !empty($webhookInfo['result']['url'])): ?>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill">
                                    <i class="bi bi-link-45deg me-1"></i> Webhook Active
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="text-muted small mt-1">
                            <?php if (!empty($botInfo['ok']) && !empty($webhookInfo['ok']) && !empty($webhookInfo['result']['url'])): ?>
                                Ready &bull; Bot is listening for group messages and replies in real time.
                            <?php elseif (empty($botToken)): ?>
                                Paste your Bot Token below to activate monitoring.
                            <?php else: ?>
                                Click <strong>Sync Webhook</strong> to connect Telegram to this server.
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-5 text-md-end d-flex align-items-center justify-content-md-end gap-2">
                <form action="<?= url('settings/set-webhook') ?>" method="POST" class="d-inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-kaldis btn-sm shadow-sm" <?= empty($botToken) ? 'disabled' : '' ?> title="Register or refresh webhook URL with Telegram">
                        <i class="bi bi-arrow-repeat me-1"></i> Sync / Register Webhook
                    </button>
                </form>
                <?php if (!empty($webhookInfo['ok']) && !empty($webhookInfo['result']['url'])): ?>
                    <form action="<?= url('settings/delete-webhook') ?>" method="POST" class="d-inline" onsubmit="return confirm('Disconnect webhook from Telegram?');">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-outline-danger btn-sm shadow-sm" title="Remove Webhook">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Collapsible Technical Diagnostics (hidden unless needed) -->
        <?php if (!empty($webhookInfo['ok']) || !empty($botInfo['ok'])): ?>
            <div class="mt-3 pt-3 border-top">
                <details>
                    <summary class="small text-muted cursor-pointer" style="cursor: pointer;">
                        <i class="bi bi-gear-wide-connected me-1"></i> View Technical Diagnostics &amp; API Details
                    </summary>
                    <div class="row g-2 mt-2 font-monospace small text-muted bg-light p-3 rounded">
                        <div class="col-md-6">
                            <strong>Bot ID:</strong> <?= e($botInfo['result']['id'] ?? '—') ?><br>
                            <strong>Can Join Groups:</strong> <?= !empty($botInfo['result']['can_join_groups']) ? 'Yes' : 'No' ?><br>
                            <strong>Group Privacy Mode:</strong> <?= !empty($botInfo['result']['can_read_all_group_messages']) ? 'Disabled (Can read all messages)' : 'Enabled' ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Registered URL:</strong> <?= e($webhookInfo['result']['url'] ?? 'None') ?><br>
                            <strong>Pending Updates:</strong> <?= number_format($webhookInfo['result']['pending_update_count'] ?? 0) ?><br>
                            <?php if (!empty($webhookInfo['result']['last_error_message'])): ?>
                                <span class="text-danger"><strong>Last Delivery Error:</strong> <?= e($webhookInfo['result']['last_error_message']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </details>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- 2. Main Configuration Form -->
<form method="POST" action="<?= url('settings/bot') ?>">
    <?= csrf_field() ?>

    <!-- Section 1: Bot Credentials -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h6 class="card-title mb-0 fw-bold text-dark">
                <i class="bi bi-key-fill text-primary me-2"></i> 1. Bot Credentials
            </h6>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-7">
                    <label class="form-label small fw-semibold">Telegram Bot Token <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-shield-lock"></i></span>
                        <input type="password" id="botTokenInput" name="bot_token" class="form-control font-monospace" value="<?= e($botToken) ?>" placeholder="e.g. 1234567890:ABCdefGhIjk..." required>
                        <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('botTokenInput')">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <div class="form-text small">Obtain from <code>@BotFather</code> on Telegram via the <code>/newbot</code> command.</div>
                </div>

                <div class="col-md-5">
                    <label class="form-label small fw-semibold">Bot Username (without @)</label>
                    <div class="input-group">
                        <span class="input-group-text">@</span>
                        <input type="text" name="bot_username" class="form-control" value="<?= e($botUsername) ?>" placeholder="kaldis_tracker_bot">
                    </div>
                    <div class="form-text small">Public handle of the bot.</div>
                </div>
            </div>

            <div class="row g-3 mt-1">
                <div class="col-md-7">
                    <label class="form-label small fw-semibold">Public HTTPS Webhook URL <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-link-45deg"></i></span>
                        <input type="url" name="webhook_url" class="form-control font-monospace" value="<?= e($webhookUrl) ?>" placeholder="https://domain.com/webhook" required>
                    </div>
                    <div class="form-text small">Must be an accessible public HTTPS address.</div>
                </div>

                <div class="col-md-5">
                    <label class="form-label small fw-semibold">Webhook Secret Token <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-hash"></i></span>
                        <input type="text" id="secretTokenInput" name="webhook_secret" class="form-control font-monospace" value="<?= e($webhookSecret) ?>" placeholder="SecretToken123" required>
                        <button class="btn btn-outline-secondary" type="button" onclick="generateSecretToken()">
                            <i class="bi bi-shuffle"></i>
                        </button>
                    </div>
                    <div class="form-text small">Secures incoming Telegram calls.</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 2: Response Time Limitation & Deadline Summary Notification -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="card-title mb-0 fw-bold text-dark">
                <i class="bi bi-stopwatch-fill text-primary me-2"></i> 2. Response Time Limitation &amp; Deadline Summary
            </h6>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1">
                <?= $defaultDeadlineHours > 0 ? "{$defaultDeadlineHours}h Limitation" : "Manual Only" ?>
            </span>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Default Response Time Limitation (Deadline / SLA)</label>
                    <select name="default_deadline_hours" class="form-select font-monospace">
                        <option value="0" <?= $defaultDeadlineHours === 0 ? 'selected' : '' ?>>No Limitation / Manual</option>
                        <option value="1" <?= $defaultDeadlineHours === 1 ? 'selected' : '' ?>>1 Hour</option>
                        <option value="2" <?= $defaultDeadlineHours === 2 ? 'selected' : '' ?>>2 Hours</option>
                        <option value="4" <?= $defaultDeadlineHours === 4 ? 'selected' : '' ?>>4 Hours</option>
                        <option value="8" <?= $defaultDeadlineHours === 8 ? 'selected' : '' ?>>8 Hours</option>
                        <option value="12" <?= $defaultDeadlineHours === 12 ? 'selected' : '' ?>>12 Hours</option>
                        <option value="24" <?= $defaultDeadlineHours === 24 ? 'selected' : '' ?>>24 Hours (1 Day) — Standard</option>
                        <option value="48" <?= $defaultDeadlineHours === 48 ? 'selected' : '' ?>>48 Hours (2 Days)</option>
                        <option value="72" <?= $defaultDeadlineHours === 72 ? 'selected' : '' ?>>72 Hours (3 Days)</option>
                        <option value="168" <?= $defaultDeadlineHours === 168 ? 'selected' : '' ?>>7 Days (1 Week)</option>
                    </select>
                    <div class="form-text small">
                        Automatically sets the reply deadline on new tracked messages from authorized senders.
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Deadline Summary Report Notification</label>
                    <div class="p-3 bg-light rounded border h-100">
                        <div class="form-check form-switch mb-1">
                            <input class="form-check-input" type="checkbox" id="notifySenderSwitch" name="notify_sender_on_deadline" value="1" <?= $notifySenderOnDeadline ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold text-dark" for="notifySenderSwitch">
                                Send Summary Report to Authorized Sender
                            </label>
                        </div>
                        <div class="small text-muted mt-1">
                            When the time is done, the bot compiles and delivers a full report (reply percentage, who responded, and who hasn't) directly to the sender.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 3: Auto-Delete Original Tracked Messages (Data Retention) -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="card-title mb-0 fw-bold text-dark">
                <i class="bi bi-trash3-fill text-danger me-2"></i> 3. Auto-Delete Original Tracked Messages (Retention)
            </h6>
            <span class="badge bg-secondary-subtle text-secondary border rounded-pill px-2.5 py-1">
                <?= $retentionDays > 0 ? "Purge after {$retentionDays} days" : "Keep Indefinitely" ?>
            </span>
        </div>
        <div class="card-body p-4">
            <div class="row g-3 align-items-center">
                <div class="col-md-7">
                    <label class="form-label small fw-semibold">Auto-Delete Tracked Messages Period</label>
                    <select name="tracked_message_retention_days" class="form-select font-monospace">
                        <option value="0" <?= $retentionDays === 0 ? 'selected' : '' ?>>Never (Keep Indefinitely)</option>
                        <option value="7" <?= $retentionDays === 7 ? 'selected' : '' ?>>7 Days (1 Week)</option>
                        <option value="14" <?= $retentionDays === 14 ? 'selected' : '' ?>>14 Days (2 Weeks)</option>
                        <option value="15" <?= $retentionDays === 15 ? 'selected' : '' ?>>15 Days</option>
                        <option value="30" <?= $retentionDays === 30 ? 'selected' : '' ?>>30 Days (1 Month) — Recommended</option>
                        <option value="60" <?= $retentionDays === 60 ? 'selected' : '' ?>>60 Days (2 Months)</option>
                        <option value="90" <?= $retentionDays === 90 ? 'selected' : '' ?>>90 Days (3 Months)</option>
                        <option value="180" <?= $retentionDays === 180 ? 'selected' : '' ?>>180 Days (6 Months)</option>
                    </select>
                    <div class="form-text small">
                        Automatically cleans up older tracked messages and reply logs from the database.
                    </div>
                </div>

                <div class="col-md-5 text-md-end pt-md-3 d-flex flex-wrap gap-2 justify-content-md-end">
                    <button type="button" class="btn btn-outline-secondary btn-sm shadow-sm" onclick="document.getElementById('formRunCleanup').submit();" title="Trigger retention purge immediately">
                        <i class="bi bi-trash3 me-1"></i> Run Cleanup Now
                    </button>
                    <button type="button" class="btn btn-outline-primary btn-sm shadow-sm" onclick="document.getElementById('formSendSummaries').submit();" title="Check and dispatch any due deadline summaries immediately">
                        <i class="bi bi-send-check me-1"></i> Check Due Summaries
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 4: Tracking Preference -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h6 class="card-title mb-0 fw-bold text-dark">
                <i class="bi bi-sliders text-primary me-2"></i> 4. Tracking Preference
            </h6>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-check p-3 bg-light rounded border h-100 d-block cursor-pointer <?= $trackingMode === 'all_authorized' ? 'border-primary bg-primary-subtle' : '' ?>" style="cursor: pointer;">
                        <input class="form-check-input ms-0 me-2" type="radio" name="tracking_mode" id="mode_all_authorized" value="all_authorized" <?= $trackingMode === 'all_authorized' ? 'checked' : '' ?>>
                        <span class="fw-semibold text-dark">Automatic Conversation Tracking (Recommended)</span>
                        <span class="d-block text-muted small mt-1">
                            Tracks conversations sent by group authorized senders automatically. No hashtags required.
                        </span>
                    </label>
                </div>
                <div class="col-md-6">
                    <label class="form-check p-3 bg-light rounded border h-100 d-block cursor-pointer <?= $trackingMode === 'trigger_only' ? 'border-primary bg-primary-subtle' : '' ?>" style="cursor: pointer;">
                        <input class="form-check-input ms-0 me-2" type="radio" name="tracking_mode" id="mode_trigger_only" value="trigger_only" <?= $trackingMode === 'trigger_only' ? 'checked' : '' ?>>
                        <span class="fw-semibold text-dark">Hashtag Only (#track)</span>
                        <span class="d-block text-muted small mt-1">
                            Only tracks messages that explicitly include the <code>#track</code> hashtag.
                        </span>
                    </label>
                </div>
            </div>

            <!-- Hidden optional fields with defaults -->
            <input type="hidden" name="track_hashtag" value="<?= e(ltrim($trackHashtag, '#')) ?>">
            <input type="hidden" name="track_command" value="<?= e(ltrim($trackCommand, '/')) ?>">
        </div>
    </div>

    <!-- Save Action Bar -->
    <div class="d-flex justify-content-end mb-5">
        <button type="submit" class="btn btn-kaldis shadow-sm px-4 py-2">
            <i class="bi bi-check2-circle me-1"></i> Save Configuration &amp; Policies
        </button>
    </div>
</form>

<!-- Dedicated Action Forms for Manual Execution -->
<form id="formRunCleanup" action="<?= url('settings/run-cleanup') ?>" method="POST" class="d-none">
    <?= csrf_field() ?>
</form>
<form id="formSendSummaries" action="<?= url('settings/send-due-summaries') ?>" method="POST" class="d-none">
    <?= csrf_field() ?>
</form>

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
