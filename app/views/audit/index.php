<div class="row align-items-center mb-4">
    <div class="col-md-7">
        <h3 class="fw-bold mb-1" style="color: var(--kaldis-primary);">Security &amp; Operational Audit Logs</h3>
        <p class="text-muted small mb-0">Immutable record of logins, group updates, tracked messages, and administration actions.</p>
    </div>
    <div class="col-md-5 text-md-end mt-3 mt-md-0">
        <span class="badge bg-light text-dark border px-3 py-2">
            Total Log Entries: <?= number_format($totalCount) ?>
        </span>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="min-width: 160px;">Timestamp (EAT)</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Entity</th>
                    <th>IP Address</th>
                    <th style="min-width: 250px;">Details</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-journal-text fs-1 d-block mb-2 text-secondary"></i>
                            <div>No audit log entries recorded yet.</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold small"><?= format_tz($log['created_at'], 'M j, Y') ?></div>
                                <div class="text-muted small"><?= format_tz($log['created_at'], 'h:i:s A') ?></div>
                            </td>
                            <td>
                                <?php if (!empty($log['admin_username'])): ?>
                                    <span class="fw-bold text-dark small"><i class="bi bi-person me-1"></i><?= e($log['admin_username']) ?></span>
                                <?php else: ?>
                                    <span class="badge bg-light text-secondary border">System / Guest</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-dark-subtle text-dark border font-monospace">
                                    <?= e($log['action']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($log['entity_type'])): ?>
                                    <span class="small text-muted text-capitalize"><?= e($log['entity_type']) ?></span>
                                    <?php if (!empty($log['entity_id'])): ?>
                                        <span class="font-monospace small">#<?= e($log['entity_id']) ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="font-monospace small text-muted"><?= e($log['ip_address']) ?></span>
                            </td>
                            <td>
                                <?php if (!empty($log['details'])): ?>
                                    <div class="small font-monospace bg-light p-2 rounded border" style="max-height: 80px; overflow-y: auto; font-size: 0.75rem;">
                                        <?= e($log['details']) ?>
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

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center py-3">
            <span class="small text-muted">Showing page <?= $page ?> of <?= $totalPages ?></span>
            <nav aria-label="Page navigation">
                <ul class="pagination pagination-sm mb-0">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?= url('audit?' . http_build_query(array_merge($_GET, ['page' => $page - 1]))) ?>">Previous</a>
                        </li>
                    <?php endif; ?>
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="<?= url('audit?' . http_build_query(array_merge($_GET, ['page' => $i]))) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?= url('audit?' . http_build_query(array_merge($_GET, ['page' => $page + 1]))) ?>">Next</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>
