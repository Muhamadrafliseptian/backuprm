<?php
/**
 * file   : backup_all.php
 * path   : C:\xampp\htdocs\backuprm\pages\backup_all.php
 * fungsi : Halaman backup seluruh isi database (semua tabel, semua data)
 *          Support export .sql (restore-ready) & .json
 */
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/internal_guard.php';

require BASE_PATH . '/layouts/header.php';
require BASE_PATH . '/layouts/sidebar.php';

$pdo = db();

/* ============================================================
   AMBIL DAFTAR SEMUA TABEL + JUMLAH BARIS
   ============================================================ */
$tables = [];

try {
    $rows = $pdo->query("
        SELECT TABLE_NAME AS tbl,
               TABLE_ROWS AS approx_rows,
               DATA_LENGTH + INDEX_LENGTH AS size_bytes
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_TYPE = 'BASE TABLE'
        ORDER BY TABLE_NAME ASC
    ")->fetchAll();

    foreach ($rows as $r) {
        // COUNT(*) akurat untuk tabel kecil
        try {
            $exact = (int)$pdo->query("SELECT COUNT(*) FROM `{$r['tbl']}`")->fetchColumn();
        } catch (Throwable $e) {
            $exact = (int)($r['approx_rows'] ?? 0);
        }
        $tables[] = [
            'name'       => $r['tbl'],
            'rows'       => $exact,
            'size_bytes' => (int)($r['size_bytes'] ?? 0),
        ];
    }
} catch (Throwable $e) {
    log_error('backup_all: gagal ambil daftar tabel — ' . $e->getMessage());
}

$totalRows = 0;
$totalSize = 0;
foreach ($tables as $t) {
    $totalRows += $t['rows'];
    $totalSize += $t['size_bytes'];
}

/* ============================================================
   INFO DATABASE
   ============================================================ */
$dbName    = env('DB_NAME', 'db_backuprm');
$dbVersion = '-';
try {
    $dbVersion = (string)$pdo->query('SELECT VERSION()')->fetchColumn();
} catch (Throwable $e) {}

$dbSizeFormatted = '-';
if ($totalSize >= 1073741824) {
    $dbSizeFormatted = round($totalSize / 1073741824, 2) . ' GB';
} elseif ($totalSize >= 1048576) {
    $dbSizeFormatted = round($totalSize / 1048576, 2) . ' MB';
} elseif ($totalSize >= 1024) {
    $dbSizeFormatted = round($totalSize / 1024, 2) . ' KB';
} else {
    $dbSizeFormatted = $totalSize . ' B';
}
?>

<!-- ============================================================
     PAGE HEADER
     ============================================================ -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 fade-in-up">
    <div>
        <h2 class="text-xl md:text-2xl font-bold text-slate-900 tracking-tight">Backup All Record DB</h2>
        <p class="text-xs md:text-sm text-slate-500">
            Download salinan lengkap seluruh isi database — semua tabel, semua kolom, semua baris.
        </p>
    </div>
</div>

<!-- ============================================================
     PERINGATAN
     ============================================================ -->
<div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 flex items-start gap-3">
    <i class="ri-alert-line text-amber-500 text-xl mt-0.5"></i>
    <div class="text-xs text-amber-800 leading-relaxed">
        <b>Perhatian:</b> File backup berisi <b>seluruh data</b> termasuk data pribadi pasien
        (NIK, alamat, telepon). Simpan file hasil download di tempat yang aman dan terenkripsi.
        Jangan kirim melalui channel yang tidak terproteksi.
    </div>
</div>

<!-- ============================================================
     INFO DATABASE
     ============================================================ -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Nama Database</p>
        <h3 class="text-base font-bold text-slate-900 mt-1 truncate" title="<?= e($dbName) ?>">
            <?= e($dbName) ?>
        </h3>
        <p class="text-[11px] text-slate-500 mt-1">Server: <?= e($dbVersion) ?></p>
    </div>
    <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total Tabel</p>
        <h3 class="text-2xl font-bold text-slate-900 mt-1"><?= number_format(count($tables)) ?></h3>
        <p class="text-[11px] text-slate-500 mt-1">Semua tabel</p>
    </div>
    <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total Baris</p>
        <h3 class="text-2xl font-bold text-slate-900 mt-1"><?= number_format($totalRows) ?></h3>
        <p class="text-[11px] text-slate-500 mt-1">Semua record</p>
    </div>
    <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Estimasi Ukuran</p>
        <h3 class="text-2xl font-bold text-slate-900 mt-1"><?= e($dbSizeFormatted) ?></h3>
        <p class="text-[11px] text-slate-500 mt-1">Data + index</p>
    </div>
</div>

<!-- ============================================================
     FORM DOWNLOAD
     ============================================================ -->
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <div class="p-5 border-b border-slate-100">
        <h3 class="font-bold text-slate-900 text-base flex items-center gap-2">
            <i class="ri-download-cloud-2-line text-mint-600"></i> Download Backup
        </h3>
    </div>
    <div class="p-5">
        <form id="formBackup" class="space-y-4">
            <?= csrf_field() ?>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-2">Format File</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <label class="cursor-pointer border-2 border-mint-200 bg-mint-50/30 hover:bg-mint-50 rounded-xl p-4 transition-colors flex items-start gap-3" id="cardSql">
                        <input type="radio" name="format" value="sql" checked class="mt-1 accent-mint-500">
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <i class="ri-database-2-line text-mint-600 text-lg"></i>
                                <b class="text-sm text-slate-800">.SQL (Restore-Ready)</b>
                            </div>
                            <p class="text-[11px] text-slate-500 mt-1 leading-relaxed">
                                File SQL siap di-import kembali ke MySQL/MariaDB
                                via phpMyAdmin atau command line.
                            </p>
                        </div>
                    </label>
                    <label class="cursor-pointer border-2 border-slate-200 bg-slate-50/30 hover:bg-slate-50 rounded-xl p-4 transition-colors flex items-start gap-3" id="cardJson">
                        <input type="radio" name="format" value="json" class="mt-1 accent-mint-500">
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <i class="ri-code-s-slash-line text-slate-600 text-lg"></i>
                                <b class="text-sm text-slate-800">.JSON (Arsip Terbaca)</b>
                            </div>
                            <p class="text-[11px] text-slate-500 mt-1 leading-relaxed">
                                File JSON dengan struktur terpisah per tabel.
                                Cocok untuk arsip, audit, atau migrasi.
                            </p>
                        </div>
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Mode</label>
                <select name="mode" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:bg-white focus:border-mint-500 focus:ring-2 focus:ring-mint-100 focus:outline-none transition-all">
                    <option value="full">Full — semua tabel, semua baris</option>
                    <option value="structure">Struktur saja (tanpa data)</option>
                    <option value="data">Data saja (tanpa CREATE TABLE)</option>
                </select>
                <p class="text-[11px] text-slate-400 mt-1">
                    Default: <b>Full</b> — semua data + struktur.
                </p>
            </div>

            <div class="flex flex-col sm:flex-row gap-2 pt-2">
                <button type="submit" id="btnDownload"
                        class="flex-1 bg-mint-500 hover:bg-mint-600 text-white text-sm font-bold px-6 py-3 rounded-xl transition-all shadow-md shadow-mint-500/20 active:scale-95">
                    <i class="ri-download-2-line mr-1"></i> Download Backup
                </button>
                <button type="button" id="btnPreview"
                        class="sm:w-auto bg-white hover:bg-slate-50 text-mint-700 border border-mint-300 text-sm font-bold px-6 py-3 rounded-xl transition-colors">
                    <i class="ri-eye-line mr-1"></i> Pratinjau
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================
     DAFTAR TABEL (maks 10 baris, tombol "Lihat Semua")
     ============================================================ -->
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <div class="p-5 border-b border-slate-100 flex items-center justify-between flex-wrap gap-2">
        <div class="flex items-center gap-2">
            <i class="ri-table-line text-mint-600 text-lg"></i>
            <h3 class="font-bold text-slate-900 text-base">Daftar Tabel</h3>
        </div>
        <span class="text-[11px] text-slate-500">
            Total <b><?= number_format($totalRows) ?></b> baris dari <b><?= count($tables) ?></b> tabel
        </span>
    </div>

    <?php if (!$tables): ?>
        <div class="p-10 text-center text-slate-400">
            <i class="ri-inbox-line text-3xl block mb-1"></i> Tidak ada tabel.
        </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead class="bg-slate-50/95 text-slate-500 uppercase tracking-wider font-semibold border-b border-slate-100">
                <tr>
                    <th class="py-3 px-4" style="width:50px">#</th>
                    <th class="py-3 px-4">Nama Tabel</th>
                    <th class="py-3 px-4 text-right" style="width:120px">Jumlah Baris</th>
                    <th class="py-3 px-4 text-right" style="width:120px">Ukuran</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-medium text-slate-600">
            <?php
            $i = 1;
            foreach ($tables as $idx => $t):
                $sz = $t['size_bytes'];
                if ($sz >= 1048576)      $szStr = round($sz / 1048576, 2) . ' MB';
                elseif ($sz >= 1024)     $szStr = round($sz / 1024, 2) . ' KB';
                else                     $szStr = $sz . ' B';

                // Baris ke-11 dan seterusnya disembunyikan
                $hidden = $idx >= 10 ? 'style="display:none" class="backup-table-row hidden-row hover:bg-slate-50/80 transition-colors"' : 'class="backup-table-row hover:bg-slate-50/80 transition-colors"';
            ?>
                <tr <?= $hidden ?>>
                    <td class="py-2.5 px-4 text-slate-400"><?= $i++ ?></td>
                    <td class="py-2.5 px-4">
                        <code class="text-mint-700 font-semibold"><?= e($t['name']) ?></code>
                    </td>
                    <td class="py-2.5 px-4 text-right font-bold text-slate-800">
                        <?= number_format($t['rows']) ?>
                    </td>
                    <td class="py-2.5 px-4 text-right text-slate-500">
                        <?= e($szStr) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if (count($tables) > 10): ?>
    <div class="p-4 border-t border-slate-100 flex items-center justify-between flex-wrap gap-2">
        <span class="text-[11px] text-slate-500">
            Menampilkan <b><?= min(10, count($tables)) ?></b> dari <b><?= count($tables) ?></b> tabel
        </span>
        <button type="button" id="btnToggleTable" data-expanded="0"
                class="inline-flex items-center gap-1.5 px-3 py-2 bg-mint-50 hover:bg-mint-100 text-mint-700 border border-mint-200 text-xs font-bold rounded-xl transition-colors">
            <i class="ri-arrow-down-s-line" id="btnToggleIcon"></i>
            <span id="btnToggleText">Lihat Semua (<?= count($tables) - 10 ?> lagi)</span>
        </button>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php require BASE_PATH . '/layouts/footer.php'; ?>

<!-- ============================================================
     SCRIPT
     ============================================================ -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    /* ---------- Highlight card yang dipilih ---------- */
    const cardSql  = document.getElementById('cardSql');
    const cardJson = document.getElementById('cardJson');

    function updateCardHighlight() {
        const val = document.querySelector('input[name="format"]:checked').value;
        if (val === 'sql') {
            cardSql.classList.add('border-mint-500');
            cardSql.classList.remove('border-mint-200');
            cardJson.classList.remove('border-mint-500');
            cardJson.classList.add('border-slate-200');
        } else {
            cardJson.classList.add('border-mint-500');
            cardJson.classList.remove('border-slate-200');
            cardSql.classList.remove('border-mint-500');
            cardSql.classList.add('border-mint-200');
        }
    }

    document.querySelectorAll('input[name="format"]').forEach(r => {
        r.addEventListener('change', updateCardHighlight);
    });
    updateCardHighlight();

    /* ---------- Submit form → download ---------- */
    document.getElementById('formBackup').addEventListener('submit', function (e) {
        e.preventDefault();

        const format = document.querySelector('input[name="format"]:checked').value;
        const mode   = document.querySelector('select[name="mode"]').value;
        const token  = document.querySelector('input[name="_token"]').value;

        Swal.fire({
            title: 'Menyiapkan backup...',
            html: 'Mohon tunggu, sedang menyusun file <b>' + format.toUpperCase() + '</b>.',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => Swal.showLoading()
        });

        /* Kirim request POST via fetch → dapatkan blob → trigger download */
        const params = new URLSearchParams();
        params.append('_token', token);
        params.append('format', format);
        params.append('mode', mode);

        fetch(window.u('/actions/download-backup'), {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': window.CSRF_TOKEN,
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: params
        })
        .then(async (res) => {
            if (!res.ok) {
                const text = await res.text();
                throw new Error(text || 'Server error ' + res.status);
            }

            /* ---------- Ambil filename dari header ---------- */
            const disposition = res.headers.get('Content-Disposition') || '';
            let filename = 'backup_' + Date.now() + '.' + format;
            const match = disposition.match(/filename="?([^";\n]+)"?/);
            if (match && match[1]) {
                filename = match[1];
            }

            /* ---------- Baca blob & trigger download ---------- */
            const blob = await res.blob();
            const url  = window.URL.createObjectURL(blob);
            const a    = document.createElement('a');
            a.href     = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);

            Swal.close();
            Swal.fire({
                icon: 'success',
                title: 'Backup Berhasil',
                html:
                    '<div style="font-size:13px">' +
                        'File <b>' + filename + '</b> sudah terdownload.<br>' +
                        '<span style="font-size:11px;color:#64748b">' +
                            'Ukuran: ' + formatBytes(blob.size) +
                        '</span>' +
                    '</div>',
                confirmButtonColor: '#10b981',
                confirmButtonText: 'OK'
            });
        })
        .catch(err => {
            Swal.close();
            Swal.fire({
                icon: 'error',
                title: 'Gagal Membuat Backup',
                html: '<pre style="text-align:left;font-size:11px;max-height:300px;overflow:auto">' +
                      escapeHtml(String(err).substring(0, 2000)) + '</pre>',
                confirmButtonColor: '#10b981',
                width: 700
            });
        });
    });

    /* ---------- Tombol Pratinjau ---------- */
    document.getElementById('btnPreview').addEventListener('click', function () {
        const format = document.querySelector('input[name="format"]:checked').value;

        Swal.fire({
            title: 'Pratinjau Backup',
            html:
                '<div style="text-align:left;font-size:12px;line-height:1.7">' +
                    '<b>Nama Database:</b> ' + <?= json_encode($dbName) ?> + '<br>' +
                    '<b>Format:</b> ' + format.toUpperCase() + '<br>' +
                    '<b>Jumlah Tabel:</b> ' + <?= json_encode(count($tables)) ?> + '<br>' +
                    '<b>Total Baris:</b> ' + <?= json_encode(number_format($totalRows)) ?> + '<br>' +
                    '<b>Estimasi Ukuran:</b> ' + <?= json_encode($dbSizeFormatted) ?> + '<br><br>' +
                    '<small style="color:#64748b">Klik <b>Download Backup</b> untuk mengunduh file.</small>' +
                '</div>',
            icon: 'info',
            confirmButtonColor: '#10b981',
            confirmButtonText: 'OK'
        });
    });

    /* ---------- Helpers ---------- */
    function formatBytes(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(2) + ' KB';
        if (bytes < 1073741824) return (bytes / 1048576).toFixed(2) + ' MB';
        return (bytes / 1073741824).toFixed(2) + ' GB';
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, m => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[m]));
    }
	
	/* ---------- Toggle Lihat Semua tabel ---------- */
const btnToggle = document.getElementById('btnToggleTable');
if (btnToggle) {
    btnToggle.addEventListener('click', function () {
        const isExpanded = this.dataset.expanded === '1';
        const icon = document.getElementById('btnToggleIcon');
        const text = document.getElementById('btnToggleText');

        document.querySelectorAll('.hidden-row').forEach(function (row) {
            if (isExpanded) {
                row.style.display = 'none';
            } else {
                row.style.display = '';
            }
        });

        if (isExpanded) {
            this.dataset.expanded = '0';
            icon.className = 'ri-arrow-down-s-line';
            text.textContent = 'Lihat Semua (<?= count($tables) - 10 ?> lagi)';
        } else {
            this.dataset.expanded = '1';
            icon.className = 'ri-arrow-up-s-line';
            text.textContent = 'Sembunyikan';
        }
    });
}
	
});
</script>