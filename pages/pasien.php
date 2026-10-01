<?php
/**
 * file   : pasien.php
 * path   : C:\xampp\htdocs\backuprm\pages\pasien.php
 * fungsi : Daftar pasien + pencarian + pagination + tombol Resume Medis
 */
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/internal_guard.php';

require BASE_PATH . '/layouts/header.php';
require BASE_PATH . '/layouts/sidebar.php';

$q      = trim((string)($_GET['q'] ?? ''));
$page   = max(1, (int)($_GET['page'] ?? 1));
$per    = 10;
$offset = ($page - 1) * $per;

/* ============================================================
   BUILD WHERE — pakai placeholder unik untuk tiap kondisi
   (PDO native tidak boleh pakai :q yang sama 3x)
   ============================================================ */
$where  = [];
$params = [];
if ($q !== '') {
    $where[] = '(nama LIKE :q1 OR no_rm LIKE :q2 OR nik LIKE :q3)';
    $params[':q1'] = '%' . $q . '%';
    $params[':q2'] = '%' . $q . '%';
    $params[':q3'] = '%' . $q . '%';
}
$sqlWhere = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

/* Hitung total */
$stmt = db()->prepare("SELECT COUNT(*) FROM m_pasien{$sqlWhere}");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $per));

/* Ambil data + jumlah kunjungan
 * LIMIT/OFFSET di-cast int supaya aman (tidak bisa di-bind sebagai string)
 */
$sql = "SELECT p.id, p.no_rm, p.nik, p.nama, p.tempat_lahir, p.tanggal_lahir,
               p.jenis_kelamin, p.status_kepesertaan, p.no_telepon,
               (SELECT COUNT(*) FROM t_kunjungan k WHERE k.pasien_id = p.id) AS total_kunjungan
        FROM m_pasien p
        {$sqlWhere}
        ORDER BY p.no_rm ASC
        LIMIT " . (int)$per . " OFFSET " . (int)$offset;
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

