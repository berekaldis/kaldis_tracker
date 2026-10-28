<?php
namespace App\Services;

class AuthService
{
    /**
     * Start and configure secure session.
     */
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $lifetime = (int)config('security.session_lifetime', 7200);

        if (!headers_sent()) {
            $cookieParams = [
                'lifetime' => $lifetime,
                'path' => '/',
                'domain' => '',
                'secure' => (bool)config('security.session_secure', false),
                'httponly' => true,
                'samesite' => 'Lax',
            ];

            session_name(config('security.session_name', 'kaldis_session'));
            session_set_cookie_params($cookieParams);
            session_start();
        } else {
            @session_start();
        }

        // Check for session timeout
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $lifetime)) {
            self::logout();
            return;
        }
        $_SESSION['last_activity'] = time();
    }

    /**
     * Check if current visitor is authenticated.
     */
    public static function check(): bool
    {
        self::startSession();
        return isset($_SESSION['admin_user']) && !empty($_SESSION['admin_user']['id']);
    }

    /**
     * Get logged-in admin user record.
     */
    public static function user(): ?array
    {
        self::startSession();
        return $_SESSION['admin_user'] ?? null;
    }

    /**
     * Check if logged in user has 'super_admin' role.
     */
    public static function isSuperAdmin(): bool
    {
        $u = self::user();
        return $u !== null && ($u['role'] ?? '') === 'super_admin';
    }

    /**
     * Check if logged in user has 'owner' or 'super_admin' role.
     */
    public static function isOwner(): bool
    {
        $u = self::user();
        return $u !== null && in_array(($u['role'] ?? ''), ['super_admin', 'owner']);
    }

    /**
     * Require super_admin role for privileged configuration actions.
     */
    public static function requireSuperAdmin(): void
    {
        self::requireLogin();
        if (!self::isSuperAdmin()) {
            flash('danger', 'Access denied: Only Super Administrator can access this section.');
            redirect(url('dashboard'));
        }
    }

    /**
     * Require authentication; redirect to login if not logged in.
     */
    public static function requireLogin(): void
    {
        if (!self::check()) {
            flash('warning', 'Please sign in to access the system.');
            redirect(url('login'));
        }
    }

    /**
     * Require owner or super_admin role for write/administrative actions.
     */
    public static function requireOwner(): void
    {
        self::requireLogin();
        if (!self::isOwner()) {
            flash('danger', 'Access denied: Viewer role does not have permission to perform this action.');
            redirect(url('dashboard'));
        }
    }

    /**
     * Attempt login with rate-limiting and lockout.
     */
    public static function attempt(string $username, string $password): array
    {
        self::startSession();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $maxAttempts = (int)config('security.max_login_attempts', 5);
        $lockoutSeconds = (int)config('security.lockout_time_seconds', 900);

        // Check lockout status
        $lockoutCheck = self::checkLockout($ip, $username, $maxAttempts, $lockoutSeconds);
        if ($lockoutCheck['locked']) {
            AuditService::log('login_locked_out', 'admin', null, [
                'username' => $username,
                'ip' => $ip,
                'minutes_remaining' => $lockoutCheck['minutes_remaining']
            ]);
            return [
                'success' => false,
                'error' => "Too many failed login attempts. Account temporarily locked. Please try again in {$lockoutCheck['minutes_remaining']} minute(s)."
            ];
        }

        // Query user by username or email
        $user = Database::fetch(
            "SELECT * FROM admins WHERE (username = :u1 OR email = :u2) AND is_active = 1 LIMIT 1",
            [':u1' => $username, ':u2' => $username]
        );

        if (!$user || !password_verify($password, $user['password_hash'])) {
            self::recordLoginAttempt($ip, $username, false);
            AuditService::log('login_failed', 'admin', null, [
                'username' => $username,
                'ip' => $ip
            ]);
            return [
                'success' => false,
                'error' => 'Invalid credentials or inactive account.'
            ];
        }

        // Success: record attempt, regenerate session id, store session data
        self::recordLoginAttempt($ip, $username, true);
        if (!headers_sent()) {
            session_regenerate_id(true);
        }

        $_SESSION['admin_user'] = [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role'],
        ];
        $_SESSION['last_activity'] = time();

        // Update last login in database
        Database::execute(
            "UPDATE admins SET last_login_at = :now, last_login_ip = :ip WHERE id = :id",
            [
                ':now' => utc_now(),
                ':ip' => $ip,
                ':id' => $user['id']
            ]
        );

        AuditService::log('login_success', 'admin', (string)$user['id'], [
            'username' => $user['username'],
            'role' => $user['role'],
            'ip' => $ip
        ], (int)$user['id']);

        return ['success' => true];
    }

    /**
     * Terminate session and log out.
     */
    public static function logout(): void
    {
        self::startSession();
        $user = self::user();
        if ($user) {
            AuditService::log('logout', 'admin', (string)$user['id'], ['username' => $user['username']]);
        }

        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        session_destroy();
    }

    /**
     * Record login attempt in database.
     */
    private static function recordLoginAttempt(string $ip, string $username, bool $success): void
    {
        try {
            Database::execute(
                "INSERT INTO login_attempts (ip_address, username, attempted_at, success) VALUES (:ip, :user, :at, :succ)",
                [
                    ':ip' => $ip,
                    ':user' => substr($username, 0, 100),
                    ':at' => utc_now(),
                    ':succ' => $success ? 1 : 0
                ]
            );
        } catch (\Throwable $e) {
            error_log("Failed to record login attempt: " . $e->getMessage());
        }
    }

    /**
     * Check if IP or username is locked out.
     */
    private static function checkLockout(string $ip, string $username, int $maxAttempts, int $lockoutSeconds): array
    {
        try {
            $windowStart = (new \DateTimeImmutable("-{$lockoutSeconds} seconds", new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

            $failedAttempts = (int)Database::fetchColumn(
                "SELECT COUNT(*) FROM login_attempts 
                 WHERE (ip_address = :ip OR username = :u) 
                   AND success = 0 
                   AND attempted_at >= :window_start",
                [
                    ':ip' => $ip,
                    ':u' => $username,
                    ':window_start' => $windowStart
                ]
            );

            if ($failedAttempts >= $maxAttempts) {
                // Find oldest attempt in window to calculate remaining minutes
                $earliest = Database::fetchColumn(
                    "SELECT MIN(attempted_at) FROM login_attempts 
                     WHERE (ip_address = :ip OR username = :u) 
                       AND success = 0 
                       AND attempted_at >= :window_start",
                    [
                        ':ip' => $ip,
                        ':u' => $username,
                        ':window_start' => $windowStart
                    ]
                );

                $earliestTime = strtotime($earliest . ' UTC');
                $elapsed = time() - $earliestTime;
                $remainingSeconds = max(0, $lockoutSeconds - $elapsed);
                $remainingMinutes = ceil($remainingSeconds / 60);

                return [
                    'locked' => true,
                    'minutes_remaining' => (int)$remainingMinutes
                ];
            }
        } catch (\Throwable $e) {
            error_log("Lockout check error: " . $e->getMessage());
        }

        return ['locked' => false, 'minutes_remaining' => 0];
    }

    /**
     * CSRF token generation and validation.
     */
    public static function getCsrfToken(): string
    {
        self::startSession();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function validateCsrfToken(?string $token): bool
    {
        self::startSession();
        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function verifyCsrf(): void
    {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!self::validateCsrfToken($token)) {
            http_response_code(403);
            die("403 Forbidden: Invalid or missing CSRF token.");
        }
    }
}
