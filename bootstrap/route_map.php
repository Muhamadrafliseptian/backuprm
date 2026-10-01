<?php
/**
 * file   : route_map.php
 * path   : bootstrap/route_map.php
 * fungsi : Daftar route halaman (nama stub .php di root)
 *
 * Dipakai oleh:
 *   - route.php                 untuk memvalidasi stub yang dipanggil
 *   - tools/generate-stubs.php  untuk membuat stub
 *   - bootstrap/routes.php      untuk memetakan ke file di pages/
 *
 * Format: 'nama_stub' => 'file_yang_ada_di_pages/'
 *
 * CATATAN: 'logout' sengaja tidak ada di sini karena logout itu action,
 * bukan halaman. File-nya ada di actions/logout.php (lihat action_map.php).
 */
declare(strict_types=1);

require_once __DIR__ . '/internal_guard.php';

return [
    'login'            => 'login.php',
    'dashboard'        => 'dashboard.php',
    'about'            => 'about.php',
    'pasien'           => 'pasien.php',
    'kunjungan'        => 'kunjungan.php',
    'kunjungan-detail' => 'kunjungan_detail.php',
    'resume-medis'     => 'resume_medis.php',
    'verifikasi'       => 'verifikasi.php',
    'tenaga-kesehatan' => 'tenaga_kesehatan.php',
    'obat'             => 'obat.php',
    'laboratorium'     => 'laboratorium.php',
    'upload'           => 'upload.php',
    'generate'         => 'generate.php',
    'log'              => 'log.php',
    'backup-all'       => 'backup_all.php',
];