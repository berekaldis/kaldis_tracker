<?php
use App\Services\AuthService;
$user = AuthService::user();
$isSuperAdmin = AuthService::isSuperAdmin();
$isOwner = AuthService::isOwner();
$currentUri = $_SERVER['REQUEST_URI'] ?? '';
$roleLabel = match($user['role'] ?? '') {
    'super_admin' => 'Super Admin',
    'owner' => 'Owner',
    'group_authorizer' => 'Group Authorizer',
    'user' => 'Normal User',
    'viewer' => 'Viewer',
    default => ucfirst(str_replace('_', ' ', $user['role'] ?? 'User'))
};
$roleBadgeClass = match($user['role'] ?? '') {
    'super_admin' => 'badge-status-pending_review text-uppercase',
    'owner' => 'bg-slate-900 text-white text-uppercase',
    'group_authorizer' => 'badge-status-replied text-uppercase',
    'user' => 'badge-status-replied text-uppercase',
    default => 'badge-status-unknown text-uppercase'
};
?>
<aside class="app-sidebar" id="appSidebar">
    <!-- Brand Header (companysystem AppLogo) -->
    <div class="sidebar-brand">
        <a href="<?= url('dashboard') ?>" class="brand-logo-container">
            <img src="<?= url('assets/img/logo.png') ?>" alt="Kaldi's Coffee Logo" class="brand-logo-img" onerror="this.onerror=null; this.src='<?= url('assets/img/kaldis-logo.jpg') ?>';">
            <div class="brand-text">
                <span class="brand-title">
                    Kaldi's <span class="accent">Coffee</span>
                </span>
                <span class="brand-badge">Tracker</span>
            </div>
        </a>
        <button type="button" class="btn-close d-lg-none" id="sidebarCloseBtn" aria-label="Close"></button>
    </div>

    <!-- User Profile Header (companysystem UserInfo) -->
    <div class="sidebar-user-box">
        <div class="user-avatar">
            <?= strtoupper(substr($user['username'] ?? 'A', 0, 2)) ?>
        </div>
        <div class="overflow-hidden flex-1">
            <div class="user-name text-truncate" title="<?= e($user['username'] ?? '') ?>">
                <?= e($user['username'] ?? 'Admin') ?>
            </div>
            <div class="d-flex align-items-center gap-1 mt-1 flex-wrap">
                <span class="badge <?= $roleBadgeClass ?>" style="font-size: 0.62rem; letter-spacing: 0.04em;">
                    <?php if ($isSuperAdmin): ?><i class="bi bi-shield-lock-fill me-0.5"></i><?php endif; ?>
                    <?= $roleLabel ?>
                </span>
                <?php if (!empty($user['assigned_group_id'])): ?>
                    <span class="badge bg-light text-success border" style="font-size: 0.62rem;" title="Assigned Group">
                        <i class="bi bi-chat-quote me-0.5"></i>#<?= (int)$user['assigned_group_id'] ?>
                    </span>
                <?php elseif (!empty($user['department']) && strcasecmp($user['department'], 'all') !== 0): ?>
                    <span class="badge bg-light text-primary border" style="font-size: 0.62rem;" title="Department Scope">
                        <i class="bi bi-building me-0.5"></i><?= e($user['department']) ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Navigation Menu Items -->
    <nav class="sidebar-nav custom-scrollbar">
        <ul class="nav flex-column mb-3">
            <li class="nav-item">
                <a class="nav-link <?= str_contains($currentUri, 'dashboard') && !str_contains($currentUri, 'about') ? 'active' : '' ?>" href="<?= url('dashboard') ?>">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= str_contains($currentUri, 'groups') ? 'active' : '' ?>" href="<?= url('groups') ?>">
                    <i class="bi bi-people-fill"></i>
                    <span>Telegram Groups</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= str_contains($currentUri, 'senders') ? 'active' : '' ?>" href="<?= url('senders') ?>">
                    <i class="bi bi-person-check-fill"></i>
                    <span>Authorized Senders</span>
                </a>
            </li>

            <?php if ($isSuperAdmin): ?>
                <li class="nav-item">
                    <a class="nav-link <?= str_contains($currentUri, 'settings/bot') ? 'active' : '' ?>" href="<?= url('settings/bot') ?>">
                        <i class="bi bi-robot"></i>
                        <span>Bot Setup</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= str_contains($currentUri, 'settings/admins') ? 'active' : '' ?>" href="<?= url('settings/admins') ?>">
                        <i class="bi bi-person-gear"></i>
                        <span>Users &amp; Access</span>
                    </a>
                </li>
            <?php endif; ?>

            <?php if ($isOwner): ?>
                <li class="nav-item">
                    <a class="nav-link <?= str_contains($currentUri, 'audit') ? 'active' : '' ?>" href="<?= url('audit') ?>">
                        <i class="bi bi-shield-check"></i>
                        <span>Audit Logs</span>
                    </a>
                </li>
            <?php endif; ?>

            <li class="nav-item">
                <a class="nav-link <?= str_contains($currentUri, 'about') ? 'active' : '' ?>" href="<?= url('dashboard/about') ?>">
                    <i class="bi bi-info-circle-fill"></i>
                    <span>About &amp; Help</span>
                </a>
            </li>
        </ul>
    </nav>

    <!-- Sidebar Footer / Logout -->
    <div class="sidebar-footer">
        <form action="<?= url('logout') ?>" method="POST" class="w-100">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-outline-danger btn-sm w-100 d-flex align-items-center justify-content-center">
                <i class="bi bi-box-arrow-right"></i>
                <span>Sign Out</span>
            </button>
        </form>
    </div>
</aside>
