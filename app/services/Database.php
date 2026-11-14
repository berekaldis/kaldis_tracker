<?php
namespace App\Services;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $config = require __DIR__ . '/../config/config.php';
            $dbConfig = $config['database'];

            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $dbConfig['host'],
                $dbConfig['port'],
                $dbConfig['name'],
                $dbConfig['charset']
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => true, // Allows repeated named parameters
            ];

            try {
                self::$instance = new PDO($dsn, $dbConfig['user'], $dbConfig['password'], $options);
            } catch (PDOException $e) {
                // Do not expose credentials in exception
                error_log("Database connection error: " . $e->getMessage());
                throw new PDOException("Database connection failed. Please check configuration and server status.");
            }
        }

        return self::$instance;
    }

    private static ?string $prefix = null;

    public static function getPrefix(): string
    {
        if (self::$prefix === null) {
            $config = require __DIR__ . '/../config/config.php';
            self::$prefix = $config['database']['prefix'] ?? '';
        }
        return self::$prefix;
    }

    public static function prefixQuery(string $sql): string
    {
        $prefix = self::getPrefix();
        if (empty($prefix)) {
            return $sql;
        }

        static $pattern = null;
        if ($pattern === null) {
            $tables = [
                'group_authorized_senders',
                'group_members',
                'telegram_groups',
                'tracked_messages',
                'message_responses',
                'telegram_updates',
                'system_settings',
                'login_attempts',
                'audit_logs',
                'members',
                'admins',
            ];
            $pattern = '/(?<!' . preg_quote($prefix, '/') . ')(`?)(' . implode('|', $tables) . ')(`?)(?![a-zA-Z0-9_])/i';
        }

        return preg_replace_callback($pattern, function ($m) use ($prefix) {
            return $m[1] . $prefix . $m[2] . $m[3];
        }, $sql);
    }

    public static function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::getConnection()->prepare(self::prefixQuery($sql));
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetch(string $sql, array $params = []): ?array
    {
        $stmt = self::query($sql, $params);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        $stmt = self::query($sql, $params);
        return $stmt->fetchAll();
    }

    public static function fetchColumn(string $sql, array $params = []): mixed
    {
        $stmt = self::query($sql, $params);
        return $stmt->fetchColumn();
    }

    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::query($sql, $params);
        return $stmt->rowCount();
    }

    public static function lastInsertId(): string
    {
        return self::getConnection()->lastInsertId();
    }

    public static function beginTransaction(): bool
    {
        return self::getConnection()->beginTransaction();
    }

    public static function commit(): bool
    {
        return self::getConnection()->commit();
    }

    public static function rollBack(): bool
    {
        if (self::getConnection()->inTransaction()) {
            return self::getConnection()->rollBack();
        }
        return false;
    }

    public static function inTransaction(): bool
    {
        return self::getConnection()->inTransaction();
    }

    private static bool $schemaEnsured = false;

    /**
     * Ensure all tables and required columns exist with the expected types.
     * Auto-migrates and repairs schema differences transparently.
     */
    public static function ensureSchemaIntegrity(bool $force = false): array
    {
        if (self::$schemaEnsured && !$force) {
            return ['status' => 'already_ensured'];
        }
        self::$schemaEnsured = true;

        $log = [];
        $pdo = self::getConnection();

        // 1. group_authorized_senders
        try {
            $pdo->exec(self::prefixQuery("
                CREATE TABLE IF NOT EXISTS group_authorized_senders (
                    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    group_id INT NOT NULL,
                    telegram_user_id BIGINT NOT NULL,
                    full_name VARCHAR(255) NULL,
                    username VARCHAR(100) NULL,
                    notes VARCHAR(255) NULL,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    UNIQUE KEY uq_gas_group_user (group_id, telegram_user_id),
                    KEY fk_gas_group (group_id),
                    KEY idx_gas_user (telegram_user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            "));
            $log[] = 'group_authorized_senders table verified';
        } catch (\Throwable $e) {
            $log[] = 'group_authorized_senders err: ' . $e->getMessage();
        }

        // 2. tracked_messages table & columns
        try {
            $pdo->exec(self::prefixQuery("
                CREATE TABLE IF NOT EXISTS tracked_messages (
                    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    group_id INT NOT NULL,
                    telegram_message_id BIGINT NOT NULL,
                    telegram_user_id BIGINT NOT NULL DEFAULT 0,
                    thread_id BIGINT DEFAULT NULL,
                    reference_code VARCHAR(64) NULL,
                    message_text TEXT NOT NULL,
                    message_date DATETIME NOT NULL,
                    deadline_at DATETIME NULL,
                    edited_at DATETIME NULL,
                    tracking_source ENUM('hashtag','command','dashboard','auto') NOT NULL DEFAULT 'auto',
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    UNIQUE KEY uq_group_message (group_id, telegram_message_id),
                    KEY idx_tm_date (message_date),
                    KEY idx_tm_group (group_id),
                    KEY idx_tm_ref (reference_code)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            "));

            $rows = $pdo->query(self::prefixQuery("SHOW COLUMNS FROM tracked_messages"))->fetchAll(\PDO::FETCH_ASSOC);
            $tmCols = [];
            foreach ($rows as $r) {
                $tmCols[strtolower($r['Field'])] = $r;
            }

            // Check telegram_user_id
            if (!isset($tmCols['telegram_user_id'])) {
                $pdo->exec(self::prefixQuery("ALTER TABLE tracked_messages ADD COLUMN telegram_user_id BIGINT NOT NULL DEFAULT 0 AFTER telegram_message_id"));
                $log[] = 'Added telegram_user_id to tracked_messages';
                if (isset($tmCols['user_id'])) {
                    $pdo->exec(self::prefixQuery("UPDATE tracked_messages SET telegram_user_id = user_id WHERE telegram_user_id = 0"));
                    $pdo->exec(self::prefixQuery("ALTER TABLE tracked_messages MODIFY COLUMN user_id BIGINT NULL DEFAULT NULL"));
                    $log[] = 'Migrated user_id to telegram_user_id in tracked_messages';
                } elseif (isset($tmCols['sender_id'])) {
                    $pdo->exec(self::prefixQuery("UPDATE tracked_messages SET telegram_user_id = sender_id WHERE telegram_user_id = 0"));
                    $pdo->exec(self::prefixQuery("ALTER TABLE tracked_messages MODIFY COLUMN sender_id BIGINT NULL DEFAULT NULL"));
                    $log[] = 'Migrated sender_id to telegram_user_id in tracked_messages';
                }
            }

            // Check thread_id
            if (!isset($tmCols['thread_id'])) {
                $pdo->exec(self::prefixQuery("ALTER TABLE tracked_messages ADD COLUMN thread_id BIGINT NULL AFTER telegram_user_id"));
                $log[] = 'Added thread_id to tracked_messages';
            }

            // Check reference_code
            if (!isset($tmCols['reference_code'])) {
                $pdo->exec(self::prefixQuery("ALTER TABLE tracked_messages ADD COLUMN reference_code VARCHAR(64) NULL AFTER thread_id"));
                $log[] = 'Added reference_code to tracked_messages';
            }

            // Check deadline_at
            if (!isset($tmCols['deadline_at'])) {
                $pdo->exec(self::prefixQuery("ALTER TABLE tracked_messages ADD COLUMN deadline_at DATETIME NULL AFTER message_date"));
                $log[] = 'Added deadline_at to tracked_messages';
            }

            // Check summary_sent_at (tracks when deadline summary report was delivered to authorized sender)
            if (!isset($tmCols['summary_sent_at'])) {
                $pdo->exec(self::prefixQuery("ALTER TABLE tracked_messages ADD COLUMN summary_sent_at DATETIME NULL AFTER deadline_at"));
                $log[] = 'Added summary_sent_at to tracked_messages';
            }

            // Check edited_at
            if (!isset($tmCols['edited_at'])) {
                $pdo->exec(self::prefixQuery("ALTER TABLE tracked_messages ADD COLUMN edited_at DATETIME NULL AFTER deadline_at"));
                $log[] = 'Added edited_at to tracked_messages';
            }

            // Check tracking_source ENUM
            if (isset($tmCols['tracking_source'])) {
                $type = strtolower($tmCols['tracking_source']['Type'] ?? '');
                if (!str_contains($type, "'auto'")) {
                    $pdo->exec(self::prefixQuery("ALTER TABLE tracked_messages MODIFY COLUMN tracking_source ENUM('hashtag', 'command', 'dashboard', 'auto') NOT NULL DEFAULT 'auto'"));
                    $log[] = 'Updated tracking_source ENUM to include auto';
                }
            }

            // Check is_active
            if (!isset($tmCols['is_active'])) {
                $pdo->exec(self::prefixQuery("ALTER TABLE tracked_messages ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER tracking_source"));
                $log[] = 'Added is_active to tracked_messages';
            }
        } catch (\Throwable $e) {
            $log[] = 'tracked_messages repair err: ' . $e->getMessage();
        }

        // 3. message_responses table & columns
        try {
            $rows = $pdo->query(self::prefixQuery("SHOW COLUMNS FROM message_responses"))->fetchAll(\PDO::FETCH_ASSOC);
            $mrCols = [];
            foreach ($rows as $r) {
                $mrCols[strtolower($r['Field'])] = $r;
            }

            if (!isset($mrCols['member_telegram_user_id'])) {
                $pdo->exec(self::prefixQuery("ALTER TABLE message_responses ADD COLUMN member_telegram_user_id BIGINT NOT NULL DEFAULT 0 AFTER member_id"));
                $log[] = 'Added member_telegram_user_id to message_responses';
            }
            if (!isset($mrCols['response_telegram_message_id'])) {
                $pdo->exec(self::prefixQuery("ALTER TABLE message_responses ADD COLUMN response_telegram_message_id BIGINT NULL AFTER member_telegram_user_id"));
                $log[] = 'Added response_telegram_message_id to message_responses';
            }
            if (!isset($mrCols['response_text'])) {
                $pdo->exec(self::prefixQuery("ALTER TABLE message_responses ADD COLUMN response_text TEXT NULL AFTER response_telegram_message_id"));
                $log[] = 'Added response_text to message_responses';
            }
            if (!isset($mrCols['response_date'])) {
                $pdo->exec(self::prefixQuery("ALTER TABLE message_responses ADD COLUMN response_date DATETIME NULL AFTER response_text"));
                if (isset($mrCols['created_at'])) {
                    $pdo->exec(self::prefixQuery("UPDATE message_responses SET response_date = created_at WHERE response_date IS NULL"));
                }
                $log[] = 'Added response_date to message_responses';
            }
            if (!isset($mrCols['reviewed_at'])) {
                $pdo->exec(self::prefixQuery("ALTER TABLE message_responses ADD COLUMN reviewed_at DATETIME NULL AFTER reviewed_by_admin_id"));
                $log[] = 'Added reviewed_at to message_responses';
            }
            if (!isset($mrCols['excluded_reason'])) {
                $pdo->exec(self::prefixQuery("ALTER TABLE message_responses ADD COLUMN excluded_reason TEXT NULL AFTER matching_method"));
                $log[] = 'Added excluded_reason to message_responses';
            }
            if (!isset($mrCols['matching_method'])) {
                $pdo->exec(self::prefixQuery("ALTER TABLE message_responses ADD COLUMN matching_method ENUM('direct_reply', 'reference', 'manual', 'reaction') NULL AFTER status"));
                $log[] = 'Added matching_method to message_responses';
            }
            if (isset($mrCols['status'])) {
                $type = strtolower($mrCols['status']['Type'] ?? '');
                if (!str_contains($type, "'excluded'")) {
                    $pdo->exec(self::prefixQuery("ALTER TABLE message_responses MODIFY COLUMN status ENUM('replied', 'pending_review', 'rejected', 'unknown', 'excluded') NOT NULL DEFAULT 'unknown'"));
                    $log[] = 'Updated status ENUM in message_responses';
                }
            }
        } catch (\Throwable $e) {
            $log[] = 'message_responses repair err: ' . $e->getMessage();
        }

        // 4. members table & columns
        try {
            $rows = $pdo->query(self::prefixQuery("SHOW COLUMNS FROM members"))->fetchAll(\PDO::FETCH_ASSOC);
            $memCols = [];
            foreach ($rows as $r) {
                $memCols[strtolower($r['Field'])] = $r;
            }

            if (!isset($memCols['telegram_user_id'])) {
                $pdo->exec(self::prefixQuery("ALTER TABLE members ADD COLUMN telegram_user_id BIGINT NOT NULL DEFAULT 0 AFTER id"));
                $log[] = 'Added telegram_user_id to members';
                if (isset($memCols['user_id'])) {
                    $pdo->exec(self::prefixQuery("UPDATE members SET telegram_user_id = user_id WHERE telegram_user_id = 0"));
                    $pdo->exec(self::prefixQuery("ALTER TABLE members MODIFY COLUMN user_id BIGINT NULL DEFAULT NULL"));
                }
            }
        } catch (\Throwable $e) {
            $log[] = 'members repair err: ' . $e->getMessage();
        }

        // 5. telegram_groups table & columns
        try {
            $rows = $pdo->query(self::prefixQuery("SHOW COLUMNS FROM telegram_groups"))->fetchAll(\PDO::FETCH_ASSOC);
            $grpCols = [];
            foreach ($rows as $r) {
                $grpCols[strtolower($r['Field'])] = $r;
            }

            if (!isset($grpCols['last_update_at'])) {
                $pdo->exec(self::prefixQuery("ALTER TABLE telegram_groups ADD COLUMN last_update_at DATETIME NULL AFTER last_health_check_at"));
                $log[] = 'Added last_update_at to telegram_groups';
            }
            if (!isset($grpCols['previous_chat_id'])) {
                $pdo->exec(self::prefixQuery("ALTER TABLE telegram_groups ADD COLUMN previous_chat_id BIGINT NULL AFTER last_update_at"));
                $log[] = 'Added previous_chat_id to telegram_groups';
            }
        } catch (\Throwable $e) {
            $log[] = 'telegram_groups repair err: ' . $e->getMessage();
        }

        // 6. Seed system settings defaults
        try {
            $pdo->exec(self::prefixQuery("
                INSERT IGNORE INTO system_settings (setting_key, setting_value, updated_at) VALUES 
                ('telegram_tracking_mode', 'all_authorized', UTC_TIMESTAMP()),
                ('telegram_track_all_messages', '1', UTC_TIMESTAMP()),
                ('telegram_track_hashtag', '#track', UTC_TIMESTAMP()),
                ('telegram_track_command', '/track', UTC_TIMESTAMP()),
                ('telegram_default_deadline_hours', '24', UTC_TIMESTAMP()),
                ('telegram_notify_sender_on_deadline', '1', UTC_TIMESTAMP()),
                ('telegram_tracked_message_retention_days', '30', UTC_TIMESTAMP()),
                ('app_retention_days', '30', UTC_TIMESTAMP())
            "));
        } catch (\Throwable) {}

        return ['status' => 'ensured', 'log' => $log];
    }
}
