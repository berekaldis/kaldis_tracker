<div class="row align-items-center mb-4">
    <div class="col-md-7">
        <h3 class="fw-bold mb-1" style="color: var(--kaldis-primary);">User &amp; Access Management</h3>
        <p class="text-muted small mb-0">Manage system administrators, normal users, group assignments, and access permissions.</p>
    </div>
    <div class="col-md-5 text-md-end mt-3 mt-md-0">
        <button type="button" class="btn btn-kaldis shadow-sm" data-bs-toggle="modal" data-bs-target="#modalCreateAdmin">
            <i class="bi bi-person-plus-fill me-1"></i> Add User / Administrator
        </button>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>User</th>
                    <th>Email Address</th>
                    <th>Access Scope (Group / Dept)</th>
                    <th class="text-center">Role</th>
                    <th class="text-center">Status</th>
                    <th>Last Login (EAT)</th>
                    <th>Last IP</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($admins as $a): ?>
                    <tr>
                        <td>
                            <div class="fw-bold text-dark"><?= e($a['username']) ?></div>
                            <span class="font-monospace small text-muted">ID: #<?= e($a['id']) ?></span>
                        </td>
                        <td>
                            <span class="text-dark small"><?= e($a['email']) ?></span>
                        </td>
                        <td>
                            <?php if (!empty($a['assigned_group_id'])): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle me-1 mb-1 d-inline-block" title="Assigned Group #<?= (int)$a['assigned_group_id'] ?>">
                                    <i class="bi bi-chat-quote-fill me-1"></i><?= e($a['assigned_group_title'] ?? ('Group #' . $a['assigned_group_id'])) ?>
                                </span>
                            <?php endif; ?>

                            <?php if (!empty($a['department']) && strcasecmp($a['department'], 'all') !== 0): ?>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle d-inline-block">
                                    <i class="bi bi-building me-1"></i><?= e($a['department']) ?>
                                </span>
                            <?php elseif (empty($a['assigned_group_id'])): ?>
                                <span class="badge bg-light text-dark border">
                                    <i class="bi bi-globe me-1 text-muted"></i>All Departments
                                </span>
                            <?php endif; ?>

                            <button type="button" class="btn btn-sm btn-link p-0 text-muted ms-1" title="Edit Scope &amp; Role" onclick="openAdminScopeModal(<?= (int)$a['id'] ?>, '<?= e(addslashes($a['username'])) ?>', '<?= e(addslashes($a['department'] ?? 'All')) ?>', '<?= (int)($a['assigned_group_id'] ?? 0) ?>', '<?= e(addslashes($a['role'])) ?>')">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                        </td>
                        <td class="text-center">
                            <?php if ($a['role'] === 'super_admin'): ?>
                                <span class="badge bg-warning text-dark border border-warning-subtle text-uppercase">
                                    <i class="bi bi-shield-lock-fill me-1"></i> Super Admin
                                </span>
                            <?php elseif ($a['role'] === 'owner'): ?>
                                <span class="badge bg-primary text-uppercase">Owner</span>
                            <?php elseif ($a['role'] === 'user'): ?>
                                <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle text-uppercase" title="Can track group conversations only">
                                    <i class="bi bi-person-fill me-1"></i> Normal User
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary text-uppercase">Viewer</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($a['is_active']): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>
                            <?php else: ?>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Deactivated</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="small text-muted">
                                <?= format_tz($a['last_login_at']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="font-monospace small text-muted"><?= e($a['last_login_ip'] ?? '—') ?></span>
                        </td>
                        <td class="text-end">
                            <?php if ($a['id'] != $_SESSION['admin_user']['id']): ?>
                                <form method="POST" action="<?= url('settings/admins/toggle') ?>" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="admin_id" value="<?= $a['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-<?= $a['is_active'] ? 'danger' : 'success' ?>" title="<?= $a['is_active'] ? 'Deactivate User' : 'Activate User' ?>">
                                        <i class="bi bi-<?= $a['is_active'] ? 'person-x' : 'person-check' ?>"></i>
                                        <?= $a['is_active'] ? 'Deactivate' : 'Activate' ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="badge bg-light text-muted border">Current User</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Create User / Admin -->
<div class="modal fade" id="modalCreateAdmin" tabindex="-1" aria-labelledby="modalCreateAdminLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="<?= url('settings/admins/create') ?>" class="modal-content">
            <?= csrf_field() ?>
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="modalCreateAdminLabel">
                    <i class="bi bi-person-plus-fill text-primary me-2"></i> Create User Account
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Username <span class="text-danger">*</span></label>
                    <input type="text" name="username" class="form-control" required placeholder="e.g. airport_supervisor or it_user">
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Email Address <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" required placeholder="e.g. user@kaldiscoffee.com">
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Initial Password <span class="text-danger">*</span></label>
                    <input type="password" name="password" class="form-control" required placeholder="••••••••" minlength="8">
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Role &amp; Permissions <span class="text-danger">*</span></label>
                    <select name="role" id="createRoleSelect" class="form-select" required onchange="handleRoleChange(this.value, 'createGroupSelectWrapper')">
                        <option value="user" selected>Normal User (Only tracks assigned group conversation)</option>
                        <option value="owner">Owner / Manager (Manages groups, sync, and tracking)</option>
                        <option value="super_admin">Super Admin (Full system access &amp; bot configuration)</option>
                        <option value="viewer">Viewer (Read-only reports &amp; dashboard)</option>
                    </select>
                    <div class="form-text small">
                        <strong>Normal User:</strong> Has access only to track and manage message responses for their assigned group. Cannot change bot configuration or administer groups.
                    </div>
                </div>

                <div class="mb-3" id="createGroupSelectWrapper">
                    <label class="form-label small fw-semibold">Assigned Telegram Group <span class="text-muted">(Optional / Recommended for Normal User)</span></label>
                    <select name="assigned_group_id" class="form-select">
                        <option value="">-- All Groups in Department (Department Scope) --</option>
                        <?php if (!empty($allGroups)): ?>
                            <?php foreach ($allGroups as $g): ?>
                                <option value="<?= $g['id'] ?>">Group: <?= e($g['title']) ?> (<?= e($g['department'] ?? 'General') ?>)</option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <div class="form-text small">If a specific group is selected, this user can <strong>ONLY</strong> see and track conversations in that group.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Department Scope <span class="text-danger">*</span></label>
                    <select name="department" class="form-select" required>
                        <option value="All">All Departments (Corporate / Company-wide)</option>
                        <option value="IT">IT (Information Technology)</option>
                        <option value="Operations" selected>Operations &amp; Branches</option>
                        <option value="Supply Chain">Supply Chain &amp; Roastery</option>
                        <option value="Finance">Finance &amp; Accounting</option>
                        <option value="Quality & Barista">Quality Assurance &amp; Baristas</option>
                        <option value="HR & Admin">HR &amp; Administration</option>
                        <option value="Marketing">Marketing &amp; Sales</option>
                    </select>
                    <div class="form-text small">If no single group is chosen, this department restricts which groups the user can access.</div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-kaldis btn-sm">
                    <i class="bi bi-check-lg me-1"></i> Create Account
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Admin Scope & Role -->
<div class="modal fade" id="modalEditAdminDept" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="<?= url('settings/admins/update-department') ?>" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="admin_id" id="editAdminId" value="">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-sliders text-primary me-2"></i> Update User Access Scope &amp; Role
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">User Account</label>
                    <input type="text" id="editAdminUsername" class="form-control bg-light" readonly>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Role &amp; Permissions</label>
                    <select name="role" id="editAdminRoleSelect" class="form-select" required>
                        <option value="user">Normal User (Only tracks assigned group conversation)</option>
                        <option value="owner">Owner / Manager (Manages groups, sync, and tracking)</option>
                        <option value="super_admin">Super Admin (Full system access)</option>
                        <option value="viewer">Viewer (Read-only reports)</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Assigned Telegram Group</label>
                    <select name="assigned_group_id" id="editAdminGroupSelect" class="form-select">
                        <option value="">-- All Groups in Department --</option>
                        <?php if (!empty($allGroups)): ?>
                            <?php foreach ($allGroups as $g): ?>
                                <option value="<?= $g['id'] ?>">Group: <?= e($g['title']) ?> (<?= e($g['department'] ?? 'General') ?>)</option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <div class="form-text small">Lock this user down to a single Telegram group's conversations.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Department Scope</label>
                    <select name="department" id="editAdminDeptSelect" class="form-select" required>
                        <option value="All">All Departments (Corporate / Company-wide)</option>
                        <option value="IT">IT (Information Technology)</option>
                        <option value="Operations">Operations &amp; Branches</option>
                        <option value="Supply Chain">Supply Chain &amp; Roastery</option>
                        <option value="Finance">Finance &amp; Accounting</option>
                        <option value="Quality & Barista">Quality Assurance &amp; Baristas</option>
                        <option value="HR & Admin">HR &amp; Administration</option>
                        <option value="Marketing">Marketing &amp; Sales</option>
                    </select>
                    <div class="form-text small">Users restricted to a department cannot view conversations or groups outside it.</div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-kaldis btn-sm">
                    <i class="bi bi-check2 me-1"></i> Save Access Scope
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openAdminScopeModal(adminId, username, currentDept, currentGroupId, currentRole) {
    document.getElementById('editAdminId').value = adminId;
    document.getElementById('editAdminUsername').value = username;

    const deptSelect = document.getElementById('editAdminDeptSelect');
    if (deptSelect) {
        deptSelect.value = (currentDept && currentDept !== '') ? currentDept : 'All';
    }

    const groupSelect = document.getElementById('editAdminGroupSelect');
    if (groupSelect) {
        groupSelect.value = (currentGroupId && parseInt(currentGroupId) > 0) ? currentGroupId : '';
    }

    const roleSelect = document.getElementById('editAdminRoleSelect');
    if (roleSelect) {
        roleSelect.value = currentRole || 'user';
    }

    const modal = new bootstrap.Modal(document.getElementById('modalEditAdminDept'));
    modal.show();
}

function handleRoleChange(roleVal, wrapperId) {
    // Optional UI helper
}
</script>
