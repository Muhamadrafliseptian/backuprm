<?php
/**
 * file   : proses_mark_verified.php
 * path   : C:\xampp\htdocs\backuprm\actions\proses_mark_verified.php
 * fungsi : Tandai batch sebagai VERIFIED + update verify_result
 */
declare(strict_types=1);

require_once BASE_PATH . '/core/hash_helper.php';

csrf_check_or_die();

if (!auth_check()) {
    json_response(['ok' => false, 'msg' => 'Unauthorized'], 401);
}

$batchId = trim((string)($_POST['batch'] ?? ''));
if ($batchId === '') {
    json_response(['ok' => false, 'msg' => 'Batch ID kosong.'], 400);
}

try {
    $pdo = db();

    /* Ambil batch */
    $stmt = $pdo->prepare("SELECT * FROM s_upload_batch WHERE batch_id = :bid LIMIT 1");
    $stmt->execute([':bid' => $batchId]);
    $batch = $stmt->fetch();
    if (!$batch) {
        json_response(['ok' => false, 'msg' => 'Batch tidak ditemukan.'], 404);
    }

    /* Hitung ulang hash */
    $currentRawHash    = hash_backup_raw($batchId);
    $currentResultData = hash_generated_result();
    $currentResultHash = $currentResultData['hash'];

    /* Bandingkan */
    $rawMatch    = $batch['raw_hash']    && hash_equals($batch['raw_hash'],    $currentRawHash);
    $resultMatch = $batch['result_hash'] && hash_equals($batch['result_hash'], $currentResultHash);
    $overall     = $rawMatch && $resultMatch;

    if (!$overall) {
        $pdo->prepare("UPDATE s_upload_batch SET status='mismatch', verify_result='MISMATCH', verified_at=NOW() WHERE id=:id")
            ->execute([':id' => $batch['id']]);
        json_response(['ok' => false, 'msg' => 'Tidak bisa verifikasi: hash tidak cocok.'], 400);
    }

    /* Update status */
    $pdo->prepare("
        UPDATE s_upload_batch
           SET status = 'verified',
               verify_result = 'MATCH',
               verify_detail = :detail,
               verified_at = NOW()
         WHERE id = :id
    ")->execute([
        ':detail' => json_encode([
            'raw_match'    => $rawMatch,
            'result_match' => $resultMatch,
            'current_raw_hash'    => $currentRawHash,
            'current_result_hash' => $currentResultHash,
        ]),
        ':id' => $batch['id'],
    ]);

    log_activity('verify_batch', sprintf(
        'Batch %s diverifikasi MATCH oleh %s',
        $batchId,
        auth_user()['nrk'] ?? '-'
    ));

    json_response(['ok' => true, 'msg' => 'Batch berhasil diverifikasi.']);

} catch (Throwable $e) {
    log_error('mark_verified: ' . $e->getMessage());
    json_response(['ok' => false, 'msg' => 'Gagal: ' . $e->getMessage()], 500);
}