<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In &bull; Kaldis Coffee Telegram Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
    <style>
        body {
            background: linear-gradient(135deg, #2b1810 0%, #4a2c1d 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 1rem;
        }
        .login-card {
            width: 100%;
            max-width: 420px;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 12px 30px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .login-header {
            background-color: #381f14;
            color: #ffffff;
            padding: 2rem 1.5rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <div class="display-5 mb-2">☕</div>
            <h4 class="mb-1 fw-bold">Kaldis Coffee</h4>
            <div class="text-white-50 small">Telegram Reply Tracker (v2)</div>
        </div>

        <div class="p-4">
            <?php require __DIR__ . '/../layouts/alerts.php'; ?>

            <form action="<?= url('login') ?>" method="POST">
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label for="username" class="form-label fw-semibold small">Username or Email</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control" id="username" name="username" required autofocus placeholder="admin or email">
                    </div>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label fw-semibold small">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" required placeholder="••••••••">
                    </div>
                </div>

                <button type="submit" class="btn btn-kaldis w-100 py-2 shadow-sm">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
                </button>
            </form>

            <div class="text-center text-muted mt-4" style="font-size: 0.75rem;">
                <i class="bi bi-shield-lock me-1"></i> Protected by brute-force lockout &amp; secure session controls.
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= url('assets/js/app.js') ?>"></script>
</body>
</html>
