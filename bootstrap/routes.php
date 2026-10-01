<?php
/**
 * file   : routes.php
 * path   : C:\xampp\htdocs\backuprm\bootstrap\routes.php
 * fungsi : Peta route GET ke file di folder pages/, 404 jika tidak ada (skip actions/*)
 */
declare(strict_types=1);
if (!empty($_GET['r'])) {
    $_SERVER['ROUTE_PATH'] = trim($_GET['r'], '/');
}

$__r = $_SERVER['ROUTE_PATH'] ?? '';
if (str_starts_with($__r, 'actions/')) {
    return;
}

$routes = [
    'login'            => 'login.php',
    'logout'           => 'logout.php',
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
    'backup-all'       => 'backup_all.php',                 // <-- TAMBAH
];

$route = $_SERVER['ROUTE_PATH'];
$file  = $routes[$route] ?? null;

if ($file === null || !file_exists(BASE_PATH . '/pages/' . $file)) {
    http_response_code(404);
    require BASE_PATH . '/pages/404.php';
    exit;
}

$_SERVER['PAGE_FILE'] = BASE_PATH . '/pages/' . $file;