<?php
// Doctor role verification test
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('BASE_PATH', __DIR__);
define('BOOTSTRAP_PATH', BASE_PATH . '/bootstrap');
require_once BOOTSTRAP_PATH . '/config_core.php';
require_once BASE_PATH . '/config/database.php';

$cookieFile = '/tmp/doc_cookies_test.txt';
if (file_exists($cookieFile)) {
    unlink($cookieFile);
}

function get_csrf_token($curl, $url) {
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $response = curl_exec($curl);
    $dom = new DOMDocument();
    @$dom->loadHTML($response);
    $inputs = $dom->getElementsByTagName('input');
    foreach ($inputs as $input) {
        if ($input->getAttribute('name') === '_token') {
            return $input->getAttribute('value');
        }
    }
    return '';
}

function do_login($curl, $cookieFile, $nrk, $password, $token) {
    curl_setopt_array($curl, [
        CURLOPT_URL => 'http://localhost:8888/rekam-medis/login.php',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => [
            'nrk' => $nrk,
            'password' => $password,
            '_token' => $token,
        ],
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HEADER => true,
    ]);
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    return [$httpCode, substr($response, 0, $headerSize), substr($response, $headerSize)];
}

$ch = curl_init();
$csrf = get_csrf_token($ch, 'http://localhost:8888/rekam-medis/login.php');
curl_close($ch);

echo "CSRF token: " . (strlen($csrf) ? 'OK' : 'MISSING') . "\n";

// Login admin first
$ch = curl_init();
list($code, $headers, $body) = do_login($ch, $cookieFile, '8813084', 'Adm#Sikda2026!rm', $csrf);
echo "Admin login HTTP: $code\n";
if (preg_match('/Location:\s*([^\r\n]+)/i', $headers, $m)) {
    echo "Admin redirect: " . trim($m[1]) . "\n";
}
curl_close($ch);

// Test admin dashboard
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'http://localhost:8888/rekam-medis/dashboard.php',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_NOBODY => true,
]);
curl_exec($ch);
$adminCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "Admin dashboard: $adminCode\n";

// Login doctor
$ch = curl_init();
list($code, $headers, $body) = do_login($ch, $cookieFile, '8813085', 'Dr#Sikda2026!rm', $csrf);
echo "Doctor login HTTP: $code\n";
if (preg_match('/Location:\s*([^\r\n]+)/i', $headers, $m)) {
    echo "Doctor redirect: " . trim($m[1]) . "\n";
} else {
    if (preg_match('/<div[^>]*class="[^"]*flash[^"]*"[^>]*>(.*?)<\/div>/s', $body, $m)) {
        echo "FLASH: " . trim(strip_tags($m[1])) . "\n";
    }
    if (preg_match('/<div[^>]*class="[^"]*error[^"]*"[^>]*>(.*?)<\/div>/s', $body, $m)) {
        echo "ERROR: " . trim(strip_tags($m[1])) . "\n";
    }
}
curl_close($ch);

// Test doctor dashboard
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'http://localhost:8888/rekam-medis/dashboard.php',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_NOBODY => true,
]);
curl_exec($ch);
$docCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "Doctor dashboard: $docCode\n";

// Test pages
$pages = ['pasien', 'kunjungan', 'laboratorium', 'obat', 'tenaga-kesehatan', 'log', 'backup-all', 'dashboard', 'akun', 'verifikasi', 'resume-medis'];
foreach ($pages as $page) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "http://localhost:8888/rekam-medis/$page.php",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_NOBODY => true,
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo "$page: $code\n";
}
