<?php
/**
 * file   : verifikasi.php
 * path   : C:\xampp\htdocs\backuprm\pages\verifikasi.php
 * fungsi : Verifikasi integritas data upload (hash chain)
 *          URL: /verifikasi  atau  /verifikasi?batch=BATCH-xxx
 *
 * Status yang didukung:
 *   - failed            : batch gagal diproses
 *   - superseded        : batch sudah digantikan batch lain (raw data dihapus)
 *   - incomplete_upload : raw_hash belum tersimpan
 *   - not_generated     : belum di-generate (result_hash kosong)
 *   - verified          : semua hash cocok
 *   - mismatch          : hash tidak cocok (data berubah)
 */
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/internal_guard.php';

require BASE_PATH . '/layouts/header.php';
require BASE_PATH . '/layouts/sidebar.php';
require_once BASE_PATH . '/core/hash_helper.php';

$pdo = db();
$batchId = trim((string)($_GET['batch'] ?? ''));

/* ============================================================
   DETAIL SATU BATCH
   ============================================================ */
if ($batchId !== '') {
    $stmt = $pdo->prepare("SELECT * FROM s_upload_batch WHERE batch_id = :bid LIMIT 1");
    $stmt->execute([':bid' => $batchId]);
    $batch = $stmt->fetch();

    if (!$batch) {
        echo '<div class="bg-rose-50 border border-rose-200 text-rose-700 rounded-xl p-4 text-sm">Batch tidak ditemukan.</div>';
        require BASE_PATH . '/layouts/footer.php';
        exit;
    }

    /* ---------- CEK: APAKAH BATCH INI MASIH YANG TERAKHIR? ---------- */
    $stmtLast = $pdo->query("
        SELECT batch_id FROM s_upload_batch
        ORDER BY uploaded_at DESC, id DESC LIMIT 1
    ");
    $lastBatchId = $stmtLast->fetchColumn();
    $isLastBatch = ($lastBatchId === $batch['batch_id']);

    /* ---------- CEK: MASIH ADA RAW DATA UNTUK BATCH INI? ---------- */
    $stmtCountRaw = $pdo->prepare("
        SELECT COUNT(*) FROM s_backup_raw
        WHERE batch_id = :bid1 OR import_batch = :bid2
    ");
    $stmtCountRaw->execute([':bid1' => $batchId, ':bid2' => $batchId]);
    $rawRowsForBatch = (int)$stmtCountRaw->fetchColumn();

    /* ---------- HITUNG HASH SAAT INI ---------- */
    $currentRawHash    = hash_backup_raw($batchId);
    $currentResultData = hash_generated_result();
    $currentResultHash = $currentResultData['hash'];

    /* ---------- DETEKSI STATUS ---------- */
    $hasRawHash    = !empty($batch['raw_hash']);
    $hasResultHash = !empty($batch['result_hash']);
    $isFailed      = ($batch['status'] === 'failed');

    $rawMatch    = $hasRawHash    && hash_equals($batch['raw_hash'],    $currentRawHash);
    $resultMatch = $hasResultHash && hash_equals($batch['result_hash'], $currentResultHash);

    /* ---------- TENTUKAN STATUS KESELURUHAN ---------- */
    if ($isFailed) {
        $overall = 'failed';
    } elseif (!$isLastBatch && $rawRowsForBatch === 0) {
        /* Batch bukan yang terakhir DAN raw-nya sudah dihapus → superseded */
        $overall = 'superseded';
    } elseif (!$hasRawHash) {
        $overall = 'incomplete_upload';
    } elseif (!$hasResultHash) {
        $overall = 'not_generated';
    } elseif ($rawMatch && $resultMatch) {
        $overall = 'verified';
    } else {
        $overall = 'mismatch';
    }

    /* ---------- KONFIGURASI TAMPILAN PER STATUS ---------- */
    $statusConfig = [
        'failed' => [
            'bg'    => 'bg-rose-50 border-rose-200 text-rose-900',
            'icon'  => 'ri-error-warning-line',
            'ibg'   => 'bg-rose-500',
            'title' => 'Batch Gagal Diproses',
            'desc'  => 'Batch ini gagal saat upload. Silakan upload ulang file SQL.',
            'note'  => 'Alasan: ' . ($batch['catatan'] ?: 'Tidak ada keterangan.'),
        ],
        'superseded' => [
            'bg'    => 'bg-slate-50 border-slate-300 text-slate-800',
            'icon'  => 'ri-archive-2-line',
            'ibg'   => 'bg-slate-500',
            'title' => 'Batch Sudah Digantikan',
            'desc'  => 'Batch ini sudah digantikan oleh batch yang lebih baru. Data raw-nya sudah dikosongkan, jadi tidak bisa diverifikasi ulang.',
            'note'  => 'Ini normal jika Anda mengupload ulang data dengan mode replace atau mengosongkan backup. Verifikasi hash hanya berlaku untuk batch terakhir.',
        ],
        'incomplete_upload' => [
            'bg'    => 'bg-amber-50 border-amber-200 text-amber-900',
            'icon'  => 'ri-upload-cloud-2-line',
            'ibg'   => 'bg-amber-500',
            'title' => 'Upload Belum Selesai',
            'desc'  => 'Hash data mentah (raw_hash) belum tersimpan. Kemungkinan upload terputus di tengah jalan.',
            'note'  => 'Silakan upload ulang file SQL.',
        ],
        'not_generated' => [
            'bg'    => 'bg-blue-50 border-blue-200 text-blue-900',
            'icon'  => 'ri-magic-line',
            'ibg'   => 'bg-blue-500',
            'title' => 'Belum Di-generate',
            'desc'  => 'Batch sudah ter-upload dengan baik, tetapi belum di-preview & simpan ke database.',
            'note'  => 'Buka halaman Upload → Preview Data → Simpan ke Database.',
        ],
        'verified' => [
            'bg'    => 'bg-mint-50 border-mint-200 text-mint-900',
            'icon'  => 'ri-shield-check-line',
            'ibg'   => 'bg-mint-500',
            'title' => 'Data Valid & Terverifikasi',
            'desc'  => 'Hash chain cocok. Data di database identik dengan file yang di-upload.',
            'note'  => null,
        ],
        'mismatch' => [
            'bg'    => 'bg-rose-50 border-rose-200 text-rose-900',
            'icon'  => 'ri-shield-cross-line',
            'ibg'   => 'bg-rose-500',
            'title' => 'Data Tidak Cocok / Berubah',
            'desc'  => 'Hash tersimpan berbeda dengan hash saat ini. Data mungkin diubah manual atau corrupt.',
            'note'  => null,
        ],
    ];

    $cfg = $statusConfig[$overall];
    ?>

    <!-- ============================================================
         PAGE HEADER
         ============================================================ -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 fade-in-up">
        <div>
            <h2 class="text-xl md:text-2xl font-bold text-slate-900 tracking-tight">Verifikasi Batch</h2>
            <p class="text-xs md:text-sm text-slate-500">
                Batch: <code class="text-mint-600"><?= e($batch['batch_id']) ?></code>
            </p>
        </div>
        <a href="<?= base_url('verifikasi') ?>"
           class="flex items-center gap-1.5 px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-colors shadow-sm">
            <i class="ri-arrow-left-line"></i> Kembali
        </a>
    </div>

    <!-- ============================================================
         OVERALL STATUS
         ============================================================ -->
    <div class="<?= $cfg['bg'] ?> border-2 rounded-2xl p-6 flex items-start gap-4">
        <div class="p-3 <?= $cfg['ibg'] ?> text-white rounded-2xl shrink-0">
            <i class="<?= $cfg['icon'] ?> text-3xl"></i>
        </div>
        <div class="flex-1 min-w-0">
            <h3 class="text-lg font-bold"><?= e($cfg['title']) ?></h3>
            <p class="text-sm mt-1 opacity-90"><?= e($cfg['desc']) ?></p>
            <?php if (!empty($cfg['note'])): ?>
                <div class="mt-3 pt-3 border-t border-current/20 text-xs break-words">
                    <b>Info:</b> <?= e($cfg['note']) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================================
         IDENTITAS UPLOAD & STATISTIK
         ============================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

        <!-- Info Upload -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm">
            <div class="p-5 border-b border-slate-100 flex items-center gap-2">
                <i class="ri-user-received-line text-mint-600 text-lg"></i>
                <h3 class="font-bold text-slate-900 text-base">Identitas Upload</h3>
            </div>
            <div class="p-5 space-y-3 text-xs">
                <div class="flex justify-between gap-3 border-b border-slate-100 pb-2">
                    <span class="text-slate-500 shrink-0">Batch ID</span>
                    <code class="text-mint-700 font-bold break-all text-right"><?= e($batch['batch_id']) ?></code>
                </div>
                <div class="flex justify-between gap-3 border-b border-slate-100 pb-2">
                    <span class="text-slate-500 shrink-0">Di-upload oleh</span>
                    <span class="font-semibold text-slate-800 text-right">
                        <?= e($batch['uploaded_by_nama'] ?: '-') ?>
                        <span class="text-slate-400">(<?= e($batch['uploaded_by_nrk'] ?: '-') ?>)</span>
                    </span>
                </div>
                <div class="flex justify-between gap-3 border-b border-slate-100 pb-2">
                    <span class="text-slate-500 shrink-0">Waktu Upload</span>
                    <span class="font-semibold text-slate-800 text-right">
                        <?= e(date('d/m/Y H:i:s', strtotime($batch['uploaded_at']))) ?>
                    </span>
                </div>
                <div class="flex justify-between gap-3 border-b border-slate-100 pb-2">
                    <span class="text-slate-500 shrink-0">Nama File</span>
                    <span class="font-semibold text-slate-800 text-right truncate max-w-[220px]" title="<?= e($batch['file_name']) ?>">
                        <?= e($batch['file_name'] ?: '-') ?>
                    </span>
                </div>
                <div class="flex justify-between gap-3 border-b border-slate-100 pb-2">
                    <span class="text-slate-500 shrink-0">Ukuran File</span>
                    <span class="font-semibold text-slate-800 text-right">
                        <?= number_format((int)$batch['file_size'] / 1024, 1) ?> KB
                    </span>
                </div>
                <div class="flex justify-between gap-3 border-b border-slate-100 pb-2">
                    <span class="text-slate-500 shrink-0">Mode</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold
                        <?= $batch['mode'] === 'replace' ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-700' ?>">
                        <?= e(strtoupper($batch['mode'])) ?>
                    </span>
                </div>
                <div class="flex justify-between gap-3">
                    <span class="text-slate-500 shrink-0">Status</span>
                    <?php
                    $statusBadge = match ($batch['status']) {
                        'verified'  => 'bg-mint-100 text-mint-700',
                        'generated' => 'bg-blue-100 text-blue-700',
                        'uploaded'  => 'bg-slate-100 text-slate-700',
                        'mismatch'  => 'bg-rose-100 text-rose-700',
                        'failed'    => 'bg-rose-100 text-rose-700',
                        default     => 'bg-slate-100 text-slate-700',
                    };
                    ?>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold <?= $statusBadge ?>">
                        <?= e(strtoupper($batch['status'])) ?>
                    </span>
                </div>
                <?php if (!empty($batch['verified_at'])): ?>
                <div class="flex justify-between gap-3 border-t border-slate-100 pt-2">
                    <span class="text-slate-500 shrink-0">Terverifikasi pada</span>
                    <span class="font-semibold text-mint-700 text-right">
                        <?= e(date('d/m/Y H:i:s', strtotime($batch['verified_at']))) ?>
                    </span>
                </div>
                <?php endif; ?>
                <div class="flex justify-between gap-3 border-t border-slate-100 pt-2">
                    <span class="text-slate-500 shrink-0">Baris raw saat ini</span>
                    <span class="font-semibold <?= $rawRowsForBatch > 0 ? 'text-slate-800' : 'text-rose-600' ?> text-right">
                        <?= number_format($rawRowsForBatch) ?> baris
                        <?php if ($rawRowsForBatch === 0): ?>
                            <span class="text-[10px] text-rose-500">(sudah dihapus)</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Statistik -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm">
            <div class="p-5 border-b border-slate-100 flex items-center gap-2">
                <i class="ri-bar-chart-line text-mint-600 text-lg"></i>
                <h3 class="font-bold text-slate-900 text-base">Statistik</h3>
            </div>
            <div class="p-5">
                <div class="grid grid-cols-3 gap-3 text-center">
                    <div class="p-3 bg-slate-50 rounded-xl">
                        <div class="text-[10px] text-slate-400 uppercase font-semibold">Total</div>
                        <div class="text-xl font-bold text-slate-800"><?= number_format((int)$batch['total_rows']) ?></div>
                    </div>
                    <div class="p-3 bg-mint-50 rounded-xl">
                        <div class="text-[10px] text-mint-600 uppercase font-semibold">Imported</div>
                        <div class="text-xl font-bold text-mint-700"><?= number_format((int)$batch['imported_rows']) ?></div>
                    </div>
                    <div class="p-3 bg-amber-50 rounded-xl">
                        <div class="text-[10px] text-amber-600 uppercase font-semibold">Skipped</div>
                        <div class="text-xl font-bold text-amber-700"><?= number_format((int)$batch['skipped_rows']) ?></div>
                    </div>
                </div>

                <?php if (!empty($batch['catatan'])): ?>
                <div class="mt-4 p-3 bg-rose-50 border border-rose-200 rounded-xl">
                    <div class="text-[10px] text-rose-600 uppercase font-semibold mb-1">Catatan</div>
                    <div class="text-xs text-rose-700 break-words"><?= e($batch['catatan']) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ============================================================
         HASH CHAIN
         ============================================================ -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center gap-2">
            <i class="ri-links-line text-mint-600 text-lg"></i>
            <h3 class="font-bold text-slate-900 text-base">Hash Chain</h3>
        </div>
        <div class="p-5 space-y-4">

            <!-- Level 1: File Hash -->
            <div class="border border-slate-200 rounded-xl p-4">
                <div class="flex items-center justify-between flex-wrap gap-2">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-full bg-slate-100 flex items-center justify-center text-xs font-bold text-slate-700">1</div>
                        <div>
                            <div class="text-xs font-bold text-slate-800">File Asli</div>
                            <div class="text-[10px] text-slate-400">SHA256 dari file .sql yang di-upload</div>
                        </div>
                    </div>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 text-[10px] font-bold">
                        <i class="ri-lock-line"></i> IMMUTABLE
                    </span>
                </div>
                <div class="mt-3 bg-slate-900 text-mint-300 font-mono text-[11px] p-3 rounded-lg break-all">
                    <?= e($batch['file_hash'] ?: '(tidak ada)') ?>
                </div>
                <div class="text-[10px] text-slate-400 mt-2">
                    <i class="ri-information-line"></i> Hash ini adalah fingerprint file asli. Kalau file diubah, hash akan berbeda.
                </div>
            </div>

            <!-- Level 2: Raw Hash -->
            <div class="border border-slate-200 rounded-xl p-4">
                <div class="flex items-center justify-between flex-wrap gap-2">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-full bg-slate-100 flex items-center justify-center text-xs font-bold text-slate-700">2</div>
                        <div>
                            <div class="text-xs font-bold text-slate-800">Data Staging (s_backup_raw)</div>
                            <div class="text-[10px] text-slate-400">SHA256 dari baris mentah yang tersimpan</div>
                        </div>
                    </div>
                    <?php if ($rawRowsForBatch === 0): ?>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 text-[10px] font-bold">
                            <i class="ri-delete-bin-line"></i> SUDAH DIHAPUS
                        </span>
                    <?php elseif (!$hasRawHash): ?>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 text-[10px] font-bold">
                            <i class="ri-alert-line"></i> BELUM ADA
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full <?= $rawMatch ? 'bg-mint-100 text-mint-700' : 'bg-rose-100 text-rose-700' ?> text-[10px] font-bold">
                            <i class="<?= $rawMatch ? 'ri-checkbox-circle-fill' : 'ri-close-circle-fill' ?>"></i>
                            <?= $rawMatch ? 'MATCH' : 'MISMATCH' ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-2">
                    <div>
                        <div class="text-[10px] text-slate-400 uppercase font-semibold mb-1">Saat Upload</div>
                        <div class="bg-slate-900 text-mint-300 font-mono text-[11px] p-3 rounded-lg break-all">
                            <?= e($batch['raw_hash'] ?: '(tidak ada)') ?>
                        </div>
                    </div>
                    <div>
                        <div class="text-[10px] text-slate-400 uppercase font-semibold mb-1">Saat Ini</div>
                        <div class="bg-slate-900 <?= (!$hasRawHash || $rawMatch) ? 'text-mint-300' : 'text-rose-300' ?> font-mono text-[11px] p-3 rounded-lg break-all">
                            <?= e($currentRawHash) ?>
                        </div>
                    </div>
                </div>
                <?php if ($rawRowsForBatch === 0 && $hasRawHash): ?>
                <div class="mt-2 text-[10px] text-rose-500">
                    <i class="ri-information-line"></i> Data raw untuk batch ini sudah dihapus, sehingga hash saat ini dihitung dari 0 baris.
                </div>
                <?php endif; ?>
            </div>

            <!-- Level 3: Result Hash -->
            <div class="border border-slate-200 rounded-xl p-4">
                <div class="flex items-center justify-between flex-wrap gap-2">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-full bg-slate-100 flex items-center justify-center text-xs font-bold text-slate-700">3</div>
                        <div>
                            <div class="text-xs font-bold text-slate-800">Data Hasil Generate (10 Tabel)</div>
                            <div class="text-[10px] text-slate-400">SHA256 dari gabungan hash semua tabel</div>
                        </div>
                    </div>
                    <?php if (!$hasResultHash): ?>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 text-[10px] font-bold">
                            <i class="ri-time-line"></i> BELUM GENERATE
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full <?= $resultMatch ? 'bg-mint-100 text-mint-700' : 'bg-rose-100 text-rose-700' ?> text-[10px] font-bold">
                            <i class="<?= $resultMatch ? 'ri-checkbox-circle-fill' : 'ri-close-circle-fill' ?>"></i>
                            <?= $resultMatch ? 'MATCH' : 'MISMATCH' ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-2">
                    <div>
                        <div class="text-[10px] text-slate-400 uppercase font-semibold mb-1">Saat Generate</div>
                        <div class="bg-slate-900 text-mint-300 font-mono text-[11px] p-3 rounded-lg break-all">
                            <?= e($batch['result_hash'] ?: '(belum generate)') ?>
                        </div>
                    </div>
                    <div>
                        <div class="text-[10px] text-slate-400 uppercase font-semibold mb-1">Saat Ini</div>
                        <div class="bg-slate-900 <?= (!$hasResultHash || $resultMatch) ? 'text-mint-300' : 'text-rose-300' ?> font-mono text-[11px] p-3 rounded-lg break-all">
                            <?= e($currentResultHash) ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Per-table Detail -->
            <details class="border border-slate-200 rounded-xl">
                <summary class="cursor-pointer p-4 font-semibold text-slate-700 text-xs flex items-center gap-2">
                    <i class="ri-table-line text-mint-600"></i>
                    Detail Hash per Tabel (klik untuk buka)
                </summary>
                <div class="p-4 border-t border-slate-100">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-slate-500 uppercase tracking-wider font-semibold border-b border-slate-100">
                            <tr>
                                <th class="py-2 px-3">Tabel</th>
                                <th class="py-2 px-3 text-right">Jumlah</th>
                                <th class="py-2 px-3">Hash</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-600">
                        <?php foreach ($currentResultData['per_table'] as $t => $h):
                            $count = $currentResultData['counts'][$t] ?? 0;
                        ?>
                            <tr>
                                <td class="py-2 px-3"><code class="text-mint-700"><?= e($t) ?></code></td>
                                <td class="py-2 px-3 text-right font-bold"><?= number_format($count) ?></td>
                                <td class="py-2 px-3 font-mono text-[10px] text-slate-500 break-all"><?= e(substr($h, 0, 24)) ?>...</td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </details>

        </div>
    </div>

    <!-- ============================================================
         ACTIONS
         ============================================================ -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <div class="font-semibold text-slate-800 text-sm">Aksi</div>
            <div class="text-xs text-slate-500 mt-0.5">
                <?php if ($overall === 'superseded'): ?>
                    Batch ini sudah tidak bisa diverifikasi (data raw sudah dihapus).
                <?php elseif ($overall === 'verified'): ?>
                    Data sudah terverifikasi. Tidak perlu tindakan lanjutan.
                <?php else: ?>
                    Verifikasi ulang atau tandai sebagai terverifikasi.
                <?php endif; ?>
            </div>
        </div>
        <div class="flex gap-2 flex-wrap">
            <?php if ($overall !== 'superseded'): ?>
            <button type="button" id="btnVerify" data-batch="<?= e($batch['batch_id']) ?>"
                    class="flex items-center gap-1.5 px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition-colors">
                <i class="ri-refresh-line"></i> Verifikasi Ulang
            </button>
            <?php endif; ?>

            <?php if ($overall === 'verified' && $batch['status'] !== 'verified'): ?>
                <button type="button" id="btnMarkVerified" data-batch="<?= e($batch['batch_id']) ?>"
                        class="flex items-center gap-1.5 px-4 py-2.5 bg-mint-500 hover:bg-mint-600 text-white text-xs font-bold rounded-xl transition-all shadow-md shadow-mint-500/20">
                    <i class="ri-shield-check-line"></i> Tandai Terverifikasi
                </button>
            <?php endif; ?>

            <?php if ($overall === 'failed'): ?>
                <a href="<?= base_url('upload') ?>"
                   class="flex items-center gap-1.5 px-4 py-2.5 bg-mint-500 hover:bg-mint-600 text-white text-xs font-bold rounded-xl transition-all shadow-md shadow-mint-500/20">
                    <i class="ri-upload-cloud-line"></i> Upload Ulang
                </a>
            <?php endif; ?>

            <?php if ($overall === 'not_generated'): ?>
                <a href="<?= base_url('generate') ?>"
                   class="flex items-center gap-1.5 px-4 py-2.5 bg-blue-500 hover:bg-blue-600 text-white text-xs font-bold rounded-xl transition-all shadow-md shadow-blue-500/20">
                    <i class="ri-magic-line"></i> Ke Preview Data
                </a>
            <?php endif; ?>
        </div>
    </div>

    <script>
    document.getElementById('btnVerify')?.addEventListener('click', function () {
        location.reload();
    });
    document.getElementById('btnMarkVerified')?.addEventListener('click', function () {
        const batch = this.dataset.batch;
        window.confirmAction({
            title: 'Tandai Terverifikasi?',
            text: 'Batch ini akan ditandai sebagai VERIFIED.',
            icon: 'question',
            confirmText: 'Ya, Tandai'
        }).then(ok => {
            if (!ok) return;
            NProgress.start();
            fetch(window.u('/actions/mark-verified'), {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': window.CSRF_TOKEN,
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'batch=' + encodeURIComponent(batch)
            })
            .then(r => r.json())
            .then(res => {
                NProgress.done();
                if (res.ok) {
                    window.showToast(res.msg, 'success');
                    setTimeout(() => location.reload(), 800);
                } else {
                    window.showToast(res.msg || 'Gagal', 'error');
                }
            })
            .catch(() => {
                NProgress.done();
                window.showToast('Kesalahan jaringan', 'error');
            });
        });
    });
    </script>

<?php
} else {
    /* ============================================================
       LIST SEMUA BATCH
       ============================================================ */
    $batches = $pdo->query("
        SELECT * FROM s_upload_batch
        ORDER BY uploaded_at DESC
        LIMIT 100
    ")->fetchAll();

    $stats = $pdo->query("
        SELECT
            COUNT(*) AS total_batch,
            SUM(status='verified') AS verified,
            SUM(status='generated') AS `generated`,
            SUM(status='uploaded') AS uploaded,
            SUM(status='mismatch' OR status='failed') AS problematic
        FROM s_upload_batch
    ")->fetch();
    ?>

    <!-- ============================================================
         PAGE HEADER
         ============================================================ -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 fade-in-up">
        <div>
            <h2 class="text-xl md:text-2xl font-bold text-slate-900 tracking-tight">Riwayat Upload & Verifikasi</h2>
            <p class="text-xs md:text-sm text-slate-500">
                Daftar semua batch upload beserta hash verifikasinya.
            </p>
        </div>
    </div>

    <!-- ============================================================
         STATISTIK
         ============================================================ -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
            <div class="text-[10px] text-slate-400 font-semibold uppercase tracking-wider">Total Batch</div>
            <div class="text-2xl font-bold text-slate-900 mt-1"><?= number_format((int)$stats['total_batch']) ?></div>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
            <div class="text-[10px] text-mint-600 font-semibold uppercase tracking-wider">Terverifikasi</div>
            <div class="text-2xl font-bold text-mint-600 mt-1"><?= number_format((int)$stats['verified']) ?></div>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
            <div class="text-[10px] text-blue-600 font-semibold uppercase tracking-wider">Generated</div>
            <div class="text-2xl font-bold text-blue-600 mt-1"><?= number_format((int)$stats['generated']) ?></div>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
            <div class="text-[10px] text-rose-600 font-semibold uppercase tracking-wider">Bermasalah</div>
            <div class="text-2xl font-bold text-rose-600 mt-1"><?= number_format((int)$stats['problematic']) ?></div>
        </div>
    </div>

    <!-- ============================================================
         TABLE RIWAYAT
         ============================================================ -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50/80 text-slate-500 uppercase tracking-wider font-semibold border-b border-slate-100">
                    <tr>
                        <th class="py-3 px-4">Batch ID</th>
                        <th class="py-3 px-4">Waktu</th>
                        <th class="py-3 px-4">Uploader</th>
                        <th class="py-3 px-4">File</th>
                        <th class="py-3 px-4 text-center">Baris</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-center">Verifikasi</th>
                        <th class="py-3 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium text-slate-600">
                <?php if (!$batches): ?>
                    <tr><td colspan="8" class="text-center py-8 text-slate-400">
                        <i class="ri-inbox-line text-3xl block mb-1"></i> Belum ada upload.
                    </td></tr>
                <?php else: foreach ($batches as $b):
                    $statusBadge = match ($b['status']) {
                        'verified'  => 'bg-mint-50 text-mint-700 border-mint-200/60',
                        'generated' => 'bg-blue-50 text-blue-700 border-blue-200/60',
                        'uploaded'  => 'bg-slate-50 text-slate-600 border-slate-200',
                        'mismatch'  => 'bg-rose-50 text-rose-700 border-rose-200/60',
                        'failed'    => 'bg-rose-50 text-rose-700 border-rose-200/60',
                        default     => 'bg-slate-50 text-slate-600 border-slate-200',
                    };
                ?>
                    <tr class="hover:bg-slate-50/80 transition-colors">
                        <td class="py-3 px-4">
                            <code class="text-mint-700 font-semibold text-[11px]"><?= e($b['batch_id']) ?></code>
                        </td>
                        <td class="py-3 px-4 text-slate-500 text-[11px] whitespace-nowrap">
                            <?= e(date('d/m/Y H:i', strtotime($b['uploaded_at']))) ?>
                        </td>
                        <td class="py-3 px-4">
                            <div class="font-semibold text-slate-800 text-[11px] truncate" style="max-width:160px">
                                <?= e($b['uploaded_by_nama'] ?: '-') ?>
                            </div>
                            <div class="text-[10px] text-slate-400"><?= e($b['uploaded_by_nrk'] ?: '-') ?></div>
                        </td>
                        <td class="py-3 px-4 text-slate-500 text-[11px] truncate" style="max-width:200px" title="<?= e($b['file_name']) ?>">
                            <?= e($b['file_name'] ?: '-') ?>
                        </td>
                        <td class="py-3 px-4 text-center font-bold text-slate-800">
                            <?= number_format((int)$b['imported_rows']) ?>
                        </td>
                        <td class="py-3 px-4 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold border <?= $statusBadge ?>">
                                <?= e(strtoupper($b['status'])) ?>
                            </span>
                        </td>
                        <td class="py-3 px-4 text-center">
                            <?php if ($b['verify_result'] === 'MATCH'): ?>
                                <span class="inline-flex items-center gap-1 text-mint-600 text-[10px] font-bold">
                                    <i class="ri-checkbox-circle-fill"></i> MATCH
                                </span>
                            <?php elseif ($b['verify_result'] === 'MISMATCH'): ?>
                                <span class="inline-flex items-center gap-1 text-rose-600 text-[10px] font-bold">
                                    <i class="ri-close-circle-fill"></i> MISMATCH
                                </span>
                            <?php else: ?>
                                <span class="text-slate-300 text-[10px]">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-4 text-right">
                            <a href="<?= base_url('verifikasi?batch=' . urlencode($b['batch_id'])) ?>"
                               class="inline-flex items-center gap-1 px-2.5 py-1.5 text-[11px] font-bold text-mint-700 bg-mint-50 hover:bg-mint-100 border border-mint-200 rounded-lg transition-colors">
                                <i class="ri-shield-check-line"></i> Detail
                            </a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php } ?>

<?php require BASE_PATH . '/layouts/footer.php'; ?>