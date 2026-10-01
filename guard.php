<?php
/**
 * file   : guard.php
 * path   : /var/www/html/rekam-medis/guard.php
 * fungsi : Pintu masuk tunggal. Menolak permintaan yang bukan untuk aplikasi.
 *
 * Kenapa file ini ada:
 *   Server produksi memakai nginx, yang TIDAK membaca .htaccess. Jadi aturan
 *   proteksi yang dulunya ada di .htaccess harus dipindahkan ke sini, agar
 *   proteksi tetap berlaku di sisi aplikasi.
 *
 * Cara kerja:
 *   1. Hanya ada SATU file PHP yang boleh dipanggil langsung, yaitu index.php.
 *      Semua .php lain (pages/, actions/, core/, config/, ...) ditutup 404.
 *   2. File/folder sensitif (.env, .sql, .log, .md, .git, logs/, backups/ ...)
 *      ditutup 404.
 *   3. Semua request lain diteruskan ke router internal index.php.
 *
 * Catatan: ini defense-in-depth, bukan pengganti proteksi di web server.
 *   Tetap WAJIB pasang location di nginx supaya proteksi berlaku lebih dulu,
 *   sebelum PHP dijalankan.
 */
declare(strict_types=1);

/* ============================================================
   1. Hanya guard.php & index.php yang boleh dipanggil langsung
   ============================================================ */
$__entry = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
if (!in_array($__entry, ['guard.php', 'index.php'], true)) {
    http_response_code(404);
    exit('Not Found');
}

/* ============================================================
   2. Blokir file & folder sensitif
   ============================================================ */
$__path = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
$__path = rawurldecode($__path);

// Normalisasi: strip trailing slash & collapse duplicate slash
$__path = preg_replace('#/+#', '/', $__path) ?? $__path;

$__baseDir = rtrim((string)(parse_url(
    defined('BASE_URL') ? BASE_URL : ($_SERVER['SCRIPT_NAME'] ?? ''),
    PHP_URL_PATH
) ?? ''), '/');

if ($__baseDir !== '' && str_starts_with($__path, $__baseDir)) {
    $__path = substr($__path, strlen($__baseDir));
}
$__path = ltrim($__path, '/');

/* --- 2a. File dengan ekstensi berisiko --- */
if (preg_match('/\.(sql|log|md|ini|sh|bash|yml|yaml|bak|old|swp|zip|tar|gz|sql\.gz)$/i', $__path)) {
    http_response_code(404);
    exit('Not Found');
}

/* --- 2b. File tersembunyi (.env, .git, dll) --- */
foreach (['.env', '.env.local', '.env.production', '.git', '.gitignore', '.svn', '.hg'] as $__hidden) {
    if ($__path === $__hidden || str_starts_with($__path, $__hidden . '/')) {
        http_response_code(404);
        exit('Not Found');
    }
}

/* --- 2c. Folder internal --- */
foreach (['backups', 'uploads', 'logs', 'database', '.git', '.svn'] as $__dir) {
    if ($__path === $__dir || str_starts_with($__path, $__dir . '/')) {
        http_response_code(404);
        exit('Not Found');
    }
}

/* --- 2d. File PHP internal (harus lewat router) --- */
if (preg_match('#^(pages|actions|core|config|bootstrap|layouts)/#', $__path)) {
    http_response_code(404);
    exit('Not Found');
}

/* ============================================================
   3. Forward ke router internal
   ============================================================ */
require __DIR__ . '/index.php';
