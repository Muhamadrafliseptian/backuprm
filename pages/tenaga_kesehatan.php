<?php
/**
 * file   : tenaga_kesehatan.php
 * path   : C:\xampp\htdocs\backuprm\pages\tenaga_kesehatan.php
 * fungsi : Daftar tenaga kesehatan (Tailwind) — 10 baris + pagination
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
    $where[] = 'nama LIKE :q';
    $params[':q'] = '%' . $q . '%';
}
$sqlWhere = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

/* Hitung total */
$stmt = db()->prepare("SELECT COUNT(*) FROM m_tenaga_kesehatan{$sqlWhere}");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $per));

/* Ambil data */
$stmt = db()->prepare("SELECT * FROM m_tenaga_kesehatan{$sqlWhere} ORDER BY nama ASC LIMIT {$per} OFFSET {$offset}");
$stmt->execute($params);
$rows = $stmt->fetchAll();
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 fade-in-up">
    <div>
        <h2 class="text-xl md:text-2xl font-bold text-slate-900 tracking-tight">Tenaga Kesehatan</h2>
        <p class="text-xs md:text-sm text-slate-500">Total <?= number_format($total) ?> tenaga kesehatan terdaftar.</p>
    </div>
</div>

<!-- Filter -->
<div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
    <form method="get" action="<?= base_url('tenaga-kesehatan') ?>" class="flex flex-col sm:flex-row gap-3">
    <input type="hidden" name="r" value="tenaga-kesehatan">
        <div class="flex-1 relative">
            <i class="ri-search-2-line absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
            <input type="text" name="q" value="<?= e($q) ?>" placeholder="Nama tenaga kesehatan"
                   class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:bg-white focus:border-mint-500 focus:ring-2 focus:ring-mint-100 focus:outline-none transition-all">
        </div>
        <button type="submit" class="bg-mint-500 hover:bg-mint-600 text-white text-xs font-bold px-5 py-2.5 rounded-xl transition-all shadow-md shadow-mint-500/20">
            <i class="ri-search-line mr-1"></i> Cari
        </button>
        <?php if ($q !== ''): ?>
        <a href="<?= base_url('tenaga-kesehatan') ?>" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold px-5 py-2.5 rounded-xl transition-all">
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
                    <th class="py-3.5 px-5" style="width:60px">#</th>
                    <th class="py-3.5 px-5">Nama</th>
                    <th class="py-3.5 px-5" style="width:150px">Jenis</th>
                    <th class="py-3.5 px-5" style="width:200px">NIP</th>
                    <th class="py-3.5 px-5" style="width:90px">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-medium">
            <?php if (!$rows): ?>
                <tr><td colspan="5" class="text-center py-8 text-slate-400">
                    <i class="ri-inbox-line text-3xl block mb-1"></i> Tidak ada data.
                </td></tr>
            <?php else: ?>
                <?php foreach ($rows as $i => $t): ?>
                <tr class="hover:bg-slate-50/80 transition-colors">
                    <td class="py-3 px-5 text-slate-400"><?= $offset + $i + 1 ?></td>
                    <td class="py-3 px-5 font-semibold text-slate-800"><?= e($t['nama']) ?></td>
                    <td class="py-3 px-5 text-slate-500"><?= e($t['jenis_tenaga'] ?: '-') ?></td>
                    <td class="py-3 px-5 text-slate-500"><?= e($t['nip'] ?: '-') ?></td>
                    <td class="py-3 px-5">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold border <?= $t['status'] === 'aktif' ? 'bg-mint-50 text-mint-700 border-mint-200/60' : 'bg-slate-50 text-slate-600 border-slate-200' ?>">
                            <?= e($t['status']) ?>
                        </span>
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
                <a href="<?= base_url('tenaga-kesehatan?page=' . ($page - 1) . ($q ? '&q=' . urlencode($q) : '')) ?>" class="px-3 py-1.5 border border-slate-200 rounded-lg hover:bg-slate-50">Prev</a>
            <?php endif; ?>
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <a href="<?= base_url('tenaga-kesehatan?page=' . $i . ($q ? '&q=' . urlencode($q) : '')) ?>"
                   class="px-3 py-1.5 rounded-lg font-semibold <?= $i === $page ? 'bg-mint-500 text-white' : 'border border-slate-200 hover:bg-slate-50' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
                <a href="<?= base_url('tenaga-kesehatan?page=' . ($page + 1) . ($q ? '&q=' . urlencode($q) : '')) ?>" class="px-3 py-1.5 border border-slate-200 rounded-lg hover:bg-slate-50">Next</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require BASE_PATH . '/layouts/footer.php'; ?>