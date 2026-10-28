<?php
use App\Services\AuthService;
$user = AuthService::user();
$isOwner = AuthService::isOwner();
$currentUri = $_SERVER['REQUEST_URI'] ?? '';
?>
<nav class="navbar navbar-expand-lg navbar-dark navbar-kaldis">
    <div class="container-fluid px-lg-4">
        <a class="navbar-brand d-flex align-items-center" href="<?= url('dashboard') ?>">
            <span class="fs-4 me-2">☕</span>
            <span>Kaldis <span class="accent">Tracker</span></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMain">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?= str_contains($currentUri, 'dashboard') && !str_contains($currentUri, 'about') ? 'active' : '' ?>" href="<?= url('dashboard') ?>">
                        <i class="bi bi-speedometer2 me-1"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= str_contains($currentUri, 'groups') ? 'active' : '' ?>" href="<?= url('groups') ?>">
                        <i class="bi bi-people me-1"></i> Telegram Groups
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= str_contains($currentUri, 'about') ? 'active' : '' ?>" href="<?= url('dashboard/about') ?>">
                        <i class="bi bi-info-circle me-1"></i> About Monitoring
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= str_contains($currentUri, 'audit') ? 'active' : '' ?>" href="<?= url('audit') ?>">
                        <i class="bi bi-shield-check me-1"></i> Audit Logs
                    </a>
                </li>
            </ul>

            <div class="d-flex align-items-center text-light">
                <div class="me-3 text-end d-none d-md-block">
                    <div class="fw-bold small"><?= e($user['username'] ?? 'Admin') ?></div>
                    <span class="badge bg-<?= $isOwner ? 'warning text-dark' : 'secondary' ?> text-uppercase" style="font-size: 0.65rem;">
                        <?= e($user['role'] ?? 'viewer') ?>
                    </span>
                </div>
                <form action="<?= url('logout') ?>" method="POST" class="d-inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline-light btn-sm" title="Log Out">
                        <i class="bi bi-box-arrow-right me-1"></i> Exit
                    </button>
                </form>
            </div>
        </div>
    </div>
</nav>
