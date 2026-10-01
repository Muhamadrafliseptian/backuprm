<?php
/**
 * file   : proses_reset_all.php
 * path   : C:\xampp\htdocs\backuprm\actions\proses_reset_all.php
 * fungsi : Reset SEMUA data — master + transaksi + staging + log
 *          WAJIB verifikasi password user yang login
 */
declare(strict_types=1);

csrf_check_or_die();
set_time_limit(600);

if (!auth_check()) {
    json_response(['ok' => false, 'msg' => 'Unauthorized'], 401);
}

/* ============================================================
   Ambil input
   ============================================================ */
$password    = (string)($_POST['password'] ?? '');
$confirmText = trim((string)($_POST['confirm'] ?? ''));

/* ============================================================
   Validasi input
   ============================================================ */
if ($password === '') {
    json_response(['ok' => false, 'msg' => 'Password wajib diisi.'], 400);
}

if ($confirmText !== 'RESET') {
    json_response([
        'ok'  => false,
        'msg' => 'Ketik "RESET" (huruf besar) untuk konfirmasi.',
    ], 400);
}

/* ============================================================
   Verifikasi password user yang login
   ============================================================ */
$currentUser = auth_user();
$nrk = $currentUser['nrk'] ?? '';

if ($nrk === '') {
    json_response(['ok' => false, 'msg' => 'Sesi tidak valid.'], 401);
}

$pegawai = auth_find_by_nrk($nrk);
if (!$pegawai) {
    log_error("RESET ALL: user nrk={$nrk} tidak ditemukan di m_pegawai");
    json_response(['ok' => false, 'msg' => 'User tidak ditemukan.'], 404);
}

if (!password_verify($password, $pegawai['peg_password'])) {
    log_error("RESET ALL: password salah untuk nrk={$nrk} ip=" . ($_SERVER['REMOTE_ADDR'] ?? '-'));
    json_response(['ok' => false, 'msg' => 'Password salah.'], 401);
}

/* ============================================================
   Verifikasi tambahan: hanya NRK 8813084
   (bisa dihapus kalau mau semua admin bisa reset)
   ============================================================ */
if ($nrk !== '8813084') {
    log_error("RESET ALL: user nrk={$nrk} tidak berwenang reset");
    json_response([
        'ok'  => false,
        'msg' => 'Hanya user dengan NRK 8813084 yang boleh melakukan reset.',
    ], 403);
}

/* ============================================================
   Eksekusi reset
   ============================================================ */
try {
    $pdo = db();

    /* ---------- Daftar tabel yang akan dikosongkan ---------- */
    $tablesToReset = [
        // Log & Staging (urutan pertama — tidak ada FK ke tabel lain)
        'l_activity',
        's_upload_batch',
        's_backup_raw',

        // Transaksi (anak → induk)
        'd_resep_detail',
        't_resep',
        't_tindakan',
        't_diagnosis',
        't_pemeriksaan',
        't_laboratorium',
        't_kunjungan',

        // Master terkait
        'm_pasien',
        'm_obat',
        'm_tenaga_kesehatan',

        // CATATAN: m_pegawai TIDAK dihapus (agar user tetap bisa login)
    ];

    /* ---------- Hitung dulu sebelum dihapus (untuk log) ---------- */
    $before = [];
    foreach ($tablesToReset as $t) {
        try {
            $before[$t] = (int)$pdo->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
        } catch (Throwable $e) {
            $before[$t] = null;
        }
    }

    /* ---------- Kosongkan file log fisik ---------- */
    $logFilesCleared = [];
    try {
        if (file_exists(LOG_ACTIVITY) && filesize(LOG_ACTIVITY) > 0) {
            @file_put_contents(LOG_ACTIVITY, '');
            $logFilesCleared[] = basename(LOG_ACTIVITY);
        }
        if (file_exists(LOG_ERROR) && filesize(LOG_ERROR) > 0) {
            @file_put_contents(LOG_ERROR, '');
            $logFilesCleared[] = basename(LOG_ERROR);
        }
    } catch (Throwable $eLog) {
        // Tidak fatal kalau gagal
    }

    /* ---------- Eksekusi TRUNCATE dengan FK checks off ---------- */
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    $affected = [];
    foreach ($tablesToReset as $t) {
        try {
            $pdo->exec("TRUNCATE TABLE `{$t}`");
            $affected[$t] = $before[$t] ?? 0;
        } catch (Throwable $e) {
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            log_error("RESET ALL gagal di tabel {$t}: " . $e->getMessage());
            json_response([
                'ok'  => false,
                'msg' => "Gagal reset tabel {$t}: " . $e->getMessage(),
            ], 500);
        }
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    /* ---------- Hitung total baris terhapus ---------- */
    $totalRows = 0;
    foreach ($affected as $v) {
        if (is_int($v)) $totalRows += $v;
    }

    /* ---------- Tulis log aktivitas reset (SETELAH semua selesai) ---------- */
    log_activity(
        'reset_all',
        sprintf(
            'RESET ALL oleh %s (%s): %d tabel, %d baris dihapus, file log dibersihkan: %s. Detail: %s',
            $currentUser['nama'] ?? '-',
            $nrk,
            count($affected),
            $totalRows,
            empty($logFilesCleared) ? 'tidak ada' : implode(', ', $logFilesCleared),
            json_encode($affected)
        ),
        'warning'
    );

    /* ---------- Response ---------- */
    json_response([
        'ok'          => true,
        'msg'         => "Reset berhasil. {$totalRows} baris dihapus dari " . count($affected) . " tabel.",
        'affected'    => $affected,
        'total'       => $totalRows,
        'log_cleared' => $logFilesCleared,
    ]);

} catch (Throwable $e) {
    try { db()->exec("SET FOREIGN_KEY_CHECKS = 1"); } catch (Throwable $e2) {}
    log_error('RESET ALL: ' . $e->getMessage());
    json_response(['ok' => false, 'msg' => 'Gagal: ' . $e->getMessage()], 500);
}