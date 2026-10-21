<?php
/**
- file   : proses_upload_chunk.php
- path   : C:\xampp\htdocs\backuprm\actions\proses_upload_chunk.php
- fungsi : Menerima chunk file SQL dari frontend dan menggabungkannya menjadi file utuh
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

// Hapus potongan file .part untuk menghemat ruang disk (menyisakan file .sql utuh)
for ($i = 0; $i < $totalChunks; $i++) {
    $part = $tmpDir . '/' . str_pad((string)$i, 6, '0', STR_PAD_LEFT) . '.part';
    if (file_exists($part)) {
        @unlink($part);
    }
}

/* ============================================================
   4. RESPON CEPAT KE FRONTEND
   ============================================================ */
log_activity('upload_chunk_sql', sprintf(
    'Upload & Penggabungan Chunk Selesai: %s (ID: %s, Mode: %s)',
    $fileName, $uploadId, $mode
));

// Kirim respons selesai secara cepat tanpa memicu timeout parsing database
json_response([
    'ok'        => true,
    'finished'  => true,
    'msg'       => "File SQL ({$fileName}) berhasil diunggah dan digabungkan sepenuhnya.",
    'upload_id' => $uploadId,
    'file_name' => $fileName,
    'file_path' => $finalFile,
    'mode'      => $mode,
]);