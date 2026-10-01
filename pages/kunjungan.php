<?php
/**
 * file   : kunjungan.php
 * path   : C:\xampp\htdocs\backuprm\pages\kunjungan.php
 * fungsi : Daftar kunjungan (Tailwind) — 10 baris/hal
 */
declare(strict_types=1);

require BASE_PATH . '/layouts/header.php';
require BASE_PATH . '/layouts/sidebar.php';

$q      = trim((string)($_GET['q'] ?? ''));
$dari   = trim((string)($_GET['dari'] ?? ''));
$sampai = trim((string)($_GET['sampai'] ?? ''));
$page   = max(1, (int)($_GET['page'] ?? 1));
$per    = 10;
$offset = ($page - 1) * $per;

$where  = [];
$params = [];
if ($q !== '') {
    // FIX: placeholder tidak boleh diulang (PDO::ATTR_EMULATE_PREPARES=false)
    $where[] = '(k.no_transaksi LIKE :q1 OR p.nama LIKE :q2 OR p.no_rm LIKE :q3)';
    $params[':q1'] = '%' . $q . '%';
    $params[':q2'] = '%' . $q . '%';
    $params[':q3'] = '%' . $q . '%';
}
if ($dari !== '') {
    $where[] = 'k.tanggal_kunjungan >= :dari';
    $params[':dari'] = $dari;
}
if ($sampai !== '') {
    $where[] = 'k.tanggal_kunjungan <= :sampai';
    $params[':sampai'] = $sampai;
}
$sqlWhere = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

/* Hitung total */
$stmt = db()->prepare("SELECT COUNT(*) FROM t_kunjungan k LEFT JOIN m_pasien p ON p.id = k.pasien_id {$sqlWhere}");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $per));

/* Ambil data */
$sql = "SELECT k.id, k.no_transaksi, k.tanggal_kunjungan, k.jam_kunjungan,
               k.sumber_data, k.status, p.no_rm, p.nama AS nama_pasien
        FROM t_kunjungan k LEFT JOIN m_pasien p ON p.id = k.pasien_id
        {$sqlWhere}
        ORDER BY k.tanggal_kunjungan DESC, k.jam_kunjungan DESC
        LIMIT {$per} OFFSET {$offset}";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

