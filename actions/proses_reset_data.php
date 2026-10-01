<?php
/**
 * file   : proses_reset_data.php
 * path   : C:\xampp\htdocs\backuprm\actions\proses_reset_data.php
 * fungsi : Hapus semua data dari 10 tabel normalisasi (s_backup_raw dipertahankan)
 */
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/internal_guard.php';

csrf_check_or_die();

if (!auth_check()) {
    json_response(['ok' => false, 'msg' => 'Unauthorized'], 401);
}

try {
    $pdo = db();
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    foreach ([
        'd_resep_detail', 't_resep', 'm_obat',
        't_tindakan', 't_diagnosis', 't_pemeriksaan',
        't_laboratorium', 't_kunjungan', 'm_pasien',
        'm_tenaga_kesehatan'
    ] as $t) {
        $pdo->exec("TRUNCATE TABLE `{$t}`");
    }
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    log_activity('reset_data', 'Reset 10 tabel normalisasi (s_backup_raw dipertahankan)');
    json_response(['ok' => true, 'msg' => 'Data berhasil di-reset.']);
} catch (Throwable $e) {
    log_error('reset_data: ' . $e->getMessage());
    json_response(['ok' => false, 'msg' => 'Gagal: ' . $e->getMessage()], 500);
}