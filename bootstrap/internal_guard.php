<?php
/**
 * file   : internal_guard.php
 * path   : bootstrap/internal_guard.php
 * fungsi : Cegah file internal (pages/, core/, config/, actions/proses_*)
 *          diakses langsung lewat HTTP tanpa melalui router.
 *
 * KENAPA DIperlukan:
 *   Server produksi memakai nginx yang tidak bisa .htaccess. Tanpa proteksi
 *   di web server, file seperti /pages/login.php atau /.env akan dilayani
 *   langsung dari disk. File ini dipakai sebagai pagar lapis aplikasi.
 *
 * CARA KERJA:
 *   Setiap file internal diawali:
 *       require_once __DIR__ . '/../bootstrap/internal_guard.php';
 *   Guard ini menolak request yang tidak datang dari pipeline aplikasi.
 *   Yang dianggap sah hanya jika index.php sudah mendefinisikan APP_INTERNAL_OK
 *   sebelum memuat file-file internal.
 *
 * PERINGATAN: ini defense-in-depth. Tetap WAJIB pasang rule di web server
 *   (nginx location / Apache .htaccess) sebagai pagar pertama, sebelum PHP
 *   sempat berjalan.
 */
declare(strict_types=1);

if (PHP_SAPI === 'cli') {
    return; // boleh saat dijalankan dari CLI (tools/, generator)
}

if (!defined('APP_INTERNAL_OK')) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Not Found');
}