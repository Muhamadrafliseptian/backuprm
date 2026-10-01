<?php
/**
 * file   : about.php
 * path   : C:\xampp\htdocs\backuprm\pages\about.php
 * fungsi : Halaman tentang aplikasi + tombol Reset (verifikasi password)
 */
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/internal_guard.php';

require BASE_PATH . '/layouts/header.php';
require BASE_PATH . '/layouts/sidebar.php';

$tables = [
    'm_pasien'           => 'Pasien',
    'm_obat'             => 'Obat',
    'm_tenaga_kesehatan' => 'Tenaga Kesehatan',
    'm_pegawai'          => 'Pegawai',
    't_kunjungan'        => 'Kunjungan',
    't_pemeriksaan'      => 'Pemeriksaan',
    't_diagnosis'        => 'Diagnosis',
    't_tindakan'         => 'Tindakan',
    't_resep'            => 'Resep',
    'd_resep_detail'     => 'Detail Resep',
    't_laboratorium'     => 'Laboratorium',
    's_backup_raw'       => 'Backup Raw (staging)',
    'l_activity'         => 'Log Aktivitas',
];

$counts = [];
foreach ($tables as $t => $label) {
    try { $counts[$t] = (int)db()->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn(); }
    catch (Throwable $e) { $counts[$t] = null; }
}

$dbVersion = db()->query('SELECT VERSION()')->fetchColumn();

