<?php
/**
 * file   : action_map.php
 * path   : bootstrap/action_map.php
 * fungsi : Daftar route action (nama stub .php di actions/)
 *
 * Dipakai oleh:
 *   - route.php untuk memvalidasi stub yang dipanggil
 *   - tools/generate-stubs.php untuk membuat stub
 *   - bootstrap/action_routes.php untuk memetakan ke file di actions/
 *
 * Format: 'nama_stub' => 'file_proses_yang_ada_di_actions'
 */
declare(strict_types=1);

require_once __DIR__ . '/internal_guard.php';

return [
    'login'           => 'proses_login.php',
    'logout'          => 'proses_logout.php',
    'ping'            => 'proses_ping.php',
    'clear-log'       => 'proses_clear_log.php',
    'upload-sql'      => 'proses_upload_sql.php',
    'preview-data'    => 'proses_preview_data.php',
    'simpan-hasil'    => 'proses_simpan_hasil.php',
    'clear-backup'    => 'proses_clear_backup.php',
    'reset-data'      => 'proses_reset_data.php',
    'reset-all'       => 'proses_reset_all.php',
    'mark-verified'   => 'proses_mark_verified.php',
    'download-backup' => 'proses_download_backup.php',
];