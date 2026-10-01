<?php
/**
 * file   : app.php
 * path   : C:\xampp\htdocs\backuprm\config\app.php
 * fungsi : Konfigurasi aplikasi & palet warna tema (mint + putih)
 */
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/internal_guard.php';

return [
    'name'     => APP_NAME,
    'url'      => BASE_URL,
    'version'  => APP_VERSION,
    'timezone' => env('APP_TIMEZONE', 'Asia/Jakarta'),
    'debug'    => env('APP_DEBUG', 'false') === 'true',
    'theme'    => [
        'primary'   => '#2EC4B6',
        'secondary' => '#A8E6CF',
        'accent'    => '#1B9AAA',
        'bg'        => '#F8F9FA',
        'text'      => '#2D3436',
        'danger'    => '#E63946',
    ],
];