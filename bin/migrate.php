<?php
/**
 * Kaldis Coffee Telegram Reply Tracker (v2)
 * Database Migration & Admin User Seeder CLI
 */

declare(strict_types=1);

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = dirname(__DIR__) . '/app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require $file;
});

require_once dirname(__DIR__) . '/app/helpers.php';

use App\Services\Database;

echo "========================================================\n";
echo "Kaldis Coffee Telegram Reply Tracker - Migration CLI\n";
echo "========================================================\n\n";

try {
    $pdo = Database::getConnection();
    echo "[+] Database connection established successfully.\n";

    // 1. Run Migrations
    $migrationFiles = glob(dirname(__DIR__) . '/database/migrations/*.sql');
    sort($migrationFiles);

    foreach ($migrationFiles as $file) {
        $filename = basename($file);
        echo "[*] Running migration: {$filename}... ";
        $sql = file_get_contents($file);
        $pdo->exec($sql);
        echo "DONE\n";
    }

    // 2. Check if default Owner Admin exists, or prompt/create one
    $adminCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM admins");
    if ($adminCount === 0) {
        echo "\n[*] No administrator account found. Creating default owner account...\n";
        $defaultUser = 'admin';
        $defaultEmail = 'admin@kaldiscoffee.com';
        $defaultPass = 'KaldisAdmin2026!';
        $passHash = password_hash($defaultPass, PASSWORD_DEFAULT);
        $now = utc_now();

        Database::execute(
            "INSERT INTO admins (username, email, password_hash, role, is_active, created_at, updated_at) 
             VALUES (:u, :e, :p, 'owner', 1, :created_at, :updated_at)",
            [
                ':u' => $defaultUser,
                ':e' => $defaultEmail,
                ':p' => $passHash,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]
        );

        echo "--------------------------------------------------------\n";
        echo "Default Owner Account Created:\n";
        echo "Username: {$defaultUser}\n";
        echo "Email:    {$defaultEmail}\n";
        echo "Password: {$defaultPass}\n";
        echo "Role:     owner\n";
        echo "IMPORTANT: Please change this password upon initial login!\n";
        echo "--------------------------------------------------------\n";
    } else {
        echo "[+] Administrators already present in database ({$adminCount} existing).\n";
    }

    echo "\n[+] Database migration completed successfully!\n";
} catch (\Throwable $e) {
    echo "\n[-] Migration failed with error: " . $e->getMessage() . "\n";
    exit(1);
}
