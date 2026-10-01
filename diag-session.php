<?php
/**
 * DIAGNOSA SESSION - file sementara, HAPUS setelah dipakai.
 * Akses:  https://domain/rekam-medis/diag-session.php
 *
 * File ini hanya menampilkan status, tidak membocorkan password.
 */
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

$out = [];
$out[] = '=== DIAGNOSA SESSION backup-rm ===';
$out[] = 'Waktu  : ' . date('Y-m-d H:i:s');
$out[] = 'PHP    : ' . PHP_VERSION;
$out[] = 'SAPI   : ' . PHP_SAPI;
$out[] = '';

/* ---------- 1. Konfigurasi session ---------- */
$out[] = '--- 1. KONFIGURASI ---';
$out[] = 'session.save_handler  = ' . var_export(ini_get('session.save_handler'), true);
$out[] = 'session.save_path     = [' . ini_get('session.save_path') . ']';

$sp = ini_get('session.save_path');
if (is_string($sp) && $sp !== '') {
    $out[] = '  folder ada?      ' . (is_dir($sp) ? 'YA' : 'TIDAK');
    $out[] = '  bisa ditulis?    ' . (is_writable($sp) ? 'YA' : 'TIDAK  <-- MASALAH');
} else {
    $out[] = '  save_path KOSONG -> PHP pakai default sistem';
    $out[] = '  default /tmp     bisa tulis? ' . (is_writable('/tmp') ? 'YA' : 'TIDAK');
}
$out[] = 'session.name         = ' . ini_get('session.name');
$out[] = 'session.cookie_secure= ' . var_export(ini_get('session.cookie_secure'), true);
$out[] = 'session.cookie_httponly = ' . var_export(ini_get('session.cookie_httponly'), true);
$out[] = '';

/* ---------- 2. Status session setelah start ---------- */
$out[] = '--- 2. STATUS SESSION ---';
$name = defined('SESSION_NAME') ? SESSION_NAME : 'BACKUPRM_SESS';
$out[] = 'nama sesi aplikasi  = ' . $name;
$out[] = 'session_status()     = ' . session_status();
$out[] = '  (1=PHP_SESSION_NONE, 2=ACTIVE)';
$out[] = 'session_id          = ' . (session_id() ?: '(kosong)');
$out[] = 'headers_sent()      = ' . var_export(headers_sent(), true);
$out[] = '';

/* ---------- 3. Header Cookie yang benar-benar dikirim ---------- */
$out[] = '--- 3. HEADER DIKIRIM ---';
if (headers_list()) {
    foreach (headers_list() as $h) {
        // sensor nilai cookie session
        $h = preg_replace('/=(.{0,6}).*/', '=$1...', $h);
        $out[] = '  ' . $h;
    }
} else {
    $out[] = '  (tidak ada header - session GAGAL start)';
}
$out[] = '';

/* ---------- 4. Error/warning terakhir ---------- */
$out[] = '--- 4. ERROR TERAKHIR ---';
$e = error_get_last();
if ($e) {
    $out[] = $e['type'] . ': ' . $e['message'];
    $out[] = 'di ' . $e['file'] . ':' . $e['line'];
} else {
    $out[] = '(tidak ada error tercatat)';
}
$out[] = '';

/* ---------- 5. Permission folder aplikasi ---------- */
$out[] = '--- 5. PERMISSION FOLDER APLIKASI ---';
$out[] = 'user PHP-FPM        = ' . (function_exists('posix_getpwuid') && function_exists('posix_geteuid')
    ? (posix_getpwuid(posix_geteuid())['name'] ?? 'tidak diketahui')
    : 'tidak diketahui (uid=' . (function_exists('posix_geteuid') ? posix_geteuid() : '?') . ')');
foreach (['logs', 'backups', 'uploads', 'database'] as $d) {
    $p = __DIR__ . '/' . $d;
    if (!is_dir($p)) { $out[] = str_pad($d, 10) . ' TIDAK ADA'; continue; }
    $out[] = str_pad($d, 10)
        . ' writable=' . (is_writable($p) ? 'YA' : 'TIDAK  <-- MASALAH')
        . '  perms=' . substr(sprintf('%o', fileperms($p)), -4);
}
$out[] = '';
$out[] = 'log_error() error.log = ' . (is_writable(__DIR__ . '/logs') ? 'bisa tulis' : 'TIDAK bisa tulis');

echo implode("\n", $out) . "\n";