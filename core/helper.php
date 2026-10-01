<?php
/**
 * file   : helper.php
 * path   : C:\xampp\htdocs\backuprm\core\helper.php
 * fungsi : Fungsi bantu global (base_url, asset, redirect, e, flash, log dual, json, dll)
 */
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/internal_guard.php';

function base_url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return URL_ASSETS . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    // Ada 3 mode routing, dipilih otomatis:
    //   1. stub     : /dashboard.php          (tanpa rewrite, tanpa .htaccess)
    //   2. pretty   : /dashboard              (.htaccess / try_files)
    //   3. query    : /index.php?r=dashboard  (fallback paling universal)
    $target = match (true) {
        stub_url_enabled()   => stub_url($path),
        pretty_url_enabled() => base_url($path),
        default              => BASE_URL . '/index.php?r=' . rawurlencode(ltrim($path, '/')),
    };

    header('Location: ' . $target);
    exit;
}

/**
 * Bangun URL ke stub .php: 'dashboard' -> '/rekam-medis/dashboard.php'
 */
function stub_url(string $path): string
{
    $path = ltrim($path, '/');

    // Sudah berupa stub (.php) atau query string -> pakai apa adanya
    if (str_ends_with($path, '.php') || str_contains($path, '?')) {
        return base_url($path);
    }

    // Path 'actions/xyz' dibuang prefiksnya: stub action bernamanya
    // 'xyz.php' di dalam folder actions/, bukan 'actions/xyz.php' di root.
    if (str_starts_with($path, 'actions/')) {
        $path = substr($path, strlen('actions/'));
        return BASE_URL . '/actions/' . $path . '.php';
    }

    return base_url($path . '.php');
}

/**
 * Apakah mode stub .php yang dipakai?
 *
 * Stub .php adalah cara paling universal: tidak butuh .htaccess,
 * tidak butuh rewrite nginx, tidak butuh query string.
 * Dipakai kalau request masuk lewat file .php di root atau actions/.
 *
 * Override lewat .env: ROUTING_MODE=stub|pretty|query
 */
function stub_url_enabled(): bool
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $mode = strtolower((string)(getenv('ROUTING_MODE') ?: ''));
    if ($mode === 'stub')   { $cached = true;  return true; }
    if ($mode === 'pretty') { $cached = false; return false; }
    if ($mode === 'query')  { $cached = false; return false; }

    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    $cached = str_ends_with($script, '.php')
        && !str_ends_with($script, '/index.php');

    return $cached;
}

/**
 * Apakah pretty URL (/dashboard) tersedia?
 *
 * Pretty URL butuh rewrite: .htaccess (Apache) atau try_files (nginx).
 * Kalau keduanya tidak ada, route harus lewat ?r=.
 *
 * Cara deteksi: kalau SCRIPT_NAME berakhiran index.php, kemungkinan besar
 * request datang lewat ?r= (bukan pretty URL), jadi pretty URL tidak dipakai.
 */
