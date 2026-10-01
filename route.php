<?php
/**
 * file   : route.php
 * path   : /var/www/html/rekam-medis/route.php
 * fungsi : Front controller untuk semua stub .php
 *
 * CARA KERJA (tanpa .htaccess, tanpa rewrite, tanpa try_files):
 *   Setiap route punya stub .php sendiri di root atau di actions/:
 *       /rekam-medis/dashboard.php      -> require_once __DIR__.'/route.php';
 *       /rekam-medis/actions/login.php  -> require_once __DIR__.'/../route.php';
 *   Stub memanggil file ini, yang membaca nama stub sebagai nama route lalu
 *   meneruskannya ke pipeline bootstrap.
 *
 * KEUNTUNGAN:
 *   - Tidak butuh .htaccess, tidak butuh rewrite nginx
 *   - Tidak butuh query string ?r=
 *   - Hanya file .php yang bisa dipanggil, jadi tidak ada file statis bocor
 */
declare(strict_types=1);

// Tandai request ini sah SEBELUM pipeline dimuat. File internal
// (pages/, core/, config/, bootstrap/, layouts/, actions/proses_*)
// menolak dimuat kalau constant ini belum diset.
if (!defined('APP_INTERNAL_OK')) {
    define('APP_INTERNAL_OK', true);
}

if (!defined('BASE_PATH')) {
    define('BASE_PATH', __DIR__);
}
if (!defined('BOOTSTRAP_PATH')) {
    define('BOOTSTRAP_PATH', BASE_PATH . '/bootstrap');
}

/* ============================================================
   1. Validasi pemanggil: hanya stub yang boleh memanggil route.php
   ============================================================ */
$__stub = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));

// File inti tidak boleh dipanggil langsung sebagai route
$__forbidden = ['route', 'index', 'guard', 'generate-stubs'];
if (in_array(pathinfo($__stub, PATHINFO_FILENAME), $__forbidden, true)) {
    http_response_code(404);
    exit('Not Found');
}

/* ============================================================
   2. Tentukan route dari path stub
   ============================================================
   CATATAN PENTING: stub di folder actions/ punya nama file yang sama
   dengan stub halaman (mis. actions/login.php vs login.php). Jadi tidak
   boleh pakai basename() saja, harus lihat direktorinya juga.

   Contoh:
     /rekam-medis/login.php          -> stub=login.php, dir=/
     /rekam-medis/actions/login.php  -> stub=login.php, dir=actions/
*/
$__script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
$__dir    = trim(str_replace('\\', '/', dirname($__script)), '/');
$__name   = basename($__script, '.php');

$__inActions = ($__dir === 'actions' || str_ends_with($__dir, '/actions'));

$__route = $__inActions
    ? 'actions/' . $__name
    : $__name;

/* ============================================================
   3. Pastikan stub itu memang route yang sah
   ============================================================ */
$__routesPage   = require BASE_PATH . '/bootstrap/route_map.php';
$__routesAction = require BASE_PATH . '/bootstrap/action_map.php';

// $__route sudah berbentuk 'actions/xyz' kalau berasal dari folder actions/,
// sedangkan kunci map action tidak memakai prefiks 'actions/'.
// Jadi bandingkan tanpa prefiks.
$__bareRoute = $__inActions ? $__name : $__route;

$__isPage   = in_array($__bareRoute, array_keys($__routesPage), true);
$__isAction = in_array($__bareRoute, array_keys($__routesAction), true);

if (!$__isPage && !$__isAction) {
    http_response_code(404);
    exit('Not Found');
}

/* ============================================================
   4. Teruskan ke pipeline bootstrap
   ============================================================ */

$_GET['r'] = $__route;

require_once BOOTSTRAP_PATH . '/config_core.php';
require_once BOOTSTRAP_PATH . '/timeout_sanitasi.php';
require_once BOOTSTRAP_PATH . '/redirect_root.php';
require_once BOOTSTRAP_PATH . '/routes.php';
require_once BOOTSTRAP_PATH . '/action_routes.php';
require_once BOOTSTRAP_PATH . '/validasi_konsistensi.php';
require_once BOOTSTRAP_PATH . '/post_action.php';
require_once BOOTSTRAP_PATH . '/security_route.php';