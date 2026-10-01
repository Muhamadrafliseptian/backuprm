<?php
/**
 * file   : validasi_konsistensi.php
 * path   : C:\xampp\htdocs\backuprm\bootstrap\validasi_konsistensi.php
 * fungsi : Pastikan folder (backups, logs, uploads) & file log tersedia
 */
declare(strict_types=1);

require_once __DIR__ . '/internal_guard.php';

foreach ([PATH_BACKUPS, PATH_LOGS, PATH_UPLOADS] as $dir) {
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
}

foreach ([LOG_ACTIVITY, LOG_ERROR] as $f) {
    if (!file_exists($f)) @touch($f);
}