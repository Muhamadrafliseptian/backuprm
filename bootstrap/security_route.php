<?php
/**
 * file   : security_route.php
 * path   : C:\xampp\htdocs\backuprm\bootstrap\security_route.php
 * fungsi : Cek login (kecuali route publik) lalu render halaman dari pages/
 */
declare(strict_types=1);

require_once __DIR__ . '/internal_guard.php';

// Kalau ini action, sudah dieksekusi di post_action.php → stop
if (!empty($_SERVER['ACTION_FILE'])) {
    return;
}

$publicRoutes = ['login'];
$route = $_SERVER['ROUTE_PATH'];

if (!in_array($route, $publicRoutes, true) && !auth_check()) {
    redirect('login');
}

if (!empty($_SERVER['PAGE_FILE']) && file_exists($_SERVER['PAGE_FILE'])) {
    require_once $_SERVER['PAGE_FILE'];
}