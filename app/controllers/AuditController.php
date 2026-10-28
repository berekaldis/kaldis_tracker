<?php
namespace App\Controllers;

use App\Services\AuthService;
use App\Services\Database;

class AuditController
{
    public function index(): void
    {
        AuthService::requireLogin();

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 25;
        $offset = ($page - 1) * $perPage;

        $totalCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM audit_logs");

        $sql = "SELECT a.*, u.username as admin_username 
                FROM audit_logs a
                LEFT JOIN admins u ON a.admin_id = u.id
                ORDER BY a.created_at DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $logs = Database::fetchAll($sql);
        $totalPages = ceil($totalCount / $perPage);

        view('audit.index', [
            'logs' => $logs,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalCount' => $totalCount
        ]);
    }
}
