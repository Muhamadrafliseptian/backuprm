<?php
/**
 * file   : bootstrap/internal_guard.php
 */
declare(strict_types=1);

// Pastikan sesi aktif jika belum
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * DEKLARASI GLOBAL FIX: Mencegah error Call to undefined function hash_file_sha256()
 */
if (!function_exists('hash_file_sha256')) {
    function hash_file_sha256(string $filePath): ?string
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return null;
        }
        $hash = hash_file('sha256', $filePath);
        return $hash !== false ? $hash : null;
    }
}

if (!function_exists('hash_string_sha256')) {
    function hash_string_sha256(string $content): string
    {
        return hash('sha256', $content);
    }
}

// Lanjutkan guard standar aplikasi
if (PHP_SAPI === 'cli') {
    return; 
}

if (!defined('APP_INTERNAL_OK')) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Not Found');
}