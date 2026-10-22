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
 * =====================================================================
 * GLOBAL HASH HELPER FUNCTIONS (Anti Undefined Function Error)
 * =====================================================================
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

if (!function_exists('hash_result_set')) {
    function hash_result_set(array $rows, array $columns = []): string
    {
        if (empty($rows)) {
            return hash('sha256', 'EMPTY');
        }
        if (empty($columns)) {
            $columns = array_keys($rows[0]);
        }
        sort($columns);

        $ctx = hash_init('sha256');
        foreach ($rows as $row) {
            $line = [];
            foreach ($columns as $col) {
                $line[] = $col . '=' . ($row[$col] ?? '');
            }
            hash_update($ctx, implode('|', $line) . "\n");
        }
        return hash_final($ctx);
    }
}

if (!function_exists('hash_backup_raw')) {
    function hash_backup_raw(?string $batchId = null): string
    {
        $sql = "SELECT id, raw_data FROM s_backup_raw";
        $params = [];

        if ($batchId !== null && $batchId !== '') {
            $sql .= " WHERE batch_id = :bid1 OR import_batch = :bid2";
            $params[':bid1'] = $batchId;
            $params[':bid2'] = $batchId;
        }
        $sql .= " ORDER BY id ASC";

        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        $ctx = hash_init('sha256');
        while ($row = $stmt->fetch()) {
            hash_update($ctx, $row['raw_data'] . "\n");
        }
        return hash_final($ctx);
    }
}

// ---------------------------------------------------------------------

if (PHP_SAPI === 'cli') {
    return; 
}

if (!defined('APP_INTERNAL_OK')) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Not Found');
}