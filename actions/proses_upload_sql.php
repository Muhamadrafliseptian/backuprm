<?php
/**
 * file   : proses_upload_sql.php
 * path   : C:\xampp\htdocs\backuprm\actions\proses_upload_sql.php
 * fungsi : Upload file SQL → parse → s_backup_raw + identitas batch + hash
 */
declare(strict_types=1);

require_once BASE_PATH . '/core/parser_helper.php';
require_once BASE_PATH . '/core/hash_helper.php';

csrf_check_or_die();
set_time_limit(600);
ini_set('memory_limit', '512M');

if (!auth_check()) {
    json_response(['ok' => false, 'msg' => 'Unauthorized'], 401);
}

/* ---------- Validasi file ---------- */
if (!isset($_FILES['sql_file']) || $_FILES['sql_file']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['sql_file']['error'] ?? -1;
    $msgs = [
        UPLOAD_ERR_INI_SIZE   => 'File melebihi batas upload server.',
        UPLOAD_ERR_FORM_SIZE  => 'File melebihi batas form.',
        UPLOAD_ERR_PARTIAL    => 'Upload terputus.',
        UPLOAD_ERR_NO_FILE    => 'Tidak ada file yang dipilih.',
        UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary tidak ada.',
        UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file.',
        UPLOAD_ERR_EXTENSION  => 'Upload dihentikan ekstensi.',
    ];
    json_response(['ok' => false, 'msg' => $msgs[$errCode] ?? 'Upload gagal.'], 400);
}

$file = $_FILES['sql_file'];
$ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if ($ext !== 'sql') {
    json_response(['ok' => false, 'msg' => 'Hanya file .sql yang diizinkan.'], 400);
}

if ($file['size'] > UPLOAD_MAX_SIZE) {
    json_response([
        'ok'  => false,
        'msg' => 'File terlalu besar. Maksimal ' . round(UPLOAD_MAX_SIZE / 1048576, 1) . ' MB.'
    ], 400);
}

/* ---------- Validasi MIME ---------- */
if (function_exists('finfo_open')) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    $allowedMimes = [
        'text/plain', 'text/x-sql', 'application/sql',
        'application/octet-stream', 'application/x-sql',
    ];
    if (!in_array($mime, $allowedMimes, true)) {
        json_response(['ok' => false, 'msg' => 'Tipe file tidak valid (MIME: ' . $mime . ').'], 400);
    }
}

$mode = ($_POST['mode'] ?? 'replace') === 'append' ? 'append' : 'replace';

/* ============================================================
   HITUNG HASH FILE ASLI
   ============================================================ */
$fileHash = hash_file_sha256($file['tmp_name']);
if ($fileHash === null) {
    json_response(['ok' => false, 'msg' => 'Gagal hitung hash file.'], 500);
}

/* ============================================================
   BACA FILE
   ============================================================ */
$handle = fopen($file['tmp_name'], 'r');
if (!$handle) {
    json_response(['ok' => false, 'msg' => 'Gagal membaca file.'], 500);
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
    /* ---------- 1. Insert identitas batch dulu (status: uploading) ---------- */
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
        ':fname' => $file['name'],
        ':fsize' => $file['size'],
        ':fhash' => $fileHash,
        ':mode'  => $mode,
    ]);
    $batchDbId = (int)$pdo->lastInsertId();

    /* ---------- 2. Mulai transaction untuk insert raw ---------- */
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

            $srcId = extract_source_row_id($stmtText);

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

    // Sisa buffer terakhir
    if (trim($buffer) !== '' && stripos($buffer, 'INSERT INTO') !== false) {
        if (preg_match('#VALUES\s*\(.*\)#is', $buffer)) {
            $srcId = extract_source_row_id(trim($buffer));
            $stmt->execute([
                ':srcid' => $srcId,
                ':raw'   => trim($buffer),
                ':batch' => $importBatch,
                ':bid'   => $importBatch,
            ]);
            $inserted++;
        }
    }

    /* ---------- 3. Hitung raw_hash ---------- */
    $rawHash = hash_backup_raw($importBatch);

    /* ---------- 4. Update batch dengan statistik + raw_hash ---------- */
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

    /* ---------- 5. Log ---------- */
    log_activity('upload_sql', sprintf(
        'Upload %s: mode=%s, %d baris, batch=%s, file_hash=%s..., raw_hash=%s...',
        $file['name'], $mode, $inserted, $importBatch,
        substr($fileHash, 0, 12), substr($rawHash, 0, 12)
    ));

    /* ---------- 6. Response ---------- */
    json_response([
        'ok'       => true,
        'msg'      => "Berhasil upload: {$inserted} baris dimasukkan.",
        'inserted' => $inserted,
        'skipped'  => $skipped,
        'batch'    => $importBatch,
        'batch_id' => $batchDbId,
        'hashes'   => [
            'file_hash' => $fileHash,
            'raw_hash'  => $rawHash,
        ],
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($handle) @fclose($handle);

    /* Tandai batch sebagai failed */
    try {
        $pdo->prepare("UPDATE s_upload_batch SET status='failed', catatan=:msg WHERE batch_id=:bid")
            ->execute([':msg' => $e->getMessage(), ':bid' => $importBatch]);
    } catch (Throwable $e2) {}

    log_error('upload_sql: ' . $e->getMessage());
    json_response(['ok' => false, 'msg' => 'Gagal: ' . $e->getMessage()], 500);
}

/**
 * Ambil value pertama dari statement INSERT.
 */
function extract_source_row_id(string $line): ?string
{
    if (!preg_match('#VALUES\s*\(\s*\'?([^\',\)]+)\'?\s*,#is', $line, $m)) {
        return null;
    }
    return trim($m[1]);
}