function pretty_url_enabled(): bool
{
    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');

    // .env: PRETTY_URL=off untuk memaksa ?r= di server tanpa rewrite
    $forced = getenv('PRETTY_URL');
    if ($forced === 'off' || $forced === 'false' || $forced === '0') {
        return false;
    }
    if ($forced === 'on' || $forced === 'true' || $forced === '1') {
        return true;
    }

    return !str_ends_with($script, '/index.php');
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function flash(string $key, ?string $msg = null)
{
    if ($msg !== null) {
        $_SESSION['_flash'][$key] = $msg;
        return null;
    }
    if (!empty($_SESSION['_flash'][$key])) {
        $v = $_SESSION['_flash'][$key];
        unset($_SESSION['_flash'][$key]);
        return $v;
    }
    return null;
}

/* ============================================================
   LOGGING — DUAL (file + database)
   ============================================================ */

/**
 * Catat aktivitas ke file log DAN tabel l_activity.
 *
 * @param string $aksi      Kode aksi singkat (login, logout, upload, dll)
 * @param string $deskripsi Detail aksi
 * @param string $level     info|warning|error
 */
function log_activity(string $aksi, string $deskripsi = '', string $level = 'info'): void
{
    $u       = $_SESSION['user'] ?? [];
    $userId  = $u['id']     ?? null;
    $userNrk = $u['nrk']    ?? 'guest';
    $userNm  = $u['nama']   ?? 'Guest';
    $role    = $u['role']   ?? '-';
    $ip      = $_SERVER['REMOTE_ADDR'] ?? '-';
    $ua      = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? '-'), 0, 255);
    $now     = date('Y-m-d H:i:s');

    /* ---------- 1. Tulis ke file ---------- */
    $line = sprintf(
        "[%s] [%s] [%s] [%s] %s | %s\n",
        $now,
        $ip,
        $userNrk,
        strtoupper($level),
        $aksi,
        $deskripsi
    );
    @file_put_contents(LOG_ACTIVITY, $line, FILE_APPEND | LOCK_EX);

    /* ---------- 2. Tulis ke database ---------- */
    try {
        $stmt = db()->prepare("
            INSERT INTO l_activity
                (user_id, user_nrk, user_nama, role, aksi, deskripsi, ip_address, user_agent, level, created_at)
            VALUES
                (:uid, :nrk, :nama, :role, :aksi, :desk, :ip, :ua, :level, :created)
        ");
        $stmt->execute([
            ':uid'     => $userId,
            ':nrk'     => $userNrk,
            ':nama'    => $userNm,
            ':role'    => $role,
            ':aksi'    => $aksi,
            ':desk'    => $deskripsi,
            ':ip'      => $ip,
            ':ua'      => $ua,
            ':level'   => $level,
            ':created' => $now,
        ]);
    } catch (Throwable $e) {
        // Kalau DB gagal, cukup catat ke file error — jangan crash
        @file_put_contents(
            LOG_ERROR,
            sprintf("[%s] l_activity DB FAIL: %s\n", $now, $e->getMessage()),
            FILE_APPEND | LOCK_EX
        );
    }
}

/**
 * Catat error ke file log DAN tabel l_activity.
 */
function log_error(string $msg): void
{
    $now = date('Y-m-d H:i:s');
    $ip  = $_SERVER['REMOTE_ADDR'] ?? '-';
    $u   = $_SESSION['user'] ?? [];

    /* ---------- 1. Tulis ke file error ---------- */
    @file_put_contents(
        LOG_ERROR,
        sprintf("[%s] %s\n", $now, $msg),
        FILE_APPEND | LOCK_EX
    );

    /* ---------- 2. Tulis ke database sebagai level=error ---------- */
    try {
        $stmt = db()->prepare("
            INSERT INTO l_activity
                (user_id, user_nrk, user_nama, role, aksi, deskripsi, ip_address, user_agent, level, created_at)
            VALUES
                (:uid, :nrk, :nama, :role, :aksi, :desk, :ip, :ua, :level, :created)
        ");
        $stmt->execute([
            ':uid'     => $u['id']   ?? null,
            ':nrk'     => $u['nrk']  ?? 'system',
            ':nama'    => $u['nama'] ?? 'System',
            ':role'    => $u['role'] ?? '-',
            ':aksi'    => 'error',
            ':desk'    => $msg,
            ':ip'      => $ip,
            ':ua'      => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? '-'), 0, 255),
            ':level'   => 'error',
            ':created' => $now,
        ]);
    } catch (Throwable $e) {
        // Kalau DB error juga, cukup diem (jangan infinite loop)
    }
}

/* ============================================================
   UTILITAS
   ============================================================ */

function format_tanggal(?string $date, string $format = 'd/m/Y'): string
{
    if (empty($date) || $date === '0000-00-00') return '-';
    $ts = strtotime($date);
    return $ts ? date($format, $ts) : '-';
}

function parse_tanggal_id(?string $date): ?string
{
    if (empty($date)) return null;
    $date = trim($date);
    if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $date, $m)) {
        return "{$m[3]}-{$m[2]}-{$m[1]}";
    }
    if (preg_match('#^\d{4}-\d{2}-\d{2}$#', $date)) return $date;
    $ts = strtotime($date);
    return $ts ? date('Y-m-d', $ts) : null;
}

function json_response(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . csrf_token() . '">';
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function is_ajax(): bool
{
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}