<?php
/**
 * file   : pages/upload.php
 * path   : C:\xampp\htdocs\backuprm\pages\upload.php
 * fungsi : Upload file ZIP backup (berisi .sql) sekaligus untuk bypass WAF Chunk + preview + clear
 */
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/internal_guard.php';

require BASE_PATH . '/layouts/header.php';
require BASE_PATH . '/layouts/sidebar.php';

$pdo = db();

$countRaw = (int)$pdo->query("SELECT COUNT(*) FROM s_backup_raw")->fetchColumn();

/* Riwayat 10 batch terakhir */
$recentBatches = [];
try {
    $recentBatches = $pdo->query("
        SELECT batch_id, uploaded_at, uploaded_by_nama,
               file_name, imported_rows, status
        FROM s_upload_batch
        ORDER BY uploaded_at DESC
        LIMIT 10
    ")->fetchAll();
} catch (Throwable $e) {
    $recentBatches = [];
}
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 fade-in-up">
    <div>
        <h2 class="text-xl md:text-2xl font-bold text-slate-900 tracking-tight">Upload Backup</h2>
        <p class="text-xs md:text-sm text-slate-500">
            Upload file arsip <code class="text-mint-600 bg-mint-50 px-1.5 py-0.5 rounded">.zip</code> ukuran besar dengan aman (Bypass WAF).
        </p>
    </div>
</div>

<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mt-4">
    <div class="p-5 border-b border-slate-100 flex items-center justify-between flex-wrap gap-2">
        <h3 class="font-bold text-slate-900 text-base flex items-center gap-2">
            <i class="ri-upload-cloud-line text-mint-600"></i> Upload File Backup (.ZIP)
        </h3>
        <?php if ($countRaw > 0): ?>
        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
            <i class="ri-alert-line"></i> Ada <?= number_format($countRaw) ?> baris belum disimpan
        </span>
        <?php endif; ?>
    </div>
    <div class="p-5">
        <form id="formUpload" class="space-y-4">
            <?= csrf_field() ?>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Pilih File Backup (.zip)</label>
                <input type="file" name="backup_zip" id="backup_zip" accept=".zip" required
                       class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-700 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-mint-100 file:text-mint-700 file:text-xs file:font-semibold hover:file:bg-mint-200 focus:bg-white focus:border-mint-500 focus:ring-2 focus:ring-mint-100 focus:outline-none transition-all">
                <p class="text-[11px] text-slate-400 mt-1">
                    Kompres file SQL Anda ke format .zip terlebih dahulu agar lolos dari sensor WAF server.
                </p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Mode Import</label>
                <select name="mode" id="mode" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-800 focus:bg-white focus:border-mint-500 focus:ring-2 focus:ring-mint-100 focus:outline-none transition-all">
                    <option value="replace">Ganti (kosongkan s_backup_raw dulu)</option>
                    <option value="append">Tambah (append ke data yang ada)</option>
                </select>
            </div>

            <!-- Progress Bar Sederhana -->
            <div id="progressWrapper" class="hidden space-y-1">
                <div class="flex justify-between text-xs text-slate-600 font-semibold">
                    <span id="progressText">Mengunggah file ZIP ke server...</span>
                    <span id="progressPercent">Proses</span>
                </div>
                <div class="w-full bg-slate-100 rounded-full h-2.5 overflow-hidden">
                    <div id="progressBar" class="bg-mint-500 h-2.5 rounded-full transition-all duration-300" style="width: 30%"></div>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-2 pt-2">
                <button type="submit" id="btnUpload"
                        class="flex-1 bg-mint-500 hover:bg-mint-600 text-white text-xs font-bold px-4 py-3 rounded-xl transition-all shadow-md shadow-mint-500/20 active:scale-95">
                    <i class="ri-upload-cloud-line mr-1"></i> Upload &amp; Proses ZIP
                </button>
                <?php if ($countRaw > 0): ?>
                <button type="button" id="btnPreview"
                        class="flex-1 bg-white hover:bg-slate-50 text-mint-700 border border-mint-300 text-xs font-bold px-4 py-3 rounded-xl transition-colors">
                    <i class="ri-magic-line mr-1"></i> Preview Data
                </button>
                <button type="button" id="btnClearBackup"
                        class="bg-white hover:bg-rose-50 text-rose-600 border border-rose-200 text-xs font-bold px-4 py-3 rounded-xl transition-colors">
                    <i class="ri-delete-bin-line mr-1"></i> Kosongkan
                </button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Bagian Tabel Riwayat Upload -->
<?php if ($recentBatches): ?>
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mt-6">
    <div class="p-5 border-b border-slate-100 flex items-center justify-between flex-wrap gap-2">
        <div class="flex items-center gap-2">
            <i class="ri-history-line text-mint-600 text-lg"></i>
            <h3 class="font-bold text-slate-900 text-base">Riwayat Upload</h3>
        </div>
        <a href="<?= base_url('verifikasi') ?>"
           class="text-[11px] font-semibold text-mint-600 hover:text-mint-700 flex items-center gap-1">
            Lihat Semua <i class="ri-arrow-right-line"></i>
        </a>
    </div>
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
                    <th class="py-3 px-4 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-medium text-slate-600">
            <?php foreach ($recentBatches as $b):
                if (in_array($b['status'], ['generated', 'verified'], true)) {
                    $rowClass    = 'bg-mint-50/20';
                    $statusBadge = 'bg-mint-50 text-mint-700 border-mint-200/60';
                    $statusText  = 'SUDAH DI-UPLOAD';
                    $statusIcon  = 'ri-checkbox-circle-fill';
                    $actionHtml  = '<a href="' . base_url('verifikasi?batch=' . urlencode($b['batch_id'])) . '" class="inline-flex items-center gap-1 px-2.5 py-1.5 text-[10px] font-bold text-mint-700 bg-mint-50 hover:bg-mint-100 border border-mint-200 rounded-lg transition-colors"><i class="ri-shield-check-line"></i> Verifikasi</a>';
                } elseif ($b['status'] === 'uploaded') {
                    $rowClass    = 'bg-amber-50/30';
                    $statusBadge = 'bg-amber-50 text-amber-700 border-amber-200/60';
                    $statusText  = 'BELUM DI-UPLOAD';
                    $statusIcon  = 'ri-time-line';
                    $actionHtml  = '<a href="' . base_url('generate') . '" class="inline-flex items-center gap-1 px-2.5 py-1.5 text-[10px] font-bold text-amber-700 bg-amber-50 hover:bg-amber-100 border border-amber-200 rounded-lg transition-colors"><i class="ri-magic-line"></i> Preview &amp; Simpan</a>';
                } else {
                    $rowClass    = '';
                    $statusBadge = 'bg-slate-50 text-slate-600 border-slate-200';
                    $statusText  = strtoupper($b['status']);
                    $statusIcon  = 'ri-information-line';
                    $actionHtml  = '<a href="' . base_url('verifikasi?batch=' . urlencode($b['batch_id'])) . '" class="inline-flex items-center gap-1 px-2.5 py-1.5 text-[10px] font-bold text-slate-600 bg-slate-50 hover:bg-slate-100 border border-slate-200 rounded-lg transition-colors"><i class="ri-information-line"></i> Detail</a>';
                }
            ?>
                <tr class="hover:bg-slate-50/80 transition-colors <?= $rowClass ?>">
                    <td class="py-3 px-4"><code class="text-mint-700 font-semibold text-[10px]"><?= e($b['batch_id']) ?></code></td>
                    <td class="py-3 px-4 text-slate-500 text-[11px] whitespace-nowrap"><?= e(date('d/m/Y H:i', strtotime($b['uploaded_at']))) ?></td>
                    <td class="py-3 px-4 text-slate-700 text-[11px] truncate" style="max-width:160px"><?= e($b['uploaded_by_nama'] ?: '-') ?></td>
                    <td class="py-3 px-4 text-slate-500 text-[11px] truncate" style="max-width:200px" title="<?= e($b['file_name']) ?>"><?= e($b['file_name'] ?: '-') ?></td>
                    <td class="py-3 px-4 text-center font-bold text-slate-800 text-[11px]"><?= number_format((int)$b['imported_rows']) ?></td>
                    <td class="py-3 px-4 text-center">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold border <?= $statusBadge ?>">
                            <i class="<?= $statusIcon ?>"></i> <?= e($statusText) ?>
                        </span>
                    </td>
                    <td class="py-3 px-4 text-right"><?= $actionHtml ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require BASE_PATH . '/layouts/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, m => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[m]));
    }

    function showError(title, text) {
        Swal.fire({
            icon: 'error',
            title: title,
            html: '<pre style="text-align:left;font-size:11px;max-height:300px;overflow:auto;background:#f5f5f5;padding:10px">' +
                  escapeHtml(String(text).substring(0, 2000)) + '</pre>',
            confirmButtonColor: '#10b981',
            width: 700
        });
    }

    /* ============================================================
       SINGLE ZIP UPLOAD LOGIC (BYPASS WAF)
       ============================================================ */
    const formUpload = document.getElementById('formUpload');
    if (formUpload) {
        formUpload.addEventListener('submit', async function (e) {
            e.preventDefault();

            const fileInput = document.getElementById('backup_zip');
            if (fileInput.files.length === 0) return;

            const file = fileInput.files[0];
            const mode = document.getElementById('mode').value;

            const progressWrapper = document.getElementById('progressWrapper');
            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('progressText');
            const progressPercent = document.getElementById('progressPercent');
            const btnUpload = document.getElementById('btnUpload');

            progressWrapper.classList.remove('hidden');
            btnUpload.disabled = true;
            btnUpload.innerHTML = '<i class="ri-loader-4-line animate-spin mr-1"></i> Mengunggah &amp; Memproses ZIP...';
            progressText.innerText = 'Mengunggah file ZIP ke server... (Mohon jangan tutup halaman)';
            progressBar.style.width = '60%';
            progressPercent.innerText = 'Sedang Proses';

            const fd = new FormData();
            fd.append('backup_zip', file);
            fd.append('mode', mode);

            try {
                const response = await fetch(window.u('/actions/upload-zip'), {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': window.CSRF_TOKEN
                    },
                    body: fd
                });

                const text = await response.text();
                let json;
                try {
                    json = JSON.parse(text);
                } catch (err) {
                    throw new Error('Response bukan JSON: ' + text);
                }

                if (!json.ok) {
                    throw new Error(json.msg || 'Gagal memproses file ZIP.');
                }

                progressBar.style.width = '100%';
                progressPercent.innerText = '100%';

                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil Upload & Ekstrak',
                    text: json.msg,
                    confirmButtonColor: '#10b981'
                }).then(() => location.reload());

            } catch (err) {
                btnUpload.disabled = false;
                btnUpload.innerHTML = '<i class="ri-upload-cloud-line mr-1"></i> Upload &amp; Proses ZIP';
                progressWrapper.classList.add('hidden');
                showError('Gagal Upload / Proses', err.toString());
            }
        });
    }

    // Tombol Preview Data
    const btnPreview = document.getElementById('btnPreview');
    if (btnPreview) {
        btnPreview.addEventListener('click', function () {
            NProgress.start();
            fetch(window.u('/actions/preview-data'), {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': window.CSRF_TOKEN,
                    'Content-Type': 'application/x-www-form-urlencoded'
                }
            })
            .then(async (r) => {
                NProgress.done();
                const text = await r.text();
                let json = null;
                try { json = JSON.parse(text); }
                catch (err) { return showError('Response bukan JSON', text); }

                if (json.ok) {
                    sessionStorage.setItem('preview_data', JSON.stringify(json));
                    sessionStorage.setItem('preview_data_ts', Date.now().toString());
                    window.location.href = window.u('generate');
                } else {
                    showError('Preview Gagal', json.msg || 'Terjadi kesalahan.');
                }
            })
            .catch((err) => {
                NProgress.done();
                showError('Fetch Error', err.toString());
            });
        });
    }

    // Tombol Kosongkan Backup Raw
    const btnClear = document.getElementById('btnClearBackup');
    if (btnClear) {
        btnClear.addEventListener('click', function () {
            Swal.fire({
                icon: 'warning',
                title: 'Kosongkan s_backup_raw?',
                showCancelButton: true,
                confirmButtonText: 'Ya, Kosongkan',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#E63946',
                cancelButtonColor: '#7A8793',
                reverseButtons: true
            }).then(r => {
                if (!r.isConfirmed) return;
                NProgress.start();
                fetch(window.u('/actions/clear-backup'), {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': window.CSRF_TOKEN,
                        'Content-Type': 'application/x-www-form-urlencoded'
                    }
                })
                .then(async (res) => {
                    NProgress.done();
                    const text = await res.text();
                    let json = null;
                    try { json = JSON.parse(text); }
                    catch (err) { return showError('Response bukan JSON', text); }

                    if (json.ok) {
                        sessionStorage.removeItem('preview_data');
                        sessionStorage.removeItem('preview_data_ts');
                        Swal.fire({ icon: 'success', title: 'Berhasil', text: json.msg, confirmButtonColor: '#10b981' })
                        .then(() => location.reload());
                    } else {
                        showError('Gagal', json.msg);
                    }
                })
                .catch((err) => {
                    NProgress.done();
                    showError('Fetch Error', err.toString());
                });
            });
        });
    }
});
</script>