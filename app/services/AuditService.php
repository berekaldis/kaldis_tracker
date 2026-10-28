<?php
namespace App\Services;

class AuditService
{
    /**
     * Record an audit log entry.
     */
    public static function log(
        string $action,
        ?string $entityType = null,
        ?string $entityId = null,
        array $details = [],
        ?int $adminId = null
    ): void {
        try {
            if ($adminId === null && isset($_SESSION['admin_user']['id'])) {
                $adminId = (int)$_SESSION['admin_user']['id'];
            }

            // Redact any sensitive keys if accidentally passed
            $sensitiveKeys = ['password', 'password_hash', 'bot_token', 'webhook_secret', 'secret'];
            foreach ($sensitiveKeys as $key) {
                if (isset($details[$key])) {
                    $details[$key] = '***REDACTED***';
                }
            }

            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'CLI', 0, 500);

            $sql = "INSERT INTO audit_logs (admin_id, action, entity_type, entity_id, details, ip_address, user_agent, created_at)
                    VALUES (:admin_id, :action, :entity_type, :entity_id, :details, :ip_address, :user_agent, :created_at)";

            Database::query($sql, [
                ':admin_id' => $adminId,
                ':action' => $action,
                ':entity_type' => $entityType,
                ':entity_id' => $entityId !== null ? (string)$entityId : null,
                ':details' => !empty($details) ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
                ':ip_address' => $ipAddress,
                ':user_agent' => $userAgent,
                ':created_at' => utc_now(),
            ]);
        } catch (\Throwable $e) {
            // Audit logging failure should not break critical application flow, but must be logged to disk
            error_log("Audit log failed: " . $e->getMessage());
        }
    }
}
