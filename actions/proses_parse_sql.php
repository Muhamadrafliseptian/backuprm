<?php
/**
 * file   : actions/proses_parse_sql.php
 * path   : C:\xampp\htdocs\backuprm\actions\proses_parse_sql.php
 * fungsi : Mengurai file SQL gabungan chunk 100MB dan menyimpan tiap row ke s_backup_raw
 */
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/internal_guard.php';
require_once BASE_PATH . '/core/parser_sql.php';
require_once BASE_PATH . '/core/hash_helper.php';

csrf_check_or_die();
set_time_limit(600);
ini_set('memory_limit', '1024M');

if (!auth_check()) {
    json_response(['ok' => false, 'msg' => 'Unauthorized'], 401);
}

$uploadId = preg_replace('/[^a-zA-Z0-9_-]/', '',$_POST['upload_id'] ?? '');
$fileName = basename($_POST['file_name'] ?? '');
$mode     = ($_POST['mode'] ?? 'replace') === 'append' ? 'append' : 'replace';

$tmpDir = sys_get_temp_dir() . '/backuprm_' .$uploadId;
$finalFile = $tmpDir . '/' .$fileName;

if (!file_exists($finalFile)) {
    json_response(['ok' => false, 'msg' => 'File SQL gabungan tidak ditemukan.'], 400);
}

$pdo = db();
$importBatch =$_POST['import_batch'] ?? ('BATCH-' . date('Ymd-His') . '-' . substr(md5(uniqid()), 0, 6));

try {
    if ($mode === 'replace') {$pdo->exec("TRUNCATE TABLE s_backup_raw");
    }

    $fileHash = hash_file_sha256($finalFile);
    $fileSize = filesize($finalFile);

    $stmtBatch = $pdo->prepare("
        INSERT INTO s_upload_batch
            (batch_id, uploaded_by, uploaded_by_nrk, uploaded_by_nama, uploaded_at, file_name, file_size, file_hash, mode, status)
        VALUES
            (:bid, :uid, :unrk, :unama, NOW(), :fname, :fsize, :fhash, :mode, 'uploaded')
        ON DUPLICATE KEY UPDATE status = 'uploaded'
    ");
    $stmtBatch->execute([
        ':bid'   => $importBatch,
        ':uid'   => function_exists('auth_id') ? auth_id() : null, // Diubah dari auth_user_id() ke auth_id()
        ':unrk'  => function_exists('auth_nrk') ? auth_nrk() : null,
        ':unama' => function_exists('auth_nama') ? auth_nama() : 'Admin',
        ':fname' => $fileName,
        ':fsize' => $fileSize,
        ':fhash' => $fileHash,
        ':mode'  => $mode,
    ]);

    $handle = fopen($finalFile, 'r');
    if (!$handle) {
        json_response(['ok' => false, 'msg' => 'Gagal membuka file SQL.'], 500);
    }

    $pdo->beginTransaction();

    $stmtInsert =$pdo->prepare("
        INSERT INTO s_backup_raw (source_row_id, raw_data, import_batch, batch_id)
        VALUES (:srcid, :raw, :batch, :bid)
    ");

    $inserted = 0;
    $buffer = '';

    while (($line = fgets($handle)) !== false) {
        $buffer .=$line;

        if (preg_match('#;\s*$#', rtrim($line))) {$sqlStatement = trim($buffer);$buffer = '';

            if (stripos($sqlStatement, 'INSERT INTO') === false) continue;

            // Pecah multi-row tuples
            $tuples = sql_extract_all_tuples($sqlStatement);

            foreach ($tuples as$tupleValues) {
                if (empty($tupleValues)) continue;
                
                $noTrans =$tupleValues[COL['id_backup']] ?? ('TRANS-' . uniqid());
                // Reconstruction string tuple untuk raw_data
                $rawTupleStr = "INSERT INTO `backup_rajal_dinkes_2026` VALUES ('" . implode("','", array_map('addslashes', array_map('strval', $tupleValues))) . "');";

                $stmtInsert->execute([
                    ':srcid' => $noTrans,
                    ':raw'   => $rawTupleStr,
                    ':batch' => $importBatch,
                    ':bid'   => $importBatch,
                ]);
                $inserted++;
            }
        }
    }

    fclose($handle);$pdo->commit();

    // Clean temp
    @unlink($finalFile);
    @rmdir($tmpDir);

    // Update Status Batch
    $totalRaw = (int)$pdo->query("SELECT COUNT(*) FROM s_backup_raw")->fetchColumn();
    $rawHash  = hash_backup_raw($importBatch);

    $pdo->prepare("
        UPDATE s_upload_batch
           SET total_rows = :total, imported_rows = :inserted, raw_hash = :rhash
         WHERE batch_id = :bid
    ")->execute([
        ':total'    => $totalRaw,
        ':inserted' => $inserted,
        ':rhash'    => $rawHash,
        ':bid'      => $importBatch,
    ]);

    json_response([
        'ok'           => true,
        'finished'     => true,
        'inserted'     => $inserted,
        'total_raw'    => $totalRaw,
        'msg'          => "Berhasil mengurai {$inserted} data pasien ke penampungan sementara (s_backup_raw).",
        'batch'        => $importBatch
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {$pdo->rollBack();
    }
    log_error('proses_parse_sql: ' . $e->getMessage());
    json_response(['ok' => false, 'msg' => 'Gagal: ' . $e->getMessage()], 500);
}