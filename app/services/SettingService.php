<?php
namespace App\Services;

class SettingService
{
    private static ?array $cache = null;

    /**
     * Load all settings into static memory cache.
     */
    public static function load(): array
    {
        if (self::$cache === null) {
            try {
                $rows = Database::fetchAll("SELECT setting_key, setting_value FROM system_settings");
                self::$cache = [];
                foreach ($rows as $r) {
                    self::$cache[$r['setting_key']] = $r['setting_value'];
                }
            } catch (\Throwable) {
                self::$cache = [];
            }
        }
        return self::$cache;
    }

    /**
     * Get a setting by key, falling back to default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::load();
        if (array_key_exists($key, $all) && $all[$key] !== null && $all[$key] !== '') {
            return $all[$key];
        }
        return $default;
    }

    /**
     * Set a setting in system_settings table.
     */
    public static function set(string $key, ?string $value): void
    {
        $now = utc_now();
        Database::execute(
            "INSERT INTO system_settings (setting_key, setting_value, updated_at) 
             VALUES (:key, :val, :updated_at)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)",
            [
                ':key' => $key,
                ':val' => $value,
                ':updated_at' => $now,
            ]
        );

        if (self::$cache !== null) {
            self::$cache[$key] = $value;
        }
    }

    /**
     * Set multiple settings at once.
     */
    public static function setMany(array $settings): void
    {
        foreach ($settings as $k => $v) {
            self::set($k, $v !== null ? (string)$v : null);
        }
    }
}
