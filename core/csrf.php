<?php
/**
 * file   : csrf.php
 * path   : C:\xampp\htdocs\backuprm\core\csrf.php
 * fungsi : Generate & verifikasi CSRF token untuk semua request POST
 */
declare(strict_types=1);

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_verify(?string $token): bool
{
    return !empty($_SESSION['_csrf'])
        && is_string($token)
        && hash_equals($_SESSION['_csrf'], $token);
}

function csrf_check_or_die(): void
{
    if (!is_post()) return;
    $token = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!csrf_verify($token)) {
        if (is_ajax()) json_response(['ok' => false, 'msg' => 'CSRF token tidak valid'], 419);
        http_response_code(419);
        exit('CSRF token tidak valid.');
    }
}