/* Total baris transaksi + staging (untuk info tombol reset) */
$resetTables = [
    'd_resep_detail','t_resep','t_tindakan','t_diagnosis','t_pemeriksaan',
    't_laboratorium','t_kunjungan','m_pasien','m_obat','m_tenaga_kesehatan','s_backup_raw'
];
$totalResetRows = 0;
foreach ($resetTables as $rt) {
    $totalResetRows += $counts[$rt] ?? 0;
}
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 fade-in-up">
    <div>
        <h2 class="text-xl md:text-2xl font-bold text-slate-900 tracking-tight">Tentang Aplikasi</h2>
        <p class="text-xs md:text-sm text-slate-500">Informasi sistem & statistik database.</p>
    </div>
    <button type="button" id="btnResetAll"
            class="flex items-center gap-1.5 px-4 py-2.5 bg-rose-500 hover:bg-rose-600 text-white rounded-xl text-xs font-bold transition-all shadow-md shadow-rose-500/20 active:scale-95">
        <i class="ri-delete-bin-7-line"></i>
        <span>Reset Data</span>
    </button>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
    <!-- Info Aplikasi -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm">
        <div class="p-5 border-b border-slate-100 flex items-center gap-2">
            <i class="ri-server-line text-mint-600 text-lg"></i>
            <h3 class="font-bold text-slate-900 text-base">Informasi Aplikasi</h3>
        </div>
        <div class="p-5">
            <table class="w-full text-xs">
                <tbody class="divide-y divide-slate-100">
                    <tr><td class="py-2.5 text-slate-400 font-medium w-40">Nama Aplikasi</td><td class="py-2.5 font-bold text-slate-800"><?= e(APP_NAME) ?></td></tr>
                    <tr><td class="py-2.5 text-slate-400 font-medium">Versi</td><td class="py-2.5 font-semibold text-slate-700"><?= e(APP_VERSION) ?></td></tr>
                    <tr><td class="py-2.5 text-slate-400 font-medium">Timezone</td><td class="py-2.5 text-slate-700"><?= e(env('APP_TIMEZONE', 'Asia/Jakarta')) ?></td></tr>
                    <tr><td class="py-2.5 text-slate-400 font-medium">Mode</td><td class="py-2.5">
                        <?php if (env('APP_DEBUG') === 'true'): ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-amber-50 text-amber-600 border border-amber-200/60">DEBUG</span>
                        <?php else: ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-mint-50 text-mint-700 border border-mint-200/60">PRODUCTION</span>
                        <?php endif; ?>
                    </td></tr>
                    <tr><td class="py-2.5 text-slate-400 font-medium">Base URL</td><td class="py-2.5"><code class="text-mint-700 text-[11px]"><?= e(BASE_URL) ?></code></td></tr>
                    <tr><td class="py-2.5 text-slate-400 font-medium">Session Lifetime</td><td class="py-2.5 text-slate-700"><?= number_format(SESSION_LIFETIME) ?> detik</td></tr>
                    <tr><td class="py-2.5 text-slate-400 font-medium">Max Upload</td><td class="py-2.5 text-slate-700"><?= round(UPLOAD_MAX_SIZE / 1048576, 0) ?> MB</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Info Server -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm">
        <div class="p-5 border-b border-slate-100 flex items-center gap-2">
            <i class="ri-database-2-line text-mint-600 text-lg"></i>
            <h3 class="font-bold text-slate-900 text-base">Informasi Server</h3>
        </div>
        <div class="p-5">
            <table class="w-full text-xs">
                <tbody class="divide-y divide-slate-100">
                    <tr><td class="py-2.5 text-slate-400 font-medium w-40">PHP Version</td><td class="py-2.5 font-semibold text-slate-700"><?= e(PHP_VERSION) ?></td></tr>
                    <tr><td class="py-2.5 text-slate-400 font-medium">Database</td><td class="py-2.5 text-slate-700">MariaDB / MySQL <?= e($dbVersion) ?></td></tr>
                    <tr><td class="py-2.5 text-slate-400 font-medium">Charset</td><td class="py-2.5 text-slate-700"><?= e(env('DB_CHARSET', 'utf8mb4')) ?></td></tr>
                    <tr><td class="py-2.5 text-slate-400 font-medium">Server Software</td><td class="py-2.5 text-slate-700"><?= e($_SERVER['SERVER_SOFTWARE'] ?? '-') ?></td></tr>
                    <tr><td class="py-2.5 text-slate-400 font-medium">Server Time</td><td class="py-2.5 text-slate-700"><?= e(date('d/m/Y H:i:s')) ?></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Statistik Tabel -->
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <div class="p-5 border-b border-slate-100 flex items-center gap-2">
        <i class="ri-table-line text-mint-600 text-lg"></i>
        <h3 class="font-bold text-slate-900 text-base">Statistik Tabel</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead class="bg-slate-50/80 text-slate-500 uppercase tracking-wider font-semibold border-b border-slate-100">
                <tr>
                    <th class="py-3.5 px-5" style="width:60px">#</th>
                    <th class="py-3.5 px-5">Nama Tabel</th>
                    <th class="py-3.5 px-5">Keterangan</th>
                    <th class="py-3.5 px-5 text-right" style="width:150px">Jumlah Baris</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-medium text-slate-600">
            <?php $i = 1; foreach ($tables as $t => $label): ?>
                <tr class="hover:bg-slate-50/80 transition-colors">
                    <td class="py-3 px-5 text-slate-400"><?= $i++ ?></td>
                    <td class="py-3 px-5"><code class="text-mint-700 font-semibold"><?= e($t) ?></code></td>
                    <td class="py-3 px-5 text-slate-500"><?= e($label) ?></td>
                    <td class="py-3 px-5 text-right">
                        <?php if ($counts[$t] === null): ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-rose-50 text-rose-600 border border-rose-200/60">error</span>
                        <?php else: ?>
                            <b class="text-slate-800"><?= number_format($counts[$t]) ?></b>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require BASE_PATH . '/layouts/footer.php'; ?>

