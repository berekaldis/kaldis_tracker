<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?= e(config('app.name', 'Kaldis Coffee Telegram Tracker')) ?></title>
    <link rel="icon" type="image/jpeg" href="<?= url('assets/img/kaldis-logo.jpg') ?>">
    <link rel="apple-touch-icon" href="<?= url('assets/img/kaldis-logo.jpg') ?>">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- App Custom CSS -->
    <link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
</head>
<body>
    <div class="app-layout">
        <!-- Sidebar Navigation -->
        <?php require __DIR__ . '/sidebar.php'; ?>

        <!-- Backdrop for mobile drawer -->
        <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

        <!-- Main Content Area -->
        <div class="app-content-wrapper">
            <!-- Topbar -->
            <header class="app-topbar">
                <button class="btn btn-outline-secondary btn-sm d-lg-none me-2" id="sidebarToggleBtn" aria-label="Toggle Sidebar">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <div class="topbar-title">
                    <span class="text-muted small">Kaldis Coffee Tracker &bull; </span>
                    <span class="fw-semibold text-dark"><?= e($pageTitle ?? 'Monitoring System') ?></span>
                </div>
                <div class="topbar-actions ms-auto d-flex align-items-center gap-2">
                    <span class="badge bg-light text-secondary border font-monospace d-none d-sm-inline">
                        <i class="bi bi-clock me-1"></i><?= format_tz(utc_now(), 'h:i A') ?> (EAT)
                    </span>
                    <a href="<?= url('dashboard') ?>" class="btn btn-sm btn-outline-secondary d-none d-md-inline" title="Quick Dashboard">
                        <i class="bi bi-speedometer2"></i>
                    </a>
                </div>
            </header>

            <!-- Main Page Body -->
            <main class="content-body">
                <?php require __DIR__ . '/alerts.php'; ?>
                <?= $content ?? '' ?>
            </main>

            <!-- Footer -->
            <?php require __DIR__ . '/footer.php'; ?>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="<?= url('assets/js/app.js') ?>"></script>
</body>
</html>
