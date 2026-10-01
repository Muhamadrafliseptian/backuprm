<?php
/**
 * file   : action_routes.php
 * path   : bootstrap/action_routes.php
 * fungsi : Peta route POST /actions/{name} ke file di folder actions/
 */
declare(strict_types=1);

require_once __DIR__ . '/internal_guard.php';

// 1. Ambil ROUTE_PATH dari query string ?r= atau dari server
if (!empty($_GET['r'])) {
    $_SERVER['ROUTE_PATH'] = trim($_GET['r'], '/');
}

// Peta action -> file di actions/.
// Sumber tunggal: bootstrap/action_map.php (dipakai juga oleh route.php
// dan tools/generate-stubs.php, sehingga stub dan router tidak bisa beda).
$routes_action = require BASE_PATH . '/bootstrap/action_map.php';

$_SERVER['ACTION_FILE'] = null;

// 2. Ambil URI dengan fallback string kosong agar tidak undefined variable
$uri = trim($_SERVER['ROUTE_PATH'] ?? '', '/');

if (str_starts_with($uri, 'actions/')) {
    $action = trim(substr($uri, strlen('actions/')), '/');
    
    if (isset($routes_action[$action])) {
        $_SERVER['ACTION_FILE'] = BASE_PATH . '/actions/' . $routes_action[$action];
    } else {
        if (function_exists('is_ajax') && is_ajax()) {
            json_response(['ok' => false, 'msg' => 'Action not found'], 404);
        }
        http_response_code(404);
        echo "Action tidak ditemukan dalam daftar route.";
        exit;
    }
}