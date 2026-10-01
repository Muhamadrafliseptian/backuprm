<?php
/**
 * file   : bootstrap.php
 * path   : C:\xampp\htdocs\backuprm\core\bootstrap.php
 * fungsi : Exception handler global, catat error ke log
 */
declare(strict_types=1);

set_exception_handler(function (Throwable $e) {
    log_error('Uncaught: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (env('APP_DEBUG') === 'true') {
        echo '<pre style="background:#111;color:#f66;padding:20px;border-radius:8px">';
        echo htmlspecialchars((string)$e);
        echo '</pre>';
    } else {
        http_response_code(500);
        echo 'Terjadi kesalahan internal.';
    }
    exit;
});