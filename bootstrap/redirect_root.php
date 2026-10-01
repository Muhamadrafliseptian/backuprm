<?php
/**
 * file   : redirect_root.php
 * path   : C:\xampp\htdocs\backuprm\bootstrap\redirect_root.php
 * fungsi : Ambil path relatif dari URL, tentukan ROUTE_PATH (default dashboard/login)
 */
declare(strict_types=1);

require_once __DIR__ . '/internal_guard.php';

$uri     = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$baseDir = parse_url(BASE_URL, PHP_URL_PATH) ?? '';
if ($baseDir && str_starts_with($uri, $baseDir)) {
    $uri = substr($uri, strlen($baseDir));
}
$uri = trim($uri, '/');

$_SERVER['ROUTE_PATH'] = $uri === '' ? (auth_check() ? 'dashboard' : 'login') : $uri;