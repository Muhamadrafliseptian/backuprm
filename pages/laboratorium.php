<?php
/**
 * file   : laboratorium.php
 * path   : C:\xampp\htdocs\backuprm\pages\laboratorium.php
 * fungsi : Daftar hasil laboratorium (Tailwind) — 10 baris + pagination
 */
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/internal_guard.php';

require BASE_PATH . '/layouts/header.php';
require BASE_PATH . '/layouts/sidebar.php';

$q      = trim((string)($_GET['q'] ?? ''));
$page   = max(1, (int)($_GET['page'] ?? 1));
$per    = 10;
$offset = ($page - 1) * $per;

$where  = []; $params = [];
if ($q !== '') {
    // FIX: placeholder tidak boleh diulang (PDO::ATTR_EMULATE_PREPARES=false)
    $where[] = '(p.nama LIKE :q1 OR p.no_rm LIKE :q2 OR k.no_transaksi LIKE :q3)';
    $params[':q1'] = '%' . $q . '%';
    $params[':q2'] = '%' . $q . '%';
    $params[':q3'] = '%' . $q . '%';
}
$sqlWhere = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

/* Hitung total */
$stmt = db()->prepare("
    SELECT COUNT(*)
    FROM t_laboratorium l
    LEFT JOIN t_kunjungan k ON k.id = l.kunjungan_id
    LEFT JOIN m_pasien p ON p.id = k.pasien_id
    {$sqlWhere}
");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $per));

/* Ambil data */
$stmt = db()->prepare("
    SELECT l.*, k.no_transaksi, k.tanggal_kunjungan, p.no_rm, p.nama AS nama_pasien
    FROM t_laboratorium l
    LEFT JOIN t_kunjungan k ON k.id = l.kunjungan_id
    LEFT JOIN m_pasien p ON p.id = k.pasien_id
    {$sqlWhere}
    ORDER BY l.tanggal DESC, l.id DESC
    LIMIT {$per} OFFSET {$offset}
");
$stmt->execute($params);
$rows = $stmt->fetchAll();
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 fade-in-up">
    <div>
        <h2 class="text-xl md:text-2xl font-bold text-slate-900 tracking-tight">Data Laboratorium</h2>
        <p class="text-xs md:text-sm text-slate-500">Total <?= number_format($total) ?> data laboratorium.</p>
    </div>
</div>

<!-- Filter -->
<div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
    <form method="get" action="<?= base_url('laboratorium') ?>" class="flex flex-col sm:flex-row gap-3">
        <div class="flex-1 relative">
            <i class="ri-search-2-line absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
            <input type="text" name="q" value="<?= e($q) ?>" placeholder="Nama / No RM / No Transaksi"
                   class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:bg-white focus:border-mint-500 focus:ring-2 focus:ring-mint-100 focus:outline-none transition-all">
        </div>
        <button type="submit" class="bg-mint-500 hover:bg-mint-600 text-white text-xs font-bold px-5 py-2.5 rounded-xl transition-all shadow-md shadow-mint-500/20">
            <i class="ri-search-line mr-1"></i> Cari
        </button>
        <?php if ($q !== ''): ?>
        <a href="<?= base_url('laboratorium') ?>" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold px-5 py-2.5 rounded-xl transition-all">
            <i class="ri-refresh-line mr-1"></i> Reset
        </a>
        <?php endif; ?>
    </form>
</div>

<!-- Table -->
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs text-slate-600">
            <thead class="bg-slate-50/80 text-slate-500 uppercase tracking-wider font-semibold border-b border-slate-100">
                <tr>
                    <th class="py-3.5 px-5" style="width:150px">Tanggal</th>
                    <th class="py-3.5 px-5" style="width:180px">No Transaksi</th>
                    <th class="py-3.5 px-5">Pasien</th>
                    <th class="py-3.5 px-5">Hasil</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-medium">
            <?php if (!$rows): ?>
                <tr><td colspan="4" class="text-center py-8 text-slate-400">
                    <i class="ri-inbox-line text-3xl block mb-1"></i> Tidak ada data.
                </td></tr>
            <?php else: ?>
                <?php foreach ($rows as $l): ?>
                <tr class="hover:bg-slate-50/80 transition-colors">
                    <td class="py-3 px-5">
                        <?= $l['tanggal'] ? e(date('d/m/Y', strtotime($l['tanggal']))) : '-' ?>
                        <div class="text-[11px] text-slate-400"><?= e(substr($l['jam'] ?? '', 0, 5)) ?></div>
                    </td>
                    <td class="py-3 px-5"><code class="text-mint-700 font-semibold"><?= e($l['no_transaksi'] ?: '-') ?></code></td>
                    <td class="py-3 px-5">
                        <div class="font-semibold text-slate-800"><?= e($l['nama_pasien'] ?: '-') ?></div>
                        <div class="text-[11px] text-slate-400"><?= e($l['no_rm'] ?: '-') ?></div>
                    </td>
                    <td class="py-3 px-5 text-slate-600" style="max-width:400px">
                        <div style="max-height:80px;overflow:hidden;text-overflow:ellipsis">
                            <?= e(mb_substr($l['hasil'] ?: '-', 0, 200)) ?><?= mb_strlen($l['hasil'] ?? '') > 200 ? '...' : '' ?>
                        </div>
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
                <a href="<?= base_url('laboratorium?page=' . ($page - 1) . ($q ? '&q=' . urlencode($q) : '')) ?>" class="px-3 py-1.5 border border-slate-200 rounded-lg hover:bg-slate-50">Prev</a>
            <?php endif; ?>
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <a href="<?= base_url('laboratorium?page=' . $i . ($q ? '&q=' . urlencode($q) : '')) ?>"
                   class="px-3 py-1.5 rounded-lg font-semibold <?= $i === $page ? 'bg-mint-500 text-white' : 'border border-slate-200 hover:bg-slate-50' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
                <a href="<?= base_url('laboratorium?page=' . ($page + 1) . ($q ? '&q=' . urlencode($q) : '')) ?>" class="px-3 py-1.5 border border-slate-200 rounded-lg hover:bg-slate-50">Next</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require BASE_PATH . '/layouts/footer.php'; ?>