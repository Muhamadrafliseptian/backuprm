<?php
/**
 * file   : index.php
 * path   : /Applications/MAMP/htdocs/sikda/index.php
 * fungsi : Entry point aplikasi, memuat seluruh pipeline bootstrap secara berurutan
 */
declare(strict_types=1);

define('BASE_PATH', __DIR__);
define('BOOTSTRAP_PATH', BASE_PATH . '/bootstrap');

require_once BOOTSTRAP_PATH . '/config_core.php';
require_once BOOTSTRAP_PATH . '/timeout_sanitasi.php';
require_once BOOTSTRAP_PATH . '/redirect_root.php';
require_once BOOTSTRAP_PATH . '/routes.php';
require_once BOOTSTRAP_PATH . '/action_routes.php';
require_once BOOTSTRAP_PATH . '/validasi_konsistensi.php';
require_once BOOTSTRAP_PATH . '/post_action.php';
require_once BOOTSTRAP_PATH . '/security_route.php';