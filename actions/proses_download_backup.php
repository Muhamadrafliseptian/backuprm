<?php
/**
 * file   : proses_download_backup.php
 * path   : C:\xampp\htdocs\backuprm\actions\proses_download_backup.php
 * fungsi : Generate & kirim file backup database lengkap
 *          Format: .sql (restore-ready) atau .json (arsip)
 *          Mode  : full | structure | data
 */
declare(strict_types=1);

csrf_check_or_die();
set_time_limit(1800);        // 30 menit
ini_set('memory_limit', '1024M');

if (!auth_check()) {
    json_response(['ok' => false, 'msg' => 'Unauthorized'], 401);
}

/* ============================================================
   Input
   ============================================================ */
$format = ($_POST['format'] ?? 'sql') === 'json' ? 'json' : 'sql';
$mode   = $_POST['mode'] ?? 'full';
if (!in_array($mode, ['full', 'structure', 'data'], true)) {
    $mode = 'full';
}

$pdo     = db();
$dbName  = env('DB_NAME', 'db_backuprm');
$ts      = date('Y-m-d_His');
$baseName = $dbName . '_backup_' . $ts;

/* ============================================================
   Ambil daftar tabel
   ============================================================ */
$tables = [];
try {
    $rows = $pdo->query("
        SELECT TABLE_NAME
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_TYPE = 'BASE TABLE'
        ORDER BY TABLE_NAME ASC
    ")->fetchAll();
    foreach ($rows as $r) $tables[] = $r['TABLE_NAME'];
} catch (Throwable $e) {
    log_error('download_backup: gagal list tabel — ' . $e->getMessage());
    json_response(['ok' => false, 'msg' => 'Gagal membaca daftar tabel.'], 500);
}

if (empty($tables)) {
    json_response(['ok' => false, 'msg' => 'Tidak ada tabel di database.'], 400);
}

/* ============================================================
   Log aksi
   ============================================================ */
$u = auth_user();
log_activity('download_backup', sprintf(
    'Download backup DB: format=%s mode=%s tabel=%d oleh %s',
    $format, $mode, count($tables), $u['nrk'] ?? '-'
));

/* ============================================================
   Set header download
   ============================================================ */
$mime = $format === 'json' ? 'application/json' : 'application/sql';
$ext  = $format;

header('Content-Type: ' . $mime . '; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $baseName . '.' . $ext . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

/* Matikan output buffering supaya stream langsung */
while (ob_get_level() > 0) {
    ob_end_flush();
}
ob_implicit_flush(true);

/* ============================================================
   GENERATE FILE
   ============================================================ */
try {
    if ($format === 'sql') {
        generateSql($pdo, $tables, $dbName, $mode, $baseName);
    } else {
        generateJson($pdo, $tables, $dbName, $mode, $baseName);
    }
} catch (Throwable $e) {
    log_error('download_backup: ' . $e->getMessage());
    // Header sudah terkirim — cuma bisa echo error di body
    echo "\n\n-- ERROR: " . $e->getMessage() . "\n";
}
exit;

/* ============================================================
   FUNGSI: SQL DUMP
   ============================================================ */
function generateSql(PDO $pdo, array $tables, string $dbName, string $mode, string $baseName): void
{
    echo "-- ============================================================\n";
    echo "-- Backup Database: {$dbName}\n";
    echo "-- File          : {$baseName}.sql\n";
    echo "-- Mode          : {$mode}\n";
    echo "-- Dibuat        : " . date('Y-m-d H:i:s') . "\n";
    echo "-- Oleh          : " . (auth_user()['nama'] ?? '-') . " (" . (auth_user()['nrk'] ?? '-') . ")\n";
    echo "-- Aplikasi      : Backup RM\n";
    echo "-- ============================================================\n\n";

    echo "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
    echo "SET time_zone = \"+07:00\";\n";
    echo "SET NAMES utf8mb4;\n";
    echo "SET FOREIGN_KEY_CHECKS = 0;\n";
    echo "SET UNIQUE_CHECKS = 0;\n\n";

    if ($mode === 'full') {
        echo "DROP DATABASE IF EXISTS `{$dbName}`;\n";
        echo "CREATE DATABASE `{$dbName}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n";
        echo "USE `{$dbName}`;\n\n";
    }

    $totalTables = count($tables);
    $curTable    = 0;

    foreach ($tables as $tbl) {
        $curTable++;

        echo "-- ============================================================\n";
        echo "-- Table: `{$tbl}` ({$curTable}/{$totalTables})\n";
        echo "-- ============================================================\n\n";

        /* ---------- Struktur ---------- */
        if ($mode === 'full' || $mode === 'structure') {
            try {
                $create = $pdo->query("SHOW CREATE TABLE `{$tbl}`")->fetch(PDO::FETCH_ASSOC);
                if ($create && !empty($create['Create Table'])) {
                    echo "DROP TABLE IF EXISTS `{$tbl}`;\n";
                    echo $create['Create Table'] . ";\n\n";
                }
            } catch (Throwable $e) {
                echo "-- Gagal baca struktur {$tbl}: " . $e->getMessage() . "\n\n";
            }
        } else {
            // data only — pastikan tabel ada
            echo "-- (struktur tidak disertakan, mode data-only)\n\n";
        }

        /* ---------- Data ---------- */
        if ($mode === 'full' || $mode === 'data') {
            try {
                $count = (int)$pdo->query("SELECT COUNT(*) FROM `{$tbl}`")->fetchColumn();

                if ($count === 0) {
                    echo "-- (kosong)\n\n";
                } else {
                    echo "LOCK TABLES `{$tbl}` WRITE;\n";

                    /* ---------- Ambil struktur kolom ---------- */
                    $cols = [];
                    $colTypes = [];
                    $colStmt = $pdo->query("SHOW COLUMNS FROM `{$tbl}`");
                    foreach ($colStmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
                        $cols[] = $c['Field'];
                        $colTypes[$c['Field']] = [
                            'type' => strtolower($c['Type'] ?? ''),
                            'null' => strtoupper($c['Null'] ?? 'YES') === 'YES',
                        ];
                    }

                    $colList = '`' . implode('`, `', $cols) . '`';

                    /* ---------- Stream baris dalam batch ---------- */
                    $batchSize = 500;
                    $offset    = 0;

                    while ($offset < $count) {
                        $stmt = $pdo->prepare("SELECT * FROM `{$tbl}` LIMIT {$batchSize} OFFSET {$offset}");
                        $stmt->execute();
                        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        if (empty($rows)) break;

                        echo "INSERT INTO `{$tbl}` ({$colList}) VALUES\n";

                        $lineParts = [];
                        foreach ($rows as $row) {
                            $vals = [];
                            foreach ($cols as $col) {
                                $v = $row[$col] ?? null;
                                if ($v === null) {
                                    $vals[] = 'NULL';
                                } elseif (is_int($v) || is_float($v)) {
                                    $vals[] = (string)$v;
                                } elseif (is_bool($v)) {
                                    $vals[] = $v ? '1' : '0';
                                } else {
                                    $vals[] = $pdo->quote((string)$v);
                                }
                            }
                            $lineParts[] = '(' . implode(', ', $vals) . ')';
                        }

                        echo implode(",\n", $lineParts) . ";\n";

                        $offset += $batchSize;
                        // flush output
                        if (function_exists('gc_collect_cycles')) gc_collect_cycles();
                    }

                    echo "UNLOCK TABLES;\n\n";
                }
            } catch (Throwable $e) {
                echo "-- Gagal baca data {$tbl}: " . $e->getMessage() . "\n\n";
            }
        }
    }

    echo "SET FOREIGN_KEY_CHECKS = 1;\n";
    echo "SET UNIQUE_CHECKS = 1;\n\n";
    echo "-- ============================================================\n";
    echo "-- Backup selesai: " . date('Y-m-d H:i:s') . "\n";
    echo "-- ============================================================\n";
}

/* ============================================================
   FUNGSI: JSON DUMP
   ============================================================ */
function generateJson(PDO $pdo, array $tables, string $dbName, string $mode, string $baseName): void
{
    $meta = [
        'database'    => $dbName,
        'file'        => $baseName . '.json',
        'mode'        => $mode,
        'created_at'  => date('Y-m-d H:i:s'),
        'created_by'  => (auth_user()['nama'] ?? '-') . ' (' . (auth_user()['nrk'] ?? '-') . ')',
        'app'         => 'Backup RM',
        'total_tables'=> count($tables),
    ];

    echo "{\n";
    echo '  "meta": ' . json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . ",\n";
    echo '  "tables": {' . "\n";

    $isFirstTable = true;

    foreach ($tables as $tbl) {
        if (!$isFirstTable) {
            echo ",\n";
        }
        $isFirstTable = false;

        echo '    ' . json_encode($tbl) . ': {' . "\n";

        /* ---------- Struktur ---------- */
        if ($mode === 'full' || $mode === 'structure') {
            try {
                $create = $pdo->query("SHOW CREATE TABLE `{$tbl}`")->fetch(PDO::FETCH_ASSOC);
                $createSql = $create['Create Table'] ?? '';
            } catch (Throwable $e) {
                $createSql = '';
            }
            echo '      "structure": ' . json_encode($createSql, JSON_UNESCAPED_UNICODE) . ",\n";
        }

        /* ---------- Data ---------- */
        if ($mode === 'full' || $mode === 'data') {
            try {
                $count = (int)$pdo->query("SELECT COUNT(*) FROM `{$tbl}`")->fetchColumn();
                $rows  = $pdo->query("SELECT * FROM `{$tbl}`")->fetchAll(PDO::FETCH_ASSOC);

                echo '      "row_count": ' . $count . ",\n";
                echo '      "columns": ' . json_encode(array_keys($rows[0] ?? [])) . ",\n";
                echo '      "data": ' . json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } catch (Throwable $e) {
                echo '      "error": ' . json_encode($e->getMessage());
            }
        }

        echo "\n    }";
    }

    echo "\n  }\n}\n";
}