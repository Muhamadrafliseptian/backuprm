<?php
/**
 * file   : proses_clear_backup.php
 * path   : C:\xampp\htdocs\backuprm\actions\proses_clear_backup.php
 * fungsi : Kosongkan tabel s_backup_raw
 */
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/internal_guard.php';

csrf_check_or_die();

if (!auth_check()) {
    json_response(['ok' => false, 'msg' => 'Unauthorized'], 401);
}

try {
    $pdo = db();
    $count = (int)$pdo->query("SELECT COUNT(*) FROM s_backup_raw")->fetchColumn();
    $pdo->exec("TRUNCATE TABLE s_backup_raw");

    log_activity('clear_backup', "Kosongkan s_backup_raw ({$count} baris dihapus)");
    json_response([
        'ok'  => true,
        'msg' => "Backup raw dikosongkan ({$count} baris dihapus).",
    ]);
} catch (Throwable $e) {
    log_error('clear_backup: ' . $e->getMessage());
    json_response(['ok' => false, 'msg' => 'Gagal: ' . $e->getMessage()], 500);
}