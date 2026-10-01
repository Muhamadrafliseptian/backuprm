<?php
/**
 * file   : dashboard.php
 * path   : C:\xampp\htdocs\backuprm\pages\dashboard.php
 * fungsi : Dashboard dengan 4 stat real + 2 chart + log terbaru
 */
declare(strict_types=1);

require BASE_PATH . '/layouts/header.php';
require BASE_PATH . '/layouts/sidebar.php';

$welcome = flash('success');

/* ============================================================
   DATA STATISTIK REAL DARI DATABASE
   ============================================================ */
$pdo = db();

$totalPasien    = (int)$pdo->query("SELECT COUNT(*) FROM m_pasien")->fetchColumn();
$totalKunjungan = (int)$pdo->query("SELECT COUNT(*) FROM t_kunjungan")->fetchColumn();
$totalObat      = (int)$pdo->query("SELECT COUNT(*) FROM m_obat")->fetchColumn();
$totalLab       = (int)$pdo->query("SELECT COUNT(*) FROM t_laboratorium")->fetchColumn();

/* Tren kunjungan 7 hari terakhir */
$stmt = $pdo->query("
    SELECT tanggal_kunjungan, COUNT(*) AS jml
    FROM t_kunjungan
    WHERE tanggal_kunjungan >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY tanggal_kunjungan
    ORDER BY tanggal_kunjungan ASC
");
$tren = $stmt->fetchAll();
$trenLabels = [];
$trenData = [];
foreach ($tren as $t) {
    $trenLabels[] = date('d M', strtotime($t['tanggal_kunjungan']));
    $trenData[]   = (int)$t['jml'];
}

/* FIX: fallback jika data kosong agar Chart.js tidak error */
if (empty($trenLabels)) {
    $trenLabels = [date('d M')];
    $trenData   = [0];
}

/* Distribusi jenis kelamin */
$stmt = $pdo->query("
    SELECT jenis_kelamin, COUNT(*) AS jml
    FROM m_pasien
    GROUP BY jenis_kelamin
");
$dist = ['Laki-laki' => 0, 'Perempuan' => 0, 'Tidak Diketahui' => 0];
foreach ($stmt->fetchAll() as $d) {
    $jk = (int)$d['jenis_kelamin'];
    if ($jk === 1) $dist['Laki-laki'] = (int)$d['jml'];
    elseif ($jk === 2) $dist['Perempuan'] = (int)$d['jml'];
    else $dist['Tidak Diketahui'] += (int)$d['jml'];
}

/* Log aktivitas terbaru */
$logs = $pdo->query("
    SELECT created_at, user_nrk, user_nama, aksi, deskripsi, level
    FROM l_activity
    ORDER BY created_at DESC
    LIMIT 8
")->fetchAll();
?>

<!-- Page Title -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 fade-in-up">
    <div>
        <h2 class="text-xl md:text-2xl font-bold text-slate-900 tracking-tight">Dashboard Backup RM</h2>
        <p class="text-xs md:text-sm text-slate-500">Selamat datang, <?= e(auth_user()['nama'] ?? 'User') ?>. Monitoring data rekam medis.</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="<?= base_url('upload') ?>" class="flex items-center gap-1.5 px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-colors shadow-sm">
            <i class="ri-upload-cloud-line text-mint-600"></i>
            <span>Upload Backup</span>
        </a>
        <a href="<?= base_url('generate') ?>" class="flex items-center gap-2 bg-mint-500 hover:bg-mint-600 text-white text-xs font-bold px-4 py-2.5 rounded-xl transition-all shadow-md shadow-mint-500/20 active:scale-95">
            <i class="ri-magic-line text-sm"></i>
            <span>Preview Data</span>
        </a>
    </div>
</div>

<!-- 4 Stat Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-5">
    <!-- Pasien -->
    <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm hover:shadow-md transition-shadow">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total Pasien</p>
                <h3 class="text-2xl font-bold text-slate-900 mt-1"><?= number_format($totalPasien) ?></h3>
                <span class="inline-flex items-center gap-1 mt-2 text-[11px] font-semibold text-mint-700 bg-mint-50 px-2 py-0.5 rounded-md">
                    <i class="ri-user-line"></i> Terdaftar
                </span>
            </div>
            <div class="p-3 bg-mint-50 text-mint-600 rounded-2xl border border-mint-100">
                <i class="ri-group-line text-2xl"></i>
            </div>
        </div>
        <p class="text-[11px] text-slate-400 mt-3 pt-3 border-t border-slate-100">Data dari m_pasien</p>
    </div>

    <!-- Kunjungan -->
    <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm hover:shadow-md transition-shadow">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Kunjungan</p>
                <h3 class="text-2xl font-bold text-slate-900 mt-1"><?= number_format($totalKunjungan) ?></h3>
                <span class="inline-flex items-center gap-1 mt-2 text-[11px] font-semibold text-mint-700 bg-mint-50 px-2 py-0.5 rounded-md">
                    <i class="ri-calendar-check-line"></i> Tercatat
                </span>
            </div>
            <div class="p-3 bg-mint-50 text-mint-600 rounded-2xl border border-mint-100">
                <i class="ri-notes-medical-line text-2xl"></i>
            </div>
        </div>
        <p class="text-[11px] text-slate-400 mt-3 pt-3 border-t border-slate-100">Data dari t_kunjungan</p>
    </div>

    <!-- Obat -->
    <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm hover:shadow-md transition-shadow">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Jenis Obat</p>
                <h3 class="text-2xl font-bold text-slate-900 mt-1"><?= number_format($totalObat) ?></h3>
                <span class="inline-flex items-center gap-1 mt-2 text-[11px] font-semibold text-mint-700 bg-mint-50 px-2 py-0.5 rounded-md">
                    <i class="ri-capsule-line"></i> Master
                </span>
            </div>
            <div class="p-3 bg-mint-50 text-mint-600 rounded-2xl border border-mint-100">
                <i class="ri-capsule-line text-2xl"></i>
            </div>
        </div>
        <p class="text-[11px] text-slate-400 mt-3 pt-3 border-t border-slate-100">Data dari m_obat</p>
    </div>

    <!-- Lab -->
    <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm hover:shadow-md transition-shadow">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Data Lab</p>
                <h3 class="text-2xl font-bold text-slate-900 mt-1"><?= number_format($totalLab) ?></h3>
                <span class="inline-flex items-center gap-1 mt-2 text-[11px] font-semibold text-mint-700 bg-mint-50 px-2 py-0.5 rounded-md">
                    <i class="ri-flask-line"></i> Pemeriksaan
                </span>
            </div>
            <div class="p-3 bg-mint-50 text-mint-600 rounded-2xl border border-mint-100">
                <i class="ri-flask-line text-2xl"></i>
            </div>
        </div>
        <p class="text-[11px] text-slate-400 mt-3 pt-3 border-t border-slate-100">Data dari t_laboratorium</p>
    </div>
</div>

<!-- Charts -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <!-- Line Chart -->
    <div class="bg-white p-5 md:p-6 rounded-2xl border border-slate-100 shadow-sm lg:col-span-2">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-4">
            <div>
                <h3 class="font-bold text-slate-900 text-base">Tren Kunjungan</h3>
                <p class="text-xs text-slate-500">7 hari terakhir</p>
            </div>
        </div>
        <div class="h-64 w-full">
            <canvas id="trendChart"></canvas>
        </div>
    </div>

    <!-- Donut Chart -->
    <div class="bg-white p-5 md:p-6 rounded-2xl border border-slate-100 shadow-sm">
        <div>
            <h3 class="font-bold text-slate-900 text-base">Distribusi Pasien</h3>
            <p class="text-xs text-slate-500">Berdasarkan jenis kelamin</p>
        </div>
        <div class="h-48 w-full my-3 flex items-center justify-center">
            <canvas id="distributionChart"></canvas>
        </div>
        <div class="space-y-2 text-xs pt-3 border-t border-slate-100 font-medium">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-mint-500"></span>
                    <span class="text-slate-600">Laki-laki</span>
                </div>
                <strong class="text-slate-800"><?= number_format($dist['Laki-laki']) ?></strong>
            </div>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-teal-400"></span>
                    <span class="text-slate-600">Perempuan</span>
                </div>
                <strong class="text-slate-800"><?= number_format($dist['Perempuan']) ?></strong>
            </div>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-slate-300"></span>
                    <span class="text-slate-600">Tidak Diketahui</span>
                </div>
                <strong class="text-slate-800"><?= number_format($dist['Tidak Diketahui']) ?></strong>
            </div>
        </div>
    </div>
</div>

<!-- Log Terbaru -->
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <div class="p-5 border-b border-slate-100 flex items-center justify-between">
        <div>
            <h3 class="font-bold text-slate-900 text-base">Log Aktivitas Terbaru</h3>
            <p class="text-xs text-slate-500">8 aktivitas terakhir</p>
        </div>
        <a href="<?= base_url('log') ?>" class="text-xs font-semibold text-mint-600 hover:text-mint-700 flex items-center gap-1">
            Lihat Semua <i class="ri-arrow-right-line"></i>
        </a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead class="bg-slate-50/80 text-slate-500 uppercase tracking-wider font-semibold border-b border-slate-100">
                <tr>
                    <th class="py-3.5 px-5">Waktu</th>
                    <th class="py-3.5 px-5">User</th>
                    <th class="py-3.5 px-5">Level</th>
                    <th class="py-3.5 px-5">Aksi</th>
                    <th class="py-3.5 px-5">Deskripsi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-medium text-slate-600">
            <?php if (!$logs): ?>
                <tr><td colspan="5" class="text-center py-6 text-slate-400">Belum ada log.</td></tr>
            <?php else: ?>
                <?php foreach ($logs as $l):
                    $badge = match ($l['level']) {
                        'error'   => 'bg-rose-50 text-rose-600 border-rose-200/60',
                        'warning' => 'bg-amber-50 text-amber-600 border-amber-200/60',
                        default   => 'bg-mint-50 text-mint-700 border-mint-200/60',
                    };
                ?>
                <tr class="hover:bg-slate-50/80 transition-colors">
                    <td class="py-3 px-5 text-slate-500"><?= e(date('d/m H:i', strtotime($l['created_at']))) ?></td>
                    <td class="py-3 px-5">
                        <div class="font-semibold text-slate-800"><?= e($l['user_nrk'] ?: '-') ?></div>
                        <div class="text-[11px] text-slate-400"><?= e($l['user_nama'] ?: '-') ?></div>
                    </td>
                    <td class="py-3 px-5">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold border <?= $badge ?>">
                            <?= e(strtoupper($l['level'])) ?>
                        </span>
                    </td>
                    <td class="py-3 px-5"><code class="text-mint-700 text-[11px]"><?= e($l['aksi']) ?></code></td>
                    <td class="py-3 px-5 text-slate-600"><?= e($l['deskripsi']) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require BASE_PATH . '/layouts/footer.php'; ?>

<!-- ============================================================
     CHART SCRIPT
     ============================================================ -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Line Chart
    const ctxTrend = document.getElementById('trendChart').getContext('2d');
    const gradientMint = ctxTrend.createLinearGradient(0, 0, 0, 250);
    gradientMint.addColorStop(0, 'rgba(16, 185, 129, 0.25)');
    gradientMint.addColorStop(1, 'rgba(16, 185, 129, 0.0)');

    new Chart(ctxTrend, {
        type: 'line',
        data: {
            labels: <?= json_encode($trenLabels) ?>,
            datasets: [{
                label: 'Kunjungan',
                data: <?= json_encode($trenData) ?>,
                borderColor: '#10b981',
                backgroundColor: gradientMint,
                borderWidth: 3,
                fill: true,
                tension: 0.3,
                pointRadius: 4,
                pointBackgroundColor: '#10b981',
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { grid: { color: 'rgba(241, 245, 249, 1)' }, ticks: { font: { family: 'Inter', size: 11 } } },
                x: { grid: { display: false }, ticks: { font: { family: 'Inter', size: 11 } } }
            }
        }
    });

    // Donut Chart
    const ctxDist = document.getElementById('distributionChart').getContext('2d');
    new Chart(ctxDist, {
        type: 'doughnut',
        data: {
            labels: ['Laki-laki', 'Perempuan', 'Tidak Diketahui'],
            datasets: [{
                data: [
                    <?= (int)$dist['Laki-laki'] ?>,
                    <?= (int)$dist['Perempuan'] ?>,
                    <?= (int)$dist['Tidak Diketahui'] ?>
                ],
                backgroundColor: ['#10b981', '#2dd4bf', '#cbd5e1'],
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '75%',
            plugins: { legend: { display: false } }
        }
    });
});
</script>

<?php if ($welcome): ?>
<script>
    Swal.fire({
        icon: 'success',
        title: 'Login Berhasil',
        text: <?= json_encode($welcome) ?>,
        confirmButtonColor: '#10b981',
        confirmButtonText: 'OK',
        timer: 2500,
        timerProgressBar: true
    });
</script>
<?php endif; ?>