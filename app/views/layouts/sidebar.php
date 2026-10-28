<?php
use App\Services\AuthService;
$user = AuthService::user();
$isSuperAdmin = AuthService::isSuperAdmin();
$isOwner = AuthService::isOwner();
$currentUri = $_SERVER['REQUEST_URI'] ?? '';
$roleLabel = match($user['role'] ?? '') {
    'super_admin' => 'Super Admin',
    'owner' => 'Owner',
    'viewer' => 'Viewer',
    default => ucfirst($user['role'] ?? 'User')
};
$roleBadgeClass = match($user['role'] ?? '') {
    'super_admin' => 'bg-warning text-dark border border-warning',
    'owner' => 'bg-primary text-white',
    default => 'bg-secondary text-white'
};
?>
<aside class="app-sidebar" id="appSidebar">
    <!-- Brand Header -->
    <div class="sidebar-brand">
        <a href="<?= url('dashboard') ?>" class="d-flex align-items-center text-decoration-none">
            <span class="brand-icon me-2">☕</span>
            <div class="brand-text">
                <span class="brand-title">Kaldis <span class="accent">Tracker</span></span>
                <span class="badge bg-amber-subtle text-amber border border-amber-subtle ms-1" style="font-size: 0.65rem;">v2.0</span>
            </div>
        </a>
        <button type="button" class="btn-close btn-close-white d-lg-none" id="sidebarCloseBtn" aria-label="Close"></button>
    </div>

    <!-- Logged in Admin User Card -->
    <div class="sidebar-user-box">
        <div class="user-avatar">
            <?= strtoupper(substr($user['username'] ?? 'A', 0, 2)) ?>
        </div>
        <div class="user-meta overflow-hidden">
            <div class="user-name text-truncate" title="<?= e($user['username'] ?? '') ?>">
                <?= e($user['username'] ?? 'Admin') ?>
            </div>
            <span class="badge <?= $roleBadgeClass ?> text-uppercase" style="font-size: 0.62rem; letter-spacing: 0.5px;">
                <?php if ($isSuperAdmin): ?><i class="bi bi-shield-lock-fill me-1"></i><?php endif; ?>
                <?= $roleLabel ?>
            </span>
        </div>
    </div>

    <!-- Navigation Menu Items -->
    <nav class="sidebar-nav">
        <div class="nav-section-label">MONITORING</div>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link <?= str_contains($currentUri, 'dashboard') && !str_contains($currentUri, 'about') ? 'active' : '' ?>" href="<?= url('dashboard') ?>">
                    <i class="bi bi-speedometer2 me-2"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= str_contains($currentUri, 'groups') ? 'active' : '' ?>" href="<?= url('groups') ?>">
                    <i class="bi bi-people-fill me-2"></i>
                    <span>Telegram Groups</span>
                </a>
            </li>
        </ul>

        <?php if ($isSuperAdmin): ?>
            <div class="nav-section-label">ADMINISTRATION</div>
            <ul class="nav flex-column mb-2">
                <li class="nav-item">
                    <a class="nav-link <?= str_contains($currentUri, 'settings/bot') ? 'active' : '' ?>" href="<?= url('settings/bot') ?>">
                        <i class="bi bi-robot me-2"></i>
                        <span>Bot Configuration</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= str_contains($currentUri, 'settings/admins') ? 'active' : '' ?>" href="<?= url('settings/admins') ?>">
                        <i class="bi bi-person-gear me-2"></i>
                        <span>Admin Users</span>
                    </a>
                </li>
            </ul>
        <?php endif; ?>

        <div class="nav-section-label">SYSTEM &amp; DOCS</div>
        <ul class="nav flex-column mb-3">
            <li class="nav-item">
                <a class="nav-link <?= str_contains($currentUri, 'audit') ? 'active' : '' ?>" href="<?= url('audit') ?>">
                    <i class="bi bi-shield-check me-2"></i>
                    <span>Audit Logs</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= str_contains($currentUri, 'about') ? 'active' : '' ?>" href="<?= url('dashboard/about') ?>">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    <span>About Monitoring</span>
                </a>
            </li>
        </ul>
    </nav>

    <!-- Sidebar Footer / Logout -->
    <div class="sidebar-footer">
        <form action="<?= url('logout') ?>" method="POST" class="w-100">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-outline-danger btn-sm w-100 d-flex align-items-center justify-content-center">
                <i class="bi bi-box-arrow-right me-2"></i> Sign Out
            </button>
        </form>
    </div>
</aside>
