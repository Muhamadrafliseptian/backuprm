<?php
/**
 * file   : config_core.php
 * path   : bootstrap/config_core.php
 * fungsi : Load .env, set timezone, error reporting, deteksi BASE_URL, load config & core
 */
declare(strict_types=1);

require_once __DIR__ . '/internal_guard.php';

// ---------- Load .env ----------
// Urutan pencarian:
//   1. getenv('ENV_FILE')  (path absolut, diisi dari luar .env - misal
//                          fastcgi_param ENV_FILE di nginx, atau pool
//                          PHP-FPM. TIDAK bisa ditulis di dalam .env
//                          karena file .env belum dimuat pada titik ini.)
//   2. BASE_PATH/.env       (lokasi lama, tetap didukung)
//   3. BASE_PATH/../.env    (satu level di atas webroot - paling aman)
//
// Kenapa mendukung lokasi luar webroot:
//   Server produksi memakai nginx yang tidak bisa .htaccess. Kalau .env
//   berada di dalam webroot, file password itu bisa saja terunduh lewat
//   HTTP kalau rule nginx belum aktif. Simpan di luar webroot + permission
//   640 root:www-data = tidak bisa dibaca visitor, tapi tetap bisa oleh PHP.
$__envFromEnv = getenv('ENV_FILE');
$__envCandidates = array_filter([
    is_string($__envFromEnv) && $__envFromEnv !== '' ? $__envFromEnv : null,
    BASE_PATH . '/.env',
    BASE_PATH . '/../.env',
]);

foreach ($__envCandidates as $envFile) {
    if (!file_exists($envFile) || !is_readable($envFile)) continue;

    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v, " \t\n
\0\x0B\"'");
        $_ENV[$k] = $v;
        putenv("$k=$v");
    }
    break; // pakai file pertama yang ditemukan
}
unset($__envCandidates, $envFile);

function env(string $key, $default = null)
{
    $v = $_ENV[$key] ?? getenv($key);
    return ($v === false || $v === null || $v === '') ? $default : $v;
}

date_default_timezone_set(env('APP_TIMEZONE', 'Asia/Jakarta'));

if (env('APP_DEBUG', 'false') === 'true') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// ---------- Deteksi BASE_URL otomatis & dinamis ----------
if (!defined('BASE_URL')) {
    // Prioritas 1: Gunakan APP_URL dari .env jika didefinisikan.
    // Useful di belakang reverse proxy/WAF yang tidak meneruskan host dengan baik.
    $appUrl = env('APP_URL');

    if ($appUrl) {
        $baseUrl = rtrim($appUrl, '/');
    } else {
        // Prioritas 2: Deteksi otomatis dari Server Request
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

        // Dukungan Reverse Proxy / WAF Kominfotik (X-Forwarded-Proto & X-Forwarded-Host)
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            $scheme = explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'])[0];
        }
        $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost:8888');

        $script = $_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? '/index.php');
        $dir    = rtrim(str_replace('\\', '/', dirname($script)), '/');

        // Kalau request masuk lewat folder actions/ (stub .php),
        // BASE_URL harus tetap menunjuk ke root aplikasi, bukan ke /actions.
        // Tanpa ini BASE_URL jadi ".../rekam-medis/actions" dan semua link
        // ke aset serta redirect akan salah.
        if ($dir !== '' && basename($dir) === 'actions') {
            $dir = rtrim(str_replace('\\', '/', dirname($dir)), '/');
        }

        // Tanpa trailing slash: base_url() sudah menambah '/' sendiri,
        // kalau tidak akan jadi URL ganda seperti /rekam-medis//dashboard
        $baseUrl = rtrim($scheme . '://' . $host . $dir, '/');
    }

    define('BASE_URL', $baseUrl);
}

// ---------- Load Config & Core ----------
require_once BASE_PATH . '/config/constants.php';
require_once BASE_PATH . '/config/app.php';
require_once BASE_PATH . '/config/database.php';

require_once BASE_PATH . '/core/helper.php';
require_once BASE_PATH . '/core/hash_helper.php';
require_once BASE_PATH . '/core/session.php';
require_once BASE_PATH . '/core/csrf.php';
require_once BASE_PATH . '/core/validator.php';
require_once BASE_PATH . '/core/auth.php';
require_once BASE_PATH . '/core/bootstrap.php';