/* Statistik */
$stat = db()->query("
    SELECT COUNT(*) AS total,
           COUNT(DISTINCT pasien_id) AS pasien_unik,
           COUNT(DISTINCT tanggal_kunjungan) AS hari_aktif
    FROM t_kunjungan
")->fetch();

/* Query string untuk pagination (preserve filter) */
$qsArr = [];
if ($q !== '')      $qsArr['q'] = $q;
if ($dari !== '')   $qsArr['dari'] = $dari;
if ($sampai !== '') $qsArr['sampai'] = $sampai;
$qsBase = $qsArr ? '&' . http_build_query($qsArr) : '';
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 fade-in-up">
    <div>
        <h2 class="text-xl md:text-2xl font-bold text-slate-900 tracking-tight">Data Kunjungan</h2>
        <p class="text-xs md:text-sm text-slate-500">Total <?= number_format((int)$stat['total']) ?> kunjungan tercatat.</p>
    </div>
</div>

<!-- Stat -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 md:gap-5">
    <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total Kunjungan</p>
                <h3 class="text-2xl font-bold text-slate-900 mt-1"><?= number_format((int)$stat['total']) ?></h3>
            </div>
            <div class="p-3 bg-mint-50 text-mint-600 rounded-2xl border border-mint-100"><i class="ri-notes-medical-line text-2xl"></i></div>
        </div>
    </div>
    <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Pasien Unik</p>
                <h3 class="text-2xl font-bold text-slate-900 mt-1"><?= number_format((int)$stat['pasien_unik']) ?></h3>
            </div>
            <div class="p-3 bg-mint-50 text-mint-600 rounded-2xl border border-mint-100"><i class="ri-group-line text-2xl"></i></div>
        </div>
    </div>
    <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Hari Aktif</p>
                <h3 class="text-2xl font-bold text-slate-900 mt-1"><?= number_format((int)$stat['hari_aktif']) ?></h3>
            </div>
            <div class="p-3 bg-mint-50 text-mint-600 rounded-2xl border border-mint-100"><i class="ri-calendar-line text-2xl"></i></div>
        </div>
    </div>
</div>

<!-- Filter -->
<div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
    <form method="get" action="<?= base_url('kunjungan') ?>" class="grid grid-cols-1 md:grid-cols-4 gap-3">
        <div class="relative md:col-span-2">
            <i class="ri-search-2-line absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
            <input type="text" name="q" value="<?= e($q) ?>" placeholder="No transaksi / Nama / No RM"
                   class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:bg-white focus:border-mint-500 focus:ring-2 focus:ring-mint-100 focus:outline-none">
        </div>
        <input type="date" name="dari" value="<?= e($dari) ?>" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:bg-white focus:border-mint-500 focus:outline-none">
        <input type="date" name="sampai" value="<?= e($sampai) ?>" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:bg-white focus:border-mint-500 focus:outline-none">
        <div class="md:col-span-4 flex gap-2">
            <button type="submit" class="bg-mint-500 hover:bg-mint-600 text-white text-xs font-bold px-5 py-2.5 rounded-xl transition-all shadow-md shadow-mint-500/20">
                <i class="ri-filter-line mr-1"></i> Filter
            </button>
            <a href="<?= base_url('kunjungan') ?>" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold px-5 py-2.5 rounded-xl transition-all">
                <i class="ri-refresh-line mr-1"></i> Reset
            </a>
        </div>
    </form>
</div>

<!-- Table -->
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs text-slate-600">
            <thead class="bg-slate-50/80 text-slate-500 uppercase tracking-wider font-semibold border-b border-slate-100">
                <tr>
                    <th class="py-3.5 px-5">No Transaksi</th>
                    <th class="py-3.5 px-5">Pasien</th>
                    <th class="py-3.5 px-5">Tanggal</th>
                    <th class="py-3.5 px-5">Jam</th>
                    <th class="py-3.5 px-5">Sumber</th>
                    <th class="py-3.5 px-5">Status</th>
                    <th class="py-3.5 px-5 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-medium">
            <?php if (!$rows): ?>
                <tr><td colspan="7" class="text-center py-8 text-slate-400">
                    <i class="ri-inbox-line text-3xl block mb-1"></i> Tidak ada data.
                </td></tr>
            <?php else: ?>
                <?php foreach ($rows as $k): ?>
                <tr class="hover:bg-slate-50/80 transition-colors">
                    <td class="py-3 px-5"><code class="text-mint-700 font-semibold"><?= e($k['no_transaksi']) ?></code></td>
                    <td class="py-3 px-5">
                        <div class="font-semibold text-slate-800"><?= e($k['nama_pasien'] ?: '-') ?></div>
                        <div class="text-[11px] text-slate-400"><?= e($k['no_rm'] ?: '-') ?></div>
                    </td>
                    <td class="py-3 px-5"><?= e(date('d/m/Y', strtotime($k['tanggal_kunjungan']))) ?></td>
                    <td class="py-3 px-5"><?= e(substr($k['jam_kunjungan'] ?? '', 0, 5)) ?></td>
                    <td class="py-3 px-5">
                        <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-600 text-[11px] font-semibold"><?= e($k['sumber_data'] ?: '-') ?></span>
                    </td>
                    <td class="py-3 px-5">
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-mint-50 text-mint-700 border border-mint-200/60">
                            <i class="ri-checkbox-circle-fill"></i> <?= e($k['status']) ?>
                        </span>
                    </td>
                    <td class="py-3 px-5 text-right">
                        <a href="<?= base_url('kunjungan-detail?id=' . (int)$k['id']) ?>"
                           class="inline-flex p-1.5 text-slate-400 hover:text-mint-600 hover:bg-mint-50 rounded-lg transition-colors" title="Detail">
                            <i class="ri-eye-line text-base"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="p-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500 flex-wrap gap-2">
        <span>Halaman <strong class="text-slate-800"><?= $page ?></strong> dari <?= $totalPages ?> (<?= number_format($total) ?> data)</span>
        <div class="flex items-center gap-1">
            <?php if ($page > 1): ?>
                <a href="<?= base_url('kunjungan?page=' . ($page - 1) . $qsBase) ?>" class="px-3 py-1.5 border border-slate-200 rounded-lg hover:bg-slate-50">Prev</a>
            <?php endif; ?>
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <a href="<?= base_url('kunjungan?page=' . $i . $qsBase) ?>"
                   class="px-3 py-1.5 rounded-lg font-semibold <?= $i === $page ? 'bg-mint-500 text-white' : 'border border-slate-200 hover:bg-slate-50' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
                <a href="<?= base_url('kunjungan?page=' . ($page + 1) . $qsBase) ?>" class="px-3 py-1.5 border border-slate-200 rounded-lg hover:bg-slate-50">Next</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require BASE_PATH . '/layouts/footer.php'; ?>