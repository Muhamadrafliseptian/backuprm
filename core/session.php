<?php
/**
 * file   : session.php
 * path   : C:\xampp\htdocs\backuprm\core\session.php
 * fungsi : Start session aman + timeout otomatis (server-side) yang sinkron
 *          dengan client-side JS.
 *
 * Prinsip:
 *   - Server HANYA logout kalau idle > SESSION_LIFETIME.
 *   - Client-side JS cek server dulu sebelum memaksa logout.
 *   - Ping dari client akan reset timer server.
 */
declare(strict_types=1);

function session_start_safe(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;

    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    if (empty($_SESSION['_init'])) {
        session_regenerate_id(true);
        $_SESSION['_init'] = time();
        $_SESSION['_last_activity'] = time();
    }

    /* ---------- Cek timeout server-side ---------- */
    $wasLogged = !empty($_SESSION['user']);

    if (!empty($_SESSION['_last_activity'])) {
        $idle = time() - (int)$_SESSION['_last_activity'];

        if ($idle > SESSION_LIFETIME) {
            /* Sesi kadaluarsa → destroy */
            session_unset();
            session_destroy();
            session_start();

            if ($wasLogged) {
                $_SESSION['_auto_logout'] = 1;
            }
            $_SESSION['_init']          = time();
            $_SESSION['_last_activity'] = time();
        }
    }

    /* ---------- Update last activity SETIAP request ---------- */
    $_SESSION['_last_activity'] = time();
}

session_start_safe();