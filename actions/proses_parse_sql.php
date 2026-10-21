<?php
/**
 */
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/internal_guard.php';
require_once BASE_PATH . '/core/parser_sql.php'; // Menggunakan helper parser 244 kolom milik Anda
require_once BASE_PATH . '/core/hash_helper.php';

csrf_check_or_die();
set_time_limit(1800); // Waktu eksekusi 30 menit untuk file raksasa (>1 GB)
ini_set('memory_limit', '1024M');

if (!auth_check()) {
    json_response(['ok' => false, 'msg' => 'Unauthorized'], 401);
}

$uploadId = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['upload_id'] ?? '');
$fileName = basename($_POST['file_name'] ?? '');
$mode     = ($_POST['mode'] ?? 'replace') === 'append' ? 'append' : 'replace';

$tmpDir    = sys_get_temp_dir() . '/backuprm_' . $uploadId;
$finalFile = $tmpDir . '/' . $fileName;

if ($uploadId === '' || $fileName === '' || !file_exists($finalFile)) {
    json_response(['ok' => false, 'msg' => 'File SQL gabungan tidak ditemukan di server.'], 404);
}

$fileSize = filesize($finalFile);
$fileHash = hash_file_sha256($finalFile);

if ($fileHash === null) {
    json_response(['ok' => false, 'msg' => 'Gagal menghitung hash file gabungan.'], 500);
}

$handle = fopen($finalFile, 'r');
if (!$handle) {
    json_response(['ok' => false, 'msg' => 'Gagal membaca file SQL gabungan.'], 500);
}

$pdo = db();
$inserted = 0;
$skipped  = 0;
$importBatch = generate_batch_id();

$currentUser = auth_user();
$userId   = $currentUser['id']   ?? null;
$userNrk  = $currentUser['nrk']  ?? '-';
$userNama = $currentUser['nama'] ?? '-';

try {
    // 1. Catat identitas batch di s_upload_batch
    $stmtBatch = $pdo->prepare("
        INSERT INTO s_upload_batch
            (batch_id, uploaded_by, uploaded_by_nrk, uploaded_by_nama,
             uploaded_at, file_name, file_size, file_hash, mode, status)
        VALUES
            (:bid, :uid, :nrk, :nama, NOW(), :fname, :fsize, :fhash, :mode, 'uploaded')
    ");
    $stmtBatch->execute([
        ':bid'   => $importBatch,
        ':uid'   => $userId,
        ':nrk'   => $userNrk,
        ':nama'  => $userNama,
        ':fname' => $fileName,
        ':fsize' => $fileSize,
        ':fhash' => $fileHash,
        ':mode'  => $mode,
    ]);
    $batchDbId = (int)$pdo->lastInsertId();

    $pdo->beginTransaction();

    if ($mode === 'replace') {
        $pdo->exec("DELETE FROM s_backup_raw");
    }

    $stmt = $pdo->prepare("
        INSERT INTO s_backup_raw (source_row_id, raw_data, import_batch, batch_id)
        VALUES (:srcid, :raw, :batch, :bid)
    ");

    $buffer = '';

    while (($line = fgets($handle)) !== false) {
        $buffer .= $line;

        if (preg_match('#;\s*$#', rtrim($line))) {
            $stmtText = trim($buffer);
            $buffer = '';

            if (stripos($stmtText, 'INSERT INTO') === false) continue;

            // Gunakan parser_sql.php untuk mengekstrak data
            $parsed = sql_parse_row($stmtText);
            if (empty($parsed)) {
                $skipped++;
                continue;
            }

            // Ambil source_row_id dari hasil parse (no_transaksi/id_backup)
            $srcId = $parsed['no_transaksi'] ?? null;

            $stmt->execute([
                ':srcid' => $srcId,
                ':raw'   => $stmtText,
                ':batch' => $importBatch,
                ':bid'   => $importBatch,
            ]);
            $inserted++;
        }
    }
    fclose($handle);
    $handle = null;

    // Sisa buffer jika ada
    if (trim($buffer) !== '' && stripos($buffer, 'INSERT INTO') !== false) {
        $parsed = sql_parse_row(trim($buffer));
        if (!empty($parsed)) {
            $srcId = $parsed['no_transaksi'] ?? null;
            $stmt->execute([
                ':srcid' => $srcId,
                ':raw'   => trim($buffer),
                ':batch' => $importBatch,
                ':bid'   => $importBatch,
            ]);
            $inserted++;
        }
    }

    // Hitung raw_hash
    $rawHash = hash_backup_raw($importBatch);

    // Update data statistik di s_upload_batch
    $stmtUpd = $pdo->prepare("
        UPDATE s_upload_batch
           SET total_rows    = :total,
               imported_rows = :imported,
               skipped_rows  = :skipped,
               raw_hash      = :rhash
         WHERE id = :id
    ");
    $stmtUpd->execute([
        ':total'    => $inserted + $skipped,
        ':imported' => $imported,
        ':skipped'  => $skipped,
        ':rhash'    => $rawHash,
        ':id'       => $batchDbId,
    ]);

    $pdo->commit();

    // Hapus file temporary SQL utuh & foldernya setelah sukses di-parse
    if (file_exists($finalFile)) {
        @unlink($finalFile);
    }
    @rmdir($tmpDir);

    log_activity('parse_sql_file', sprintf(
        'Parse SQL Selesai %s: mode=%s, %d baris, batch=%s',
        $fileName, $mode, $inserted, $importBatch
    ));

    json_response([
        'ok'       => true,
        'msg'      => "Berhasil memproses dan menyimpan {$inserted} baris data ke database.",
        'batch'    => $importBatch,
        'batch_id' => $batchDbId,
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($handle) @fclose($handle);

    try {
        $pdo->prepare("UPDATE s_upload_batch SET status='failed', catatan=:msg WHERE batch_id=:bid")
            ->execute([':msg' => $e->getMessage(), ':bid' => $importBatch]);
    } catch (Throwable $e2) {}

    log_error('parse_sql_file: ' . $e->getMessage());
    json_response(['ok' => false, 'msg' => 'Gagal Parse Data: ' . $e->getMessage()], 500);
}