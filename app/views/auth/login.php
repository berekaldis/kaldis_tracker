<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log in &bull; <?= e(config('app.name', "Kaldi's Coffee")) ?></title>
    
    <link rel="icon" type="image/svg+xml" href="<?= url('favicon.svg') ?>">
    <link rel="apple-touch-icon" href="<?= url('assets/img/logo.png') ?>">
    
    <!-- Google / Bunny Fonts: Instrument Sans (matching companysystem) -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
    
    <style>
        body {
            background-color: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 1.5rem 1rem;
            font-family: var(--font-sans);
        }
        .auth-container {
            width: 100%;
            max-width: 440px;
        }
        .auth-logo-badge {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            padding: 12px;
        }
        .auth-logo-badge img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .auth-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
            padding: 2.25rem 2rem;
        }
        .password-toggle-btn {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #64748b;
            padding: 0;
            cursor: pointer;
            z-index: 10;
        }
        .password-toggle-btn:hover {
            color: #0f172a;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <!-- Logo Icon (companysystem AppLogoIcon) -->
        <div class="text-center">
            <div class="auth-logo-badge">
                <img src="<?= url('assets/img/logo.png') ?>" alt="Kaldi's Coffee" onerror="this.onerror=null; this.src='<?= url('assets/img/kaldis-logo.jpg') ?>';">
            </div>
        </div>

        <div class="auth-card">
            <div class="text-center mb-4">
                <h3 class="fw-bold mb-1" style="color: #0f172a; letter-spacing: -0.02em;">Log in to your account</h3>
                <p class="text-muted small mb-0">Enter your credentials below to access the tracker</p>
            </div>

            <?php require __DIR__ . '/../layouts/alerts.php'; ?>

            <form action="<?= url('login') ?>" method="POST" class="needs-validation">
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label for="username" class="form-label">Username or Email</label>
                    <input type="text" class="form-control" id="username" name="username" required autofocus placeholder="admin or user@kaldisbunna.et">
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="position-relative">
                        <input type="password" class="form-control pe-5" id="password" name="password" required placeholder="••••••••">
                        <button type="button" class="password-toggle-btn" id="togglePasswordBtn" aria-label="Toggle password visibility">
                            <i class="bi bi-eye" id="togglePasswordIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="remember" name="remember" checked>
                        <label class="form-check-label small text-muted" for="remember">
                            Remember this browser
                        </label>
                    </div>
                    <span class="small text-muted" style="font-size: 0.78rem;">
                        <i class="bi bi-shield-check text-success me-1"></i>Secure Session
                    </span>
                </div>

                <button type="submit" class="btn btn-kaldis w-100 py-2.5">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
                </button>
            </form>
        </div>

        <div class="text-center text-muted mt-4 small" style="font-size: 0.78rem;">
            Kaldi's Coffee Ethiopia &bull; Telegram Reply Tracker (v2.0)
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('togglePasswordBtn')?.addEventListener('click', function() {
            const pwdInput = document.getElementById('password');
            const icon = document.getElementById('togglePasswordIcon');
            if (pwdInput.type === 'password') {
                pwdInput.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                pwdInput.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        });
    </script>
</body>
</html>
