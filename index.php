<?php
/**
 * file   : index.php
 * path   : /Applications/MAMP/htdocs/sikda/index.php
 * fungsi : Entry point aplikasi, memuat seluruh pipeline bootstrap secara berurutan
 */
// declare(strict_types=1);

// define('BASE_PATH', __DIR__);
// define('BOOTSTRAP_PATH', BASE_PATH . '/bootstrap');

// require_once BOOTSTRAP_PATH . '/config_core.php';
// require_once BOOTSTRAP_PATH . '/timeout_sanitasi.php';
// require_once BOOTSTRAP_PATH . '/redirect_root.php';
// require_once BOOTSTRAP_PATH . '/routes.php';
// require_once BOOTSTRAP_PATH . '/action_routes.php';
// require_once BOOTSTRAP_PATH . '/validasi_konsistensi.php';
// require_once BOOTSTRAP_PATH . '/post_action.php';
// require_once BOOTSTRAP_PATH . '/security_route.php';
// Tambahkan di paling bawah bootstrap/action_routes.php untuk debugging:
echo "<pre style='background:#111; color:#0f0; padding:15px; font-family:monospace;'>";
echo "=== DEBUGGING ROUTE MAMP ===\n";
echo "REQUEST_URI : " . ($_SERVER['REQUEST_URI'] ?? '') . "\n";
echo "SCRIPT_NAME : " . ($_SERVER['SCRIPT_NAME'] ?? '') . "\n";
echo "ROUTE_PATH  : " . ($_SERVER['ROUTE_PATH'] ?? 'BELUM SET') . "\n";
echo "ACTION_FILE : " . ($_SERVER['ACTION_FILE'] ?? 'KOSONG / TIDAK COCOK') . "\n";
echo "FILE EXISTS : " . (file_exists($_SERVER['ACTION_FILE'] ?? '') ? 'YA' : 'TIDAK') . "\n";
echo "</pre>";
exit;