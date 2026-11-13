<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?= e($pageTitle ?? 'Monitoring System') ?> &bull; <?= e(config('app.name', "Kaldi's Coffee")) ?></title>
    
    <link rel="icon" type="image/svg+xml" href="<?= url('favicon.svg') ?>">
    <link rel="alternate icon" type="image/jpeg" href="<?= url('assets/img/kaldis-logo.jpg') ?>">
    <link rel="apple-touch-icon" href="<?= url('assets/img/logo.png') ?>">
    
    <!-- Google / Bunny Fonts: Instrument Sans (matching companysystem) -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- App Custom CSS (companysystem Theme) -->
    <link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
</head>
<body>
    <div class="app-layout">
        <!-- Sidebar Navigation (companysystem AppSidebar) -->
        <?php require __DIR__ . '/sidebar.php'; ?>

        <!-- Backdrop for mobile drawer -->
        <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

        <!-- Main Content Area -->
        <div class="app-content-wrapper">
            <!-- Topbar (companysystem AppSidebarHeader) -->
            <header class="app-topbar">
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-outline-secondary btn-sm d-lg-none" id="sidebarToggleBtn" aria-label="Toggle Sidebar">
                        <i class="bi bi-list fs-5"></i>
                    </button>
                    <!-- Clean Breadcrumbs -->
                    <div class="topbar-breadcrumbs d-flex align-items-center">
                        <span class="breadcrumb-item-lead">Kaldi's Coffee</span>
                        <i class="bi bi-chevron-right breadcrumb-separator mx-1.5"></i>
                        <span class="breadcrumb-item-active"><?= e($pageTitle ?? 'Monitoring System') ?></span>
                    </div>
                </div>

                <div class="topbar-actions ms-auto d-flex align-items-center gap-2">
                    <!-- Live Status Indicator -->
                    <div class="live-status-pill d-none d-sm-inline-flex" title="Real-time WebSocket &amp; polling listener active">
                        <span class="pulse-dot"></span>
                        <span>Live Sync</span>
                    </div>

                    <!-- EAT Clock Pill -->
                    <span class="badge bg-light text-secondary border font-monospace d-none d-md-inline-flex py-1 px-2.5">
                        <i class="bi bi-clock me-1 text-muted"></i><?= format_tz(utc_now(), 'h:i A') ?> EAT
                    </span>

                    <a href="<?= url('dashboard') ?>" class="btn btn-sm btn-outline-secondary" title="Return to Dashboard">
                        <i class="bi bi-speedometer2"></i>
                    </a>
                </div>
            </header>

            <!-- Main Page Body -->
            <main class="content-body">
                <?php require __DIR__ . '/alerts.php'; ?>
                <?= $content ?? '' ?>
            </main>

            <!-- Footer (companysystem AppFooter) -->
            <?php require __DIR__ . '/footer.php'; ?>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="<?= url('assets/js/app.js') ?>"></script>
</body>
</html>
