<?php
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

require_once __DIR__ . '/../app/helpers.php';

use App\Services\Database;
use App\Services\AuthService;
use App\Controllers\AuthorizedSenderController;

echo "========================================================\n";
echo "Starting Authorized Senders Edit Test Suite\n";
echo "========================================================\n\n";

$passCount = 0;
$failCount = 0;

function it(string $desc, bool $condition): void {
    global $passCount, $failCount;
    if ($condition) {
        echo "  [PASS] {$desc}\n";
        $passCount++;
    } else {
        echo "  [FAIL] {$desc}\n";
        $failCount++;
    }
}

// Ensure test groups exist
$g21 = Database::fetch("SELECT id FROM telegram_groups WHERE id = 21");
if (!$g21) {
    Database::execute("INSERT IGNORE INTO telegram_groups (id, telegram_chat_id, title, department, is_active, created_at, updated_at) VALUES (21, -1002100000000, 'Operations Group', 'Operations', 1, NOW(), NOW())");
}

// 1. Create or retrieve a test sender
Database::execute(
    "INSERT INTO group_authorized_senders (group_id, telegram_user_id, full_name, username, notes, is_active, created_at, updated_at)
     VALUES (21, 888777666, 'Original Sender Name', 'orig_sender', 'Original Role', 1, NOW(), NOW())
     ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), notes = VALUES(notes)"
);

$sender = Database::fetch("SELECT * FROM group_authorized_senders WHERE group_id = 21 AND telegram_user_id = 888777666");
it("Test sender exists in group_authorized_senders", !empty($sender));

// 2. Simulate Super Admin login
$_SESSION['admin_user'] = [
    'id' => 1,
    'username' => 'superadmin',
    'email' => 'superadmin@kaldisbunna.et',
    'role' => 'super_admin',
    'department' => null,
    'assigned_group_id' => null,
];
$_POST['csrf_token'] = csrf_token();

// 3. Test View has Edit Button and Edit Modal
$_SERVER['REQUEST_URI'] = '/senders?group_id=21';
ob_start();
(new AuthorizedSenderController())->index();
$html = ob_get_clean();

it("Senders view contains Edit button with data attributes", str_contains($html, 'btn-edit-sender') && str_contains($html, 'data-id='));
it("Senders view contains Edit Authorized Sender modal", str_contains($html, 'id="modalEditSender"'));
it("Senders view posts to senders/update", str_contains($html, 'action="') && str_contains($html, 'senders/update'));

// 4. Test Update action via CLI subprocess so exit doesn't terminate test runner
$runnerScript = __DIR__ . '/test_sender_update_action.php';
file_put_contents($runnerScript, '<?php
spl_autoload_register(function ($class) {
    $prefix = "App\\\\";
    $baseDir = dirname(__DIR__) . "/app/";
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace("\\\\", "/", $relativeClass) . ".php";
    if (file_exists($file)) require $file;
});
require_once __DIR__ . "/../app/helpers.php";
use App\Controllers\AuthorizedSenderController;

session_start();
$_SESSION["admin_user"] = [
    "id" => 1,
    "username" => "superadmin",
    "email" => "superadmin@kaldisbunna.et",
    "role" => "super_admin",
    "department" => null,
    "assigned_group_id" => null,
];
$_POST["csrf_token"] = csrf_token();
$_POST["sender_id"] = ' . (int)$sender['id'] . ';
$_POST["group_id"] = 21;
$_POST["telegram_user_id"] = "888777666";
$_POST["full_name"] = "Updated Sender Name (Lead)";
$_POST["username"] = "@updated_sender";
$_POST["notes"] = "Promoted to Operations Manager";
$_POST["is_active"] = "1";

(new AuthorizedSenderController())->update();
');

exec("php " . escapeshellarg($runnerScript), $out, $code);
@unlink($runnerScript);

$updated = Database::fetch("SELECT * FROM group_authorized_senders WHERE id = :id", [':id' => $sender['id']]);
it("Sender full_name was updated", ($updated['full_name'] ?? '') === 'Updated Sender Name (Lead)');
it("Sender username was updated without @", ($updated['username'] ?? '') === 'updated_sender');
it("Sender notes were updated", ($updated['notes'] ?? '') === 'Promoted to Operations Manager');

// 5. Test Access Control: Group Authorizer restricted from updating other groups
$deniedScript = __DIR__ . '/test_sender_denied_action.php';
file_put_contents($deniedScript, '<?php
spl_autoload_register(function ($class) {
    $prefix = "App\\\\";
    $baseDir = dirname(__DIR__) . "/app/";
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace("\\\\", "/", $relativeClass) . ".php";
    if (file_exists($file)) require $file;
});
require_once __DIR__ . "/../app/helpers.php";
use App\Controllers\AuthorizedSenderController;

session_start();
$_SESSION["admin_user"] = [
    "id" => 4,
    "username" => "authorizer_g65",
    "email" => "authorizer65@kaldisbunna.et",
    "role" => "group_authorizer",
    "department" => "Operations",
    "assigned_group_id" => 65,
];
$_POST["csrf_token"] = csrf_token();
$_POST["sender_id"] = ' . (int)$sender['id'] . '; // Sender in group 21

// Register shutdown function to capture session flash message upon exit
register_shutdown_function(function() {
    $flash = json_encode($_SESSION["flash_messages"] ?? []);
    echo "FLASH:" . $flash;
});

(new AuthorizedSenderController())->update();
');

$deniedOut = shell_exec("php " . escapeshellarg($deniedScript));
@unlink($deniedScript);

it("Group authorizer cannot update sender in non-assigned group", str_contains((string)$deniedOut, 'Access denied'));

// Clean up test sender
Database::execute("DELETE FROM group_authorized_senders WHERE telegram_user_id = 888777666");

echo "\n========================================================\n";
echo "Test Summary: {$passCount} Passed, {$failCount} Failed\n";
echo "========================================================\n";

if ($failCount > 0) {
    exit(1);
}
