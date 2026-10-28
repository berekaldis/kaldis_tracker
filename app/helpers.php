<?php
/**
 * Kaldis Coffee Telegram Reply Tracker (v2)
 * Global Helper Functions
 */

use App\Services\AuthService;

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        // Check dynamic database settings first
        $dbKey = str_replace('.', '_', $key);
        $dbVal = \App\Services\SettingService::get($dbKey);
        if ($dbVal !== null && $dbVal !== '') {
            if ($key === 'telegram.authorized_user_ids' && is_string($dbVal)) {
                return array_filter(array_map('trim', explode(',', $dbVal)));
            }
            if ($key === 'telegram.track_all_messages') {
                return (bool)(int)$dbVal;
            }
            if ($key === 'app.retention_days') {
                return (int)$dbVal;
            }
            return $dbVal;
        }

        static $config = null;
        if ($config === null) {
            $config = require __DIR__ . '/config/config.php';
        }

        $segments = explode('.', $key);
        $current = $config;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }
            $current = $current[$segment];
        }

        return $current;
    }
}

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return AuthService::getCsrfToken();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        $token = csrf_token();
        return '<input type="hidden" name="csrf_token" value="' . e($token) . '">';
    }
}

if (!function_exists('utc_now')) {
    function utc_now(): string
    {
        $dt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        return $dt->format('Y-m-d H:i:s');
    }
}

if (!function_exists('format_tz')) {
    function format_tz(?string $utcDate, string $format = 'M j, Y h:i A'): string
    {
        if (empty($utcDate)) {
            return '—';
        }

        try {
            $tzTarget = new DateTimeZone(config('app.timezone', 'Africa/Addis_Ababa'));
            $dt = new DateTimeImmutable($utcDate, new DateTimeZone('UTC'));
            return $dt->setTimezone($tzTarget)->format($format);
        } catch (Exception) {
            return (string)$utcDate;
        }
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url): void
    {
        header("Location: " . $url);
        exit;
    }
}

if (!function_exists('flash')) {
    function flash(?string $type = null, ?string $message = null): mixed
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if ($type !== null && $message !== null) {
            $_SESSION['flash_messages'][$type][] = $message;
            return null;
        }

        $messages = $_SESSION['flash_messages'] ?? [];
        if ($type !== null) {
            $result = $messages[$type] ?? [];
            unset($_SESSION['flash_messages'][$type]);
            return $result;
        }

        unset($_SESSION['flash_messages']);
        return $messages;
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $base = rtrim(config('app.url', ''), '/');
        $path = ltrim($path, '/');
        return $path === '' ? $base : $base . '/' . $path;
    }
}

if (!function_exists('json_response')) {
    function json_response(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('view')) {
    function view(string $viewPath, array $data = [], string $layout = 'main'): void
    {
        extract($data);
        $viewFile = __DIR__ . '/views/' . str_replace('.', '/', $viewPath) . '.php';

        if (!file_exists($viewFile)) {
            throw new RuntimeException("View file not found: {$viewPath}");
        }

        if ($layout === 'none') {
            require $viewFile;
            return;
        }

        $layoutFile = __DIR__ . '/views/layouts/' . $layout . '.php';
        if (!file_exists($layoutFile)) {
            throw new RuntimeException("Layout file not found: {$layout}");
        }

        // Buffer view content
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        // Render layout with content
        require $layoutFile;
    }
}
