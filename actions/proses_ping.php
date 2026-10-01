<?php
/**
 * file   : proses_ping.php
 * path   : C:\xampp\htdocs\backuprm\actions\proses_ping.php
 * fungsi : Ping dari client untuk reset timer session + refresh CSRF token
 *          + cek status expired
 */
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/internal_guard.php';

/* ---------- Cek session expired ---------- */
if (!auth_check()) {
    // Kalau session expired, beri tahu client supaya redirect
    json_response([
        'ok'      => false,
        'expired' => true,
        'msg'     => 'Session expired',
    ], 401);
}

/* ---------- Reset timer server ---------- */
$_SESSION['_last_activity'] = time();

/* ---------- Hitung sisa waktu ---------- */
$expiresAt = time() + SESSION_LIFETIME;

json_response([
    'ok'          => true,
    'ts'          => time(),
    'ttl'         => SESSION_LIFETIME,
    'expires_at'  => $expiresAt,
    'remaining'   => SESSION_LIFETIME, // sisa dari sisi server baru di-reset
    'csrf_token'  => csrf_token(),
]);