<?php
/**
 * file   : proses_clear_log.php
 * path   : C:\xampp\htdocs\backuprm\actions\proses_clear_log.php
 * fungsi : Bersihkan log (truncate tabel + kosongkan file log)
 *
 * Opsi POST:
 *   - include_error=1 → juga kosongkan error.log
 */
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/internal_guard.php';

csrf_check_or_die();

if (!auth_check()) {
    json_response(['ok' => false, 'msg' => 'Unauthorized'], 401);
}

$includeError = ($_POST['include_error'] ?? '0') === '1';

try {
    /* ---------- 1. Truncate tabel ---------- */
    $count = (int)db()->query("SELECT COUNT(*) FROM l_activity")->fetchColumn();
    db()->exec("TRUNCATE TABLE l_activity");

    /* ---------- 2. Kosongkan file log ---------- */
    @file_put_contents(LOG_ACTIVITY, '');
    if ($includeError) {
        @file_put_contents(LOG_ERROR, '');
    }

    /* ---------- 3. Catat aksi ini sendiri ---------- */
    log_activity(
        'clear_log',
        sprintf(
            'Semua log dibersihkan (%d baris, error.log: %s) oleh %s',
            $count,
            $includeError ? 'ya' : 'tidak',
            auth_user()['nrk'] ?? '-'
        )
    );

    json_response([
        'ok'  => true,
        'msg' => "Log berhasil dibersihkan ({$count} baris).",
    ]);
} catch (Throwable $e) {
    log_error('clear_log: ' . $e->getMessage());
    json_response(['ok' => false, 'msg' => 'Gagal: ' . $e->getMessage()], 500);
}