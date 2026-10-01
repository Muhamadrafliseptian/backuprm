<?php
/**
 * file   : log.php
 * path   : C:\xampp\htdocs\backuprm\pages\log.php
 * fungsi : Log aktivitas (Tailwind) — 10 baris + pagination
 */
declare(strict_types=1);

require BASE_PATH . '/layouts/header.php';
require BASE_PATH . '/layouts/sidebar.php';

$fLevel  = trim((string)($_GET['level']  ?? ''));
$fAksi   = trim((string)($_GET['aksi']   ?? ''));
$fUser   = trim((string)($_GET['user']   ?? ''));
$fDari   = trim((string)($_GET['dari']   ?? ''));
$fSampai = trim((string)($_GET['sampai'] ?? ''));
$page    = max(1, (int)($_GET['page'] ?? 1));
$per     = 10;
$offset  = ($page - 1) * $per;

$where = []; $params = [];
if ($fLevel !== '' && in_array($fLevel, ['info','warning','error'], true)) {
    $where[] = 'level = :level'; $params[':level'] = $fLevel;
}
if ($fAksi !== '')   { $where[] = 'aksi LIKE :aksi'; $params[':aksi'] = '%' . $fAksi . '%'; }
// FIX: placeholder tidak boleh diulang (PDO::ATTR_EMULATE_PREPARES=false)
if ($fUser !== '')   { $where[] = '(user_nrk LIKE :user1 OR user_nama LIKE :user2)'; $params[':user1'] = '%' . $fUser . '%'; $params[':user2'] = '%' . $fUser . '%'; }
if ($fDari !== '')   { $where[] = 'DATE(created_at) >= :dari'; $params[':dari'] = $fDari; }
if ($fSampai !== '') { $where[] = 'DATE(created_at) <= :sampai'; $params[':sampai'] = $fSampai; }

$sqlWhere = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

/* Hitung total */
$stmt = db()->prepare("SELECT COUNT(*) FROM l_activity{$sqlWhere}");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $per));

/* Ambil data */
$sql = "SELECT * FROM l_activity{$sqlWhere} ORDER BY created_at DESC LIMIT {$per} OFFSET {$offset}";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

