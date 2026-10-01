<?php
/**
 * file   : action_routes.php
 * path   : bootstrap/action_routes.php
 * fungsi : Peta route POST /actions/{name} ke file di folder actions/
 */
declare(strict_types=1);

// 1. Ambil ROUTE_PATH dari query string ?r= atau dari server
if (!empty($_GET['r'])) {
    $_SERVER['ROUTE_PATH'] = trim($_GET['r'], '/');
}

$routes_action = [
    'login'            => 'proses_login.php',
    'logout'           => 'proses_logout.php',
    'ping'             => 'proses_ping.php',
    'clear-log'        => 'proses_clear_log.php',
    'upload-sql'       => 'proses_upload_sql.php',
    'preview-data'     => 'proses_preview_data.php',
    'simpan-hasil'     => 'proses_simpan_hasil.php',
    'clear-backup'     => 'proses_clear_backup.php',
    'reset-data'       => 'proses_reset_data.php',
    'reset-all'        => 'proses_reset_all.php',
    'mark-verified'    => 'proses_mark_verified.php',
    'download-backup'  => 'proses_download_backup.php',
];

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