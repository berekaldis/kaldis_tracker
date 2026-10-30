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
    <div class="col-md-7 text-md-end mt-3 mt-md-0 d-flex flex-wrap align-items-center justify-content-md-end gap-2">
        <?php if ($isOwner): ?>
            <!-- Send Interactive Check-in Button to Telegram -->
            <form method="POST" action="<?= url('groups/send-checkin') ?>" class="d-inline" onsubmit="return confirm('Send an interactive Check-in button to the Telegram group? Members simply tap it to register.');">
                <?= csrf_field() ?>
                <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                <button type="submit" class="btn btn-success btn-sm shadow-sm" title="Send interactive 'Tap to Check In' button directly into the Telegram group">
                    <i class="bi bi-broadcast-pin me-1"></i> Send Check-in Button to Telegram
                </button>
            </form>

            <!-- Sync Admins Button -->
            <form method="POST" action="<?= url('groups/sync-admins') ?>" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                <input type="hidden" name="redirect_to_view" value="1">
                <button type="submit" class="btn btn-outline-primary btn-sm shadow-sm" title="Pull Creators and Administrators directly from Telegram">
                    <i class="bi bi-arrow-repeat me-1"></i> Sync Admins
                </button>
            </form>

            <!-- Bulk Import Button -->
            <button type="button" class="btn btn-outline-dark btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#modalBulkImport">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i> Bulk Import
            </button>

            <!-- Add Single Member Button -->
            <button type="button" class="btn btn-kaldis btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAddMember">
                <i class="bi bi-person-plus-fill me-1"></i> Add Single Member
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
<div class="alert alert-warning-subtle border border-warning shadow-sm mb-4">
    <div class="d-flex align-items-start">
        <div class="fs-2 text-warning me-3 mt-1"><i class="bi bi-shield-exclamation"></i></div>
        <div class="flex-grow-1">
            <h6 class="fw-bold mb-1 text-dark">Why did "Sync Admins" only sync the Admin and Group Owner?</h6>
            <p class="small text-secondary mb-2">
                Under <strong>Telegram's official platform security &amp; privacy policy</strong>, bots are <strong>strictly forbidden from downloading the full member list of a group in bulk</strong> (there is no <code>getChatMembers</code> API in Telegram). The only endpoint Telegram provides is <code>getChatAdministrators</code>, which exclusively returns the group creator and administrators.
            </p>
            <div class="fw-semibold small text-dark mb-2">To get all other 23+ members into this roster, use any of these 3 easy methods:</div>
            <div class="row g-2 small">
                <div class="col-md-4">
                    <div class="p-2 bg-white rounded border h-100 shadow-sm">
                        <strong class="text-success d-block mb-1"><i class="bi bi-broadcast-pin me-1"></i> 1. Send Check-in Button (Recommended)</strong>
                        <div class="text-muted">Click <strong>"Send Check-in Button to Telegram"</strong> above. A button appears in the Telegram group; when staff tap it, they are <strong>instantly registered</strong> on this roster with their real name &amp; ID!</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-2 bg-white rounded border h-100 shadow-sm">
                        <strong class="text-primary d-block mb-1"><i class="bi bi-chat-dots me-1"></i> 2. Auto-Discovery as They Chat</strong>
                        <div class="text-muted">Whenever a waiter or staff member sends <strong>any message, photo, greeting, or reply</strong> in the group, the system captures them and adds them to this roster automatically.</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-2 bg-white rounded border h-100 shadow-sm">
                        <strong class="text-dark d-block mb-1"><i class="bi bi-file-earmark-spreadsheet me-1"></i> 3. Bulk Import / Paste</strong>
                        <div class="text-muted">Click <strong>"Bulk Import"</strong> to paste your staff list (Names and Telegram @usernames or IDs) or upload a CSV file from HR/management.</div>
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

<!-- Modal: Bulk Import Roster -->
<div class="modal fade" id="modalBulkImport" tabindex="-1" aria-labelledby="modalBulkImportLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="<?= url('groups/bulk-import') ?>" enctype="multipart/form-data" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="modalBulkImportLabel">
                    <i class="bi bi-file-earmark-spreadsheet text-primary me-2"></i> Bulk Import Staff Members to Roster
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">
                    Quickly add multiple waiters, baristas, or staff members to <strong><?= e($group['title']) ?></strong> by pasting their names and Telegram IDs/usernames, or by uploading a CSV file.
                </p>

                <!-- Method A: Paste Text -->
                <div class="mb-3">
                    <label class="form-label small fw-semibold">
                        Paste Member List <span class="text-muted">(One member per line)</span>
                    </label>
                    <textarea name="raw_members" class="form-control font-monospace small" rows="7" placeholder="Format: Full Name, Telegram User ID or @username, Role (optional)
Example:
Abebe Bikila, @abebikila, member
Sara Mengistu, 555666777, member
Dawit Kebede, @dawit_k, member
Berhanu Supervisor, 1626288394, admin"></textarea>
                    <div class="form-text small">
                        Accepts comma-separated or tab-separated entries. You can paste directly from Excel or Google Sheets.
                    </div>
                </div>

                <div class="d-flex align-items-center my-3">
                    <hr class="flex-grow-1 my-0">
                    <span class="px-3 text-muted small fw-semibold">OR UPLOAD CSV</span>
                    <hr class="flex-grow-1 my-0">
                </div>

                <!-- Method B: Upload CSV File -->
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Upload CSV / TXT File</label>
                    <input type="file" name="csv_file" class="form-control" accept=".csv,.txt">
                    <div class="form-text small">Columns: <code>Name, Identifier (@username or ID), Role</code></div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-kaldis btn-sm">
                    <i class="bi bi-cloud-arrow-up me-1"></i> Import Members
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
