<?php
/**
 * file   : proses_upload_chunk.php
 * path   : C:\xampp\htdocs\backuprm\actions\proses_upload_chunk.php
 * fungsi : Menerima chunk file SQL dari frontend, menggabungkannya, lalu parse ke s_backup_raw
 */
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/internal_guard.php';

require_once BASE_PATH . '/core/parser_helper.php';
require_once BASE_PATH . '/core/hash_helper.php';

csrf_check_or_die();
set_time_limit(600);
ini_set('memory_limit', '1024M');

if (!auth_check()) {
    json_response(['ok' => false, 'msg' => 'Unauthorized'], 401);
}

$chunkIndex  = (int)($_POST['chunk_index'] ?? 0);
$totalChunks = (int)($_POST['total_chunks'] ?? 0);
$uploadId    = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['upload_id'] ?? '');
$fileName    = basename($_POST['file_name'] ?? 'backup.sql');
$mode        = ($_POST['mode'] ?? 'replace') === 'append' ? 'append' : 'replace';

if ($uploadId === '' || !isset($_FILES['sql_chunk']) || $_FILES['sql_chunk']['error'] !== UPLOAD_ERR_OK) {
    json_response(['ok' => false, 'msg' => 'Data chunk tidak valid atau gagal di-upload.'], 400);
}

/* ============================================================
   1. SIMPAN CHUNK KE FOLDER TEMPORARY
   ============================================================ */
$tmpDir = sys_get_temp_dir() . '/backuprm_' . $uploadId;
if (!is_dir($tmpDir)) {
    mkdir($tmpDir, 0777, true);
}

$chunkPath = $tmpDir . '/' . str_pad((string)$chunkIndex, 6, '0', STR_PAD_LEFT) . '.part';
if (!move_uploaded_file($_FILES['sql_chunk']['tmp_name'], $chunkPath)) {
    json_response(['ok' => false, 'msg' => 'Gagal menyimpan chunk ke server.'], 500);
}

/* ============================================================
   2. CEK APAKAH SEMUA CHUNK SUDAH LENGKAP
   ============================================================ */
$allReceived = true;
for ($i = 0; $i < $totalChunks; $i++) {
    $part = $tmpDir . '/' . str_pad((string)$i, 6, '0', STR_PAD_LEFT) . '.part';
    if (!file_exists($part)) {
        $allReceived = false;
        break;
    }
}

// Jika belum lengkap, berikan respons sukses agar frontend lanjut mengirim chunk berikutnya
if (!$allReceived) {
    json_response([
        'ok'       => true,
        'finished' => false,
        'msg'      => "Chunk {$chunkIndex} berhasil diterima."
    ]);
}

/* ============================================================
   3. GABUNGKAN SEMUA CHUNK JADI FILE SQL UTUH
   ============================================================ */
$finalFile = $tmpDir . '/' . $fileName;
$out = fopen($finalFile, 'wb');
if (!$out) {
    json_response(['ok' => false, 'msg' => 'Gagal membuat file gabungan.'], 500);
}

for ($i = 0; $i < $totalChunks; $i++) {
    $part = $tmpDir . '/' . str_pad((string)$i, 6, '0', STR_PAD_LEFT) . '.part';
    $in = fopen($part, 'rb');
    if ($in) {
        stream_copy_to_stream($in, $out);
        fclose($in);
    }
}
fclose($out);

/* ============================================================
   4. PROSES PARSING SQL KE s_backup_raw (Sama seperti proses_upload_sql.php)
   ============================================================ */
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
    // Insert identitas batch
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

            if (!preg_match('#VALUES\s*\(.*\)#is', $stmtText)) {
                $skipped++;
                continue;
            }

            // Ekstrak source_row_id dari kolom pertama VALUES
            $srcId = null;
            if (preg_match('#VALUES\s*\(\s*\'?([^\',\)]+)\'?\s*,#is', $stmtText, $m)) {
                $srcId = trim($m[1]);
            }

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

    // Sisa buffer terakhir jika ada
    if (trim($buffer) !== '' && stripos($buffer, 'INSERT INTO') !== false) {
        if (preg_match('#VALUES\s*\(.*\)#is', $buffer)) {
            $srcId = null;
            if (preg_match('#VALUES\s*\(\s*\'?([^\',\)]+)\'?\s*,#is', trim($buffer), $m)) {
                $srcId = trim($m[1]);
            }
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

    // Update batch statistik
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
        ':imported' => $inserted,
        ':skipped'  => $skipped,
        ':rhash'    => $rawHash,
        ':id'       => $batchDbId,
    ]);

    $pdo->commit();

    // Bersihkan file temporary chunk dan file gabungan
    array_map('unlink', glob("$tmpDir/*.*"));
    @rmdir($tmpDir);

    // Log aktivitas
    log_activity('upload_chunk_sql', sprintf(
        'Upload Chunk %s: mode=%s, %d baris, batch=%s',
        $fileName, $mode, $inserted, $importBatch
    ));

    json_response([
        'ok'       => true,
        'finished' => true,
        'msg'      => "Berhasil upload & parse: {$inserted} baris dimasukkan.",
        'batch'    => $importBatch,
        'batch_id' => $batchDbId,
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($handle) @fclose($handle);

    // Tandai batch gagal jika sempat tercatat
    try {
        $pdo->prepare("UPDATE s_upload_batch SET status='failed', catatan=:msg WHERE batch_id=:bid")
            ->execute([':msg' => $e->getMessage(), ':bid' => $importBatch]);
    } catch (Throwable $e2) {}

    log_error('upload_chunk_sql: ' . $e->getMessage());
    json_response(['ok' => false, 'msg' => 'Gagal Proses: ' . $e->getMessage()], 500);
}