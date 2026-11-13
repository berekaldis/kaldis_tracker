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
}