<!-- ============================================================
     SCRIPT: TOMBOL RESET
     ============================================================ -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('btnResetAll');
    if (!btn) return;

    const TOTAL_ROWS = <?= (int)$totalResetRows ?>;

    btn.addEventListener('click', function () {
        Swal.fire({
            icon: 'warning',
            title: 'Reset Seluruh Data?',
            html:
                '<div style="text-align:left;font-size:13px;line-height:1.7">' +
                'Aksi ini akan <b style="color:#dc2626">MENGHAPUS PERMANEN</b> seluruh data:<br>' +
                '<ul style="margin:8px 0 12px 20px;padding:0">' +
                '<li>Semua transaksi (kunjungan, pemeriksaan, diagnosis, tindakan)</li>' +
                '<li>Semua resep & detail resep</li>' +
                '<li>Semua data laboratorium</li>' +
                '<li>Semua pasien, obat, tenaga kesehatan</li>' +
                '<li>Data staging <code>s_backup_raw</code></li>' +
                '</ul>' +
                'Total <b>' + TOTAL_ROWS.toLocaleString('id-ID') + '</b> baris akan dihapus.<br><br>' +
                '<label style="display:block;margin-bottom:4px;font-weight:600">Ketik <code style="background:#fee2e2;color:#991b1b;padding:2px 6px;border-radius:4px">RESET</code> untuk konfirmasi:</label>' +
                '<input id="swalConfirm" class="swal2-input" placeholder="Ketik RESET" style="margin:0 0 12px 0;width:100%;font-size:13px">' +
                '<label style="display:block;margin-bottom:4px;font-weight:600">Password NRK 8813084:</label>' +
                '<input id="swalPassword" type="password" class="swal2-input" placeholder="Password" style="margin:0;width:100%;font-size:13px">' +
                '</div>',
            showCancelButton: true,
            confirmButtonText: '<i class="ri-delete-bin-7-line"></i> Ya, Reset Sekarang',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#64748b',
            reverseButtons: true,
            focusConfirm: false,
            allowOutsideClick: false,
            didOpen: () => {
                const input = document.getElementById('swalConfirm');
                if (input) input.focus();
            },
            preConfirm: () => {
                const confirmVal  = document.getElementById('swalConfirm').value.trim();
                const passwordVal = document.getElementById('swalPassword').value;

                if (confirmVal !== 'RESET') {
                    Swal.showValidationMessage('Ketik "RESET" (huruf besar) untuk konfirmasi.');
                    return false;
                }
                if (!passwordVal) {
                    Swal.showValidationMessage('Password wajib diisi.');
                    return false;
                }
                return { confirm: confirmVal, password: passwordVal };
            }
        }).then(function (result) {
            if (!result.isConfirmed || !result.value) return;

            /* ---------- Loading ---------- */
            Swal.fire({
                title: 'Menghapus data...',
                html: 'Mohon tunggu, jangan tutup halaman ini.',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => Swal.showLoading()
            });

            /* ---------- Kirim request ---------- */
            const fd = new FormData();
            fd.append('_token', window.CSRF_TOKEN);
            fd.append('password', result.value.password);
            fd.append('confirm', result.value.confirm);

            fetch(window.u('/actions/reset-all'), {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': window.CSRF_TOKEN
                },
                body: fd
            })
            .then(async (res) => {
                const text = await res.text();
                let json = null;
                try { json = JSON.parse(text); }
                catch (e) {
                    return Swal.fire({
                        icon: 'error',
                        title: 'Response bukan JSON',
                        html: '<pre style="text-align:left;font-size:11px;max-height:300px;overflow:auto">' +
                              String(text).substring(0, 2000)
                                .replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])) +
                              '</pre>',
                        width: 700,
                        confirmButtonColor: '#10b981'
                    });
                }

                if (json.ok) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Reset Berhasil!',
                        html: 'Total <b>' + (json.total || 0).toLocaleString('id-ID') + '</b> baris dihapus dari <b>' +
                              Object.keys(json.affected || {}).length + '</b> tabel.',
                        confirmButtonColor: '#10b981',
                        confirmButtonText: 'Muat Ulang Halaman'
                    }).then(() => location.reload());
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Reset Gagal',
                        text: json.msg || 'Terjadi kesalahan.',
                        confirmButtonColor: '#10b981'
                    });
                }
            })
            .catch((err) => {
                Swal.fire({
                    icon: 'error',
                    title: 'Kesalahan Jaringan',
                    text: 'Tidak dapat menghubungi server.',
                    confirmButtonColor: '#10b981'
                });
            });
        });
    });
});
</script>