/* Statistik */
$stat = db()->query("
    SELECT COUNT(*) AS total,
           SUM(level='info')    AS info_count,
           SUM(level='warning') AS warning_count,
           SUM(level='error')   AS error_count
    FROM l_activity
")->fetch();

/* Build query string untuk pagination */
$qsArr = [];
foreach (['level'=>$fLevel, 'aksi'=>$fAksi, 'user'=>$fUser, 'dari'=>$fDari, 'sampai'=>$fSampai] as $k=>$v) {
    if ($v !== '') $qsArr[$k] = $v;
}
$qsBase = $qsArr ? '&' . http_build_query($qsArr) : '';
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 fade-in-up">
    <div>
        <h2 class="text-xl md:text-2xl font-bold text-slate-900 tracking-tight">Log Aktivitas</h2>
        <p class="text-xs md:text-sm text-slate-500">Total <?= number_format((int)($stat['total'] ?? 0)) ?> aktivitas tercatat.</p>
    </div>
    <button type="button" id="btnClearLog"
            class="flex items-center gap-1.5 px-3 py-2 bg-white border border-rose-200 text-rose-600 rounded-xl text-xs font-semibold hover:bg-rose-50 transition-colors shadow-sm">
        <i class="ri-delete-bin-line"></i> Bersihkan Log
    </button>
</div>

<!-- Stat -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 md:gap-5">
    <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total</p>
        <h3 class="text-2xl font-bold text-slate-900 mt-1"><?= number_format((int)($stat['total'] ?? 0)) ?></h3>
    </div>
    <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Info</p>
        <h3 class="text-2xl font-bold text-mint-600 mt-1"><?= number_format((int)($stat['info_count'] ?? 0)) ?></h3>
    </div>
    <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Warning</p>
        <h3 class="text-2xl font-bold text-amber-500 mt-1"><?= number_format((int)($stat['warning_count'] ?? 0)) ?></h3>
    </div>
    <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Error</p>
        <h3 class="text-2xl font-bold text-rose-600 mt-1"><?= number_format((int)($stat['error_count'] ?? 0)) ?></h3>
    </div>
</div>

<!-- Filter -->
<div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
    <form method="get" action="<?= base_url('log') ?>" class="grid grid-cols-1 md:grid-cols-6 gap-3">
        <select name="level" class="px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:bg-white focus:border-mint-500 focus:outline-none">
            <option value="">Semua Level</option>
            <option value="info"    <?= $fLevel === 'info' ? 'selected' : '' ?>>Info</option>
            <option value="warning" <?= $fLevel === 'warning' ? 'selected' : '' ?>>Warning</option>
            <option value="error"   <?= $fLevel === 'error' ? 'selected' : '' ?>>Error</option>
        </select>
        <input type="text" name="aksi" value="<?= e($fAksi) ?>" placeholder="Aksi (login, upload...)" class="px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:bg-white focus:border-mint-500 focus:outline-none">
        <input type="text" name="user" value="<?= e($fUser) ?>" placeholder="NRK / Nama" class="px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:bg-white focus:border-mint-500 focus:outline-none">
        <input type="date" name="dari" value="<?= e($fDari) ?>" class="px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:bg-white focus:border-mint-500 focus:outline-none">
        <input type="date" name="sampai" value="<?= e($fSampai) ?>" class="px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:bg-white focus:border-mint-500 focus:outline-none">
        <div class="flex gap-2">
            <button type="submit" class="flex-1 bg-mint-500 hover:bg-mint-600 text-white text-xs font-bold py-2.5 rounded-xl"><i class="ri-filter-line mr-1"></i>Filter</button>
            <a href="<?= base_url('log') ?>" class="px-3 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl"><i class="ri-refresh-line"></i></a>
        </div>
    </form>
</div>

<!-- Table -->
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs text-slate-600">
            <thead class="bg-slate-50/80 text-slate-500 uppercase tracking-wider font-semibold border-b border-slate-100">
                <tr>
                    <th class="py-3.5 px-5">Waktu</th>
                    <th class="py-3.5 px-5">Level</th>
                    <th class="py-3.5 px-5">User</th>
                    <th class="py-3.5 px-5">Aksi</th>
                    <th class="py-3.5 px-5">Deskripsi</th>
                    <th class="py-3.5 px-5">IP</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-medium">
            <?php if (!$logs): ?>
                <tr><td colspan="6" class="text-center py-8 text-slate-400">
                    <i class="ri-inbox-line text-3xl block mb-1"></i> Belum ada log.
                </td></tr>
            <?php else: ?>
                <?php foreach ($logs as $l):
                    $badge = match ($l['level']) {
                        'error'   => 'bg-rose-50 text-rose-600 border-rose-200/60',
                        'warning' => 'bg-amber-50 text-amber-600 border-amber-200/60',
                        default   => 'bg-mint-50 text-mint-700 border-mint-200/60',
                    };
                ?>
                <tr class="hover:bg-slate-50/80 transition-colors">
                    <td class="py-3 px-5 text-slate-500 whitespace-nowrap"><?= e(date('d/m/Y H:i:s', strtotime($l['created_at']))) ?></td>
                    <td class="py-3 px-5">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold border <?= $badge ?>"><?= e(strtoupper($l['level'])) ?></span>
                    </td>
                    <td class="py-3 px-5">
                        <div class="font-semibold text-slate-800"><?= e($l['user_nrk'] ?: '-') ?></div>
                        <div class="text-[11px] text-slate-400"><?= e($l['user_nama'] ?: '-') ?></div>
                    </td>
                    <td class="py-3 px-5"><code class="text-mint-700 text-[11px]"><?= e($l['aksi']) ?></code></td>
                    <td class="py-3 px-5 text-slate-600"><?= e($l['deskripsi']) ?></td>
                    <td class="py-3 px-5 text-slate-500"><?= e($l['ip_address'] ?: '-') ?></td>
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
                <a href="<?= base_url('log?page=' . ($page - 1) . $qsBase) ?>" class="px-3 py-1.5 border border-slate-200 rounded-lg hover:bg-slate-50">Prev</a>
            <?php endif; ?>
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <a href="<?= base_url('log?page=' . $i . $qsBase) ?>"
                   class="px-3 py-1.5 rounded-lg font-semibold <?= $i === $page ? 'bg-mint-500 text-white' : 'border border-slate-200 hover:bg-slate-50' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
                <a href="<?= base_url('log?page=' . ($page + 1) . $qsBase) ?>" class="px-3 py-1.5 border border-slate-200 rounded-lg hover:bg-slate-50">Next</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require BASE_PATH . '/layouts/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('btnClearLog');
    if (btn) {
        btn.addEventListener('click', function () {
            window.confirmAction({
                title: 'Bersihkan semua log?',
                text: 'Tindakan ini tidak dapat dibatalkan.',
                icon: 'warning',
                confirmText: 'Ya, Bersihkan'
            }).then(ok => {
                if (!ok) return;
                NProgress.start();
                fetch(window.u('/actions/clear-log'), {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': window.CSRF_TOKEN
                    }
                })
                .then(r => r.json())
                .then(res => {
                    NProgress.done();
                    if (res.ok) {
                        window.showToast(res.msg || 'Log dibersihkan', 'success');
                        setTimeout(() => location.reload(), 800);
                    } else {
                        window.showToast(res.msg || 'Gagal', 'error');
                    }
                })
                .catch(() => {
                    NProgress.done();
                    window.showToast('Terjadi kesalahan', 'error');
                });
            });
        });
    }
});
</script>