/* Statistik */
$stat = db()->query("
    SELECT COUNT(*) AS total,
           SUM(jenis_kelamin = 1) AS lk,
           SUM(jenis_kelamin = 2) AS pr,
           SUM(status_kepesertaan = 'PBI') AS pbi
    FROM m_pasien
")->fetch();
?>

<!-- Page Title -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 fade-in-up">
    <div>
        <h2 class="text-xl md:text-2xl font-bold text-slate-900 tracking-tight">Data Pasien</h2>
        <p class="text-xs md:text-sm text-slate-500">Total <?= number_format((int)$stat['total']) ?> pasien terdaftar.</p>
    </div>
</div>

<!-- Stat -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-5">
    <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total</p>
                <h3 class="text-2xl font-bold text-slate-900 mt-1"><?= number_format((int)$stat['total']) ?></h3>
            </div>
            <div class="p-3 bg-mint-50 text-mint-600 rounded-2xl border border-mint-100"><i class="ri-group-line text-2xl"></i></div>
        </div>
    </div>
    <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Laki-laki</p>
                <h3 class="text-2xl font-bold text-slate-900 mt-1"><?= number_format((int)$stat['lk']) ?></h3>
            </div>
            <div class="p-3 bg-mint-50 text-mint-600 rounded-2xl border border-mint-100"><i class="ri-men-line text-2xl"></i></div>
        </div>
    </div>
    <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Perempuan</p>
                <h3 class="text-2xl font-bold text-slate-900 mt-1"><?= number_format((int)$stat['pr']) ?></h3>
            </div>
            <div class="p-3 bg-mint-50 text-mint-600 rounded-2xl border border-mint-100"><i class="ri-women-line text-2xl"></i></div>
        </div>
    </div>
    <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">PBI</p>
                <h3 class="text-2xl font-bold text-slate-900 mt-1"><?= number_format((int)$stat['pbi']) ?></h3>
            </div>
            <div class="p-3 bg-mint-50 text-mint-600 rounded-2xl border border-mint-100"><i class="ri-id-card-line text-2xl"></i></div>
        </div>
    </div>
</div>

<!-- Filter -->
<div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
    <form method="get" action="<?= base_url('pasien') ?>" class="flex flex-col sm:flex-row gap-3">
        <div class="flex-1 relative">
            <i class="ri-search-2-line absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
            <input type="text" name="q" value="<?= e($q) ?>" placeholder="Nama / No RM / NIK"
                   class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:bg-white focus:border-mint-500 focus:ring-2 focus:ring-mint-100 focus:outline-none transition-all">
        </div>
        <button type="submit" class="bg-mint-500 hover:bg-mint-600 text-white text-xs font-bold px-5 py-2.5 rounded-xl transition-all shadow-md shadow-mint-500/20">
            <i class="ri-search-line mr-1"></i> Cari
        </button>
        <?php if ($q !== ''): ?>
        <a href="<?= base_url('pasien') ?>" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold px-5 py-2.5 rounded-xl transition-all">
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
                    <th class="py-3.5 px-5">No RM</th>
                    <th class="py-3.5 px-5">Nama</th>
                    <th class="py-3.5 px-5">NIK</th>
                    <th class="py-3.5 px-5">TTL</th>
                    <th class="py-3.5 px-5">JK</th>
                    <th class="py-3.5 px-5">Peserta</th>
                    <th class="py-3.5 px-5 text-center">Kunjungan</th>
                    <th class="py-3.5 px-5 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-medium">
            <?php if (!$rows): ?>
                <tr><td colspan="8" class="text-center py-8 text-slate-400">
                    <i class="ri-inbox-line text-3xl block mb-1"></i> Tidak ada data.
                </td></tr>
            <?php else: ?>
                <?php foreach ($rows as $p):
                    $jk = (int)$p['jenis_kelamin'];
                    $totalKunj = (int)$p['total_kunjungan'];
                ?>
                <tr class="hover:bg-slate-50/80 transition-colors">
                    <td class="py-3 px-5"><code class="text-mint-700 font-semibold"><?= e($p['no_rm']) ?></code></td>
                    <td class="py-3 px-5 font-semibold text-slate-800"><?= e($p['nama']) ?></td>
                    <td class="py-3 px-5 text-slate-500"><?= e($p['nik'] ?: '-') ?></td>
                    <td class="py-3 px-5">
                        <?= e($p['tempat_lahir'] ?: '-') ?>,
                        <?= $p['tanggal_lahir'] ? e(date('d/m/Y', strtotime($p['tanggal_lahir']))) : '-' ?>
                    </td>
                    <td class="py-3 px-5">
                        <?= $jk === 1 ? 'L' : ($jk === 2 ? 'P' : '-') ?>
                    </td>
                    <td class="py-3 px-5">
                        <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-600 text-[11px] font-semibold"><?= e($p['status_kepesertaan'] ?: '-') ?></span>
                    </td>
                    <td class="py-3 px-5 text-center">
                        <?php if ($totalKunj > 0): ?>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-mint-50 text-mint-700 border border-mint-200/60 text-[11px] font-bold">
                                <i class="ri-notes-medical-line"></i>
                                <?= $totalKunj ?>x
                            </span>
                        <?php else: ?>
                            <span class="text-[11px] text-slate-300">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="py-3 px-5 text-right">
                        <div class="flex items-center justify-end gap-1">
                            <?php if ($totalKunj > 0): ?>
                            <a href="<?= base_url('resume-medis?id=' . (int)$p['id']) ?>"
                               class="inline-flex items-center gap-1 px-2.5 py-1.5 text-[11px] font-bold text-mint-700 bg-mint-50 hover:bg-mint-100 border border-mint-200 rounded-lg transition-colors"
                               title="Lihat Resume Medis">
                                <i class="ri-file-text-line"></i>
                                <span class="hidden sm:inline">Resume</span>
                            </a>
                            <?php endif; ?>
                            <a href="<?= base_url('kunjungan?q=' . urlencode($p['no_rm'])) ?>"
                               class="inline-flex items-center gap-1 px-2.5 py-1.5 text-[11px] font-bold text-slate-600 bg-slate-50 hover:bg-slate-100 border border-slate-200 rounded-lg transition-colors"
                               title="Lihat Kunjungan">
                                <i class="ri-calendar-line"></i>
                            </a>
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
                <a href="<?= base_url('pasien?page=' . ($page - 1) . ($q !== '' ? '&q=' . urlencode($q) : '')) ?>" class="px-3 py-1.5 border border-slate-200 rounded-lg hover:bg-slate-50">Prev</a>
            <?php endif; ?>
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <a href="<?= base_url('pasien?page=' . $i . ($q !== '' ? '&q=' . urlencode($q) : '')) ?>"
                   class="px-3 py-1.5 rounded-lg font-semibold <?= $i === $page ? 'bg-mint-500 text-white' : 'border border-slate-200 hover:bg-slate-50' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
                <a href="<?= base_url('pasien?page=' . ($page + 1) . ($q !== '' ? '&q=' . urlencode($q) : '')) ?>" class="px-3 py-1.5 border border-slate-200 rounded-lg hover:bg-slate-50">Next</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require BASE_PATH . '/layouts/footer.php'; ?>