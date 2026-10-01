<?php
/**
 * file   : proses_login.php
 * path   : C:\xampp\htdocs\backuprm\actions\proses_login.php
 * fungsi : Proses login + rate limit per IP
 */
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/internal_guard.php';

csrf_check_or_die();

/* ============================================================
   RATE LIMIT PER IP (10 percobaan per 5 menit)
   ============================================================ */
$ip       = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ipKey    = 'rl_login_' . md5($ip);
$rlWindow = 300; // 5 menit
$rlMax    = 10;

$rl = $_SESSION[$ipKey] ?? ['count' => 0, 'reset' => time() + $rlWindow];

if ($rl['reset'] < time()) {
    $rl = ['count' => 0, 'reset' => time() + $rlWindow];
}

if ($rl['count'] >= $rlMax) {
    $wait = $rl['reset'] - time();
    log_error("LOGIN RATE LIMIT ip={$ip} wait={$wait}s");
    flash('error', 'Terlalu banyak percobaan. Coba lagi dalam ' . ceil($wait / 60) . ' menit.');
    redirect('login');
}

$nrk      = trim((string)($_POST['nrk'] ?? ''));
$password = (string)($_POST['password'] ?? '');

$v = new Validator(['nrk' => $nrk, 'password' => $password]);
$v->required('nrk', 'NRK')->required('password', 'Password');

if ($v->fails()) {
    flash('error', $v->firstError());
    redirect('login');
}

$result = auth_attempt($nrk, $password);

if (!$result['ok']) {
    /* Increment counter rate limit */
    $rl['count']++;
    $_SESSION[$ipKey] = $rl;

    $msg = match ($result['reason']) {
        'not_found'      => 'NRK tidak terdaftar.',
        'inactive'       => 'Akun tidak aktif. Hubungi admin.',
        'locked'         => 'Akun terkunci. Coba lagi dalam ' . ceil($result['remaining'] / 60) . ' menit.',
        'wrong_password' => 'Password salah. Sisa percobaan: ' . ($result['sisa'] ?? 0),
        default          => 'Login gagal.',
    };
    flash('error', $msg);
    redirect('login');
}

/* Reset rate limit saat sukses */
unset($_SESSION[$ipKey]);

auth_login($result['user']);
log_activity('login', 'Berhasil login: ' . $nrk);

flash('success', 'Selamat datang, ' . ($result['user']['peg_nama'] ?? $nrk) . '!');
redirect('dashboard');