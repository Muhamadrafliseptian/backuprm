<?php
/**
 * file   : routes.php
 * path   : C:\xampp\htdocs\backuprm\bootstrap\routes.php
 * fungsi : Peta route GET ke file di folder pages/, 404 jika tidak ada (skip actions/*)
 */
declare(strict_types=1);

require_once __DIR__ . '/internal_guard.php';
if (!empty($_GET['r'])) {
    $_SERVER['ROUTE_PATH'] = trim($_GET['r'], '/');
}

$__r = $_SERVER['ROUTE_PATH'] ?? '';
if (str_starts_with($__r, 'actions/')) {
    return;
}

// Peta route -> file di pages/.
// Sumber tunggal: bootstrap/route_map.php (dipakai juga oleh route.php
// dan tools/generate-stubs.php, sehingga stub dan router tidak bisa beda).
$routes = require BASE_PATH . '/bootstrap/route_map.php';

$route = $_SERVER['ROUTE_PATH'];
$file  = $routes[$route] ?? null;

if ($file === null || !file_exists(BASE_PATH . '/pages/' . $file)) {
    http_response_code(404);
    require BASE_PATH . '/pages/404.php';
    exit;
}

$_SERVER['PAGE_FILE'] = BASE_PATH . '/pages/' . $file;