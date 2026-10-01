<?php
/**
 * file   : index.php
 * path   : /var/www/html/rekam-medis/index.php
 * fungsi : Entry point aplikasi (mode query string /index.php?r=dashboard)
 *
 * Mode ini dipakai kalau web server tidak menyediakan rewrite:
 *   - tanpa .htaccess (server nginx)
 *   - tanpa try_files
 *
 * Stub .php (dashboard.php, actions/login.php) memakai route.php.
 * Dua mode ini saling melengkapi; keduanya berbagi pipeline yang sama.
 */
declare(strict_types=1);

define('BASE_PATH', __DIR__);
define('BOOTSTRAP_PATH', BASE_PATH . '/bootstrap');

// Tandai request sah supaya file internal (pages/, core/, config/, actions/)
// tidak menolak saat dimuat oleh pipeline di bawah.
define('APP_INTERNAL_OK', true);

require_once BOOTSTRAP_PATH . '/config_core.php';
require_once BOOTSTRAP_PATH . '/timeout_sanitasi.php';
require_once BOOTSTRAP_PATH . '/redirect_root.php';
require_once BOOTSTRAP_PATH . '/routes.php';
require_once BOOTSTRAP_PATH . '/action_routes.php';
require_once BOOTSTRAP_PATH . '/validasi_konsistensi.php';
require_once BOOTSTRAP_PATH . '/post_action.php';
require_once BOOTSTRAP_PATH . '/security_route.php';