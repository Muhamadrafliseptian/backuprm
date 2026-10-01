<?php
/**
 * file   : kunjungan_detail.php
 * path   : C:\xampp\htdocs\backuprm\pages\kunjungan_detail.php
 * fungsi : Detail kunjungan lengkap + tombol Resume Medis
 */
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/internal_guard.php';

require BASE_PATH . '/layouts/header.php';
require BASE_PATH . '/layouts/sidebar.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) redirect('kunjungan');

$pdo = db();

$stmt = $pdo->prepare("
    SELECT k.*, p.id AS pasien_id, p.no_rm, p.nama AS nama_pasien, p.nik, p.tempat_lahir, p.tanggal_lahir,
           p.jenis_kelamin, p.alamat, p.no_telepon, p.status_kepesertaan
    FROM t_kunjungan k LEFT JOIN m_pasien p ON p.id = k.pasien_id
    WHERE k.id = :id
");
$stmt->execute([':id' => $id]);
$k = $stmt->fetch();
if (!$k) redirect('kunjungan');

$stmt = $pdo->prepare("SELECT * FROM t_pemeriksaan WHERE kunjungan_id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$periksa = $stmt->fetch();

$stmt = $pdo->prepare("SELECT * FROM t_diagnosis WHERE kunjungan_id = :id ORDER BY urutan ASC");
$stmt->execute([':id' => $id]);
$diagnosis = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT t.*, n.nama AS nama_tenaga FROM t_tindakan t LEFT JOIN m_tenaga_kesehatan n ON n.id = t.tenaga_kesehatan_id WHERE t.kunjungan_id = :id");
$stmt->execute([':id' => $id]);
$tindakan = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT r.*, n.nama AS nama_dokter FROM t_resep r LEFT JOIN m_tenaga_kesehatan n ON n.id = r.dokter_id WHERE r.kunjungan_id = :id");
$stmt->execute([':id' => $id]);
$resep = $stmt->fetchAll();

$resepDetail = [];
if ($resep) {
    $ids = array_column($resep, 'id');
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT rd.*, o.nama_obat FROM d_resep_detail rd LEFT JOIN m_obat o ON o.id = rd.obat_id WHERE rd.resep_id IN ($ph) ORDER BY rd.id ASC");
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $row) $resepDetail[$row['resep_id']][] = $row;
}

$stmt = $pdo->prepare("SELECT * FROM t_laboratorium WHERE kunjungan_id = :id");
$stmt->execute([':id' => $id]);
$lab = $stmt->fetchAll();

$jkLabel = match ((int)$k['jenis_kelamin']) { 1 => 'Laki-laki', 2 => 'Perempuan', default => '-' };

/* Hitung total kunjungan pasien ini */
$stmt = $pdo->prepare("SELECT COUNT(*) FROM t_kunjungan WHERE pasien_id = :pid");
$stmt->execute([':pid' => $k['pasien_id']]);
$totalKunjunganPasien = (int)$stmt->fetchColumn();
?>

<!-- ============================================================
     PAGE HEADER
     ============================================================ -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 fade-in-up">
    <div>
        <h2 class="text-xl md:text-2xl font-bold text-slate-900 tracking-tight">Detail Kunjungan</h2>
        <p class="text-xs md:text-sm text-slate-500">
            No Transaksi: <code class="text-mint-600"><?= e($k['no_transaksi']) ?></code>
            &middot; <?= e(date('d/m/Y', strtotime($k['tanggal_kunjungan']))) ?>
            <?= e(substr($k['jam_kunjungan'] ?? '', 0, 5)) ?>
        </p>
    </div>
    <div class="flex items-center gap-2">
        <a href="<?= base_url('kunjungan') ?>"
           class="flex items-center gap-1.5 px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-colors shadow-sm">
            <i class="ri-arrow-left-line"></i> Kembali
        </a>
        <?php if (!empty($k['pasien_id'])): ?>
        <a href="<?= base_url('resume-medis?id=' . (int)$k['pasien_id']) ?>"
           class="flex items-center gap-1.5 px-4 py-2 bg-mint-500 hover:bg-mint-600 text-white rounded-xl text-xs font-bold transition-all shadow-md shadow-mint-500/20 active:scale-95">
            <i class="ri-file-text-line"></i>
            <span>Resume Medis</span>
            <?php if ($totalKunjunganPasien > 1): ?>
                <span class="ml-1 px-1.5 py-0.5 rounded-full bg-white/20 text-[10px] font-bold">
                    <?= $totalKunjunganPasien ?>x
                </span>
            <?php endif; ?>
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- ============================================================
     INFO TAMBAHAN: Kunjungan ke berapa
     ============================================================ -->
<?php if ($totalKunjunganPasien > 1): ?>
<div class="bg-mint-50/60 border border-mint-200/60 rounded-2xl p-4 flex items-start gap-3">
    <i class="ri-information-line text-mint-600 text-xl mt-0.5"></i>
    <div class="flex-1">
        <div class="text-xs font-semibold text-mint-900">
            Pasien ini telah berkunjung <b><?= $totalKunjunganPasien ?> kali</b>.
        </div>
        <div class="text-[11px] text-mint-700 mt-0.5">
            Lihat riwayat lengkap semua kunjungan di halaman
            <a href="<?= base_url('resume-medis?id=' . (int)$k['pasien_id']) ?>" class="underline font-semibold hover:text-mint-900">
                Resume Medis &rarr;
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ============================================================
     KARTU PASIEN
     ============================================================ -->
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm">
    <div class="p-5 border-b border-slate-100 flex items-center justify-between flex-wrap gap-2">
        <div class="flex items-center gap-2">
            <i class="ri-user-line text-mint-600 text-lg"></i>
            <h3 class="font-bold text-slate-900 text-base">Identitas Pasien</h3>
        </div>
        <?php if (!empty($k['pasien_id'])): ?>
        <a href="<?= base_url('resume-medis?id=' . (int)$k['pasien_id']) ?>"
           class="text-[11px] font-semibold text-mint-600 hover:text-mint-700 flex items-center gap-1">
            <i class="ri-external-link-line"></i> Buka Resume
        </a>
        <?php endif; ?>
    </div>
    <div class="p-5 grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="col-span-2 md:col-span-2">
            <div class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">Nama</div>
            <div class="font-bold text-slate-800 mt-0.5"><?= e($k['nama_pasien'] ?: '-') ?></div>
        </div>
        <div>
            <div class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">No RM</div>
            <div class="mt-0.5"><code class="text-mint-700 font-semibold"><?= e($k['no_rm'] ?: '-') ?></code></div>
        </div>
        <div>
            <div class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">Jenis Kelamin</div>
            <div class="mt-0.5 text-slate-700"><?= e($jkLabel) ?></div>
        </div>
        <div class="col-span-2 md:col-span-1">
            <div class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">NIK</div>
            <div class="mt-0.5 text-slate-700"><?= e($k['nik'] ?: '-') ?></div>
        </div>
        <div class="col-span-2 md:col-span-1">
            <div class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">TTL</div>
            <div class="mt-0.5 text-slate-700 text-xs">
                <?= e($k['tempat_lahir'] ?: '-') ?>,
                <?= $k['tanggal_lahir'] ? e(date('d/m/Y', strtotime($k['tanggal_lahir']))) : '-' ?>
            </div>
        </div>
        <div>
            <div class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">Peserta</div>
            <div class="mt-0.5">
                <span class="inline-flex items-center px-2 py-0.5 rounded bg-mint-50 text-mint-700 text-[11px] font-bold"><?= e($k['status_kepesertaan'] ?: '-') ?></span>
            </div>
        </div>
        <div>
            <div class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">Telepon</div>
            <div class="mt-0.5 text-slate-700"><?= e($k['no_telepon'] ?: '-') ?></div>
        </div>
        <div class="col-span-2 md:col-span-4">
            <div class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">Alamat</div>
            <div class="mt-0.5 text-slate-700 text-xs"><?= e($k['alamat'] ?: '-') ?></div>
        </div>
    </div>
</div>

<!-- ============================================================
     PEMERIKSAAN
     ============================================================ -->
<?php if ($periksa): ?>
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm">
    <div class="p-5 border-b border-slate-100 flex items-center gap-2">
        <i class="ri-stethoscope-line text-mint-600 text-lg"></i>
        <h3 class="font-bold text-slate-900 text-base">Pemeriksaan</h3>
    </div>
    <div class="p-5 grid grid-cols-2 md:grid-cols-6 gap-4">
        <?php
        $vitals = [
            'Kesadaran' => $periksa['kesadaran'],
            'Nadi'      => $periksa['nadi'] ? $periksa['nadi'] . ' x/mnt' : null,
            'RR'        => $periksa['respiratory_rate'] ? $periksa['respiratory_rate'] . ' x/mnt' : null,
            'TD'        => $periksa['tekanan_darah'],
            'Suhu'      => $periksa['suhu'] ? $periksa['suhu'] . ' °C' : null,
            'IMT'       => $periksa['imt'],
        ];
        foreach ($vitals as $label => $val): ?>
        <div>
            <div class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider"><?= e($label) ?></div>
            <div class="mt-0.5 font-semibold text-slate-800">
                <?= $val ? e($val) : '<span class="text-slate-300">-</span>' ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ============================================================
     DIAGNOSIS
     ============================================================ -->
<?php if ($diagnosis): ?>
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <div class="p-5 border-b border-slate-100 flex items-center gap-2">
        <i class="ri-diagnoses-line text-mint-600 text-lg"></i>
        <h3 class="font-bold text-slate-900 text-base">Diagnosis</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead class="bg-slate-50/80 text-slate-500 uppercase tracking-wider font-semibold border-b border-slate-100">
                <tr>
                    <th class="py-3 px-5" style="width:60px">No</th>
                    <th class="py-3 px-5" style="width:100px">ICD</th>
                    <th class="py-3 px-5">Nama</th>
                    <th class="py-3 px-5" style="width:100px">Jenis</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-medium text-slate-600">
            <?php foreach ($diagnosis as $d): ?>
                <tr class="hover:bg-slate-50/80">
                    <td class="py-3 px-5"><?= (int)$d['urutan'] ?></td>
                    <td class="py-3 px-5"><code class="text-mint-700"><?= e($d['kode_icd'] ?: '-') ?></code></td>
                    <td class="py-3 px-5"><?= e($d['nama_diagnosis']) ?></td>
                    <td class="py-3 px-5">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold border <?= $d['jenis_diagnosis'] === 'Utama' ? 'bg-mint-50 text-mint-700 border-mint-200/60' : 'bg-slate-50 text-slate-600 border-slate-200' ?>">
                            <?= e($d['jenis_diagnosis']) ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- ============================================================
     TINDAKAN
     ============================================================ -->
<?php if ($tindakan): ?>
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <div class="p-5 border-b border-slate-100 flex items-center gap-2">
        <i class="ri-syringe-line text-mint-600 text-lg"></i>
        <h3 class="font-bold text-slate-900 text-base">Tindakan</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead class="bg-slate-50/80 text-slate-500 uppercase tracking-wider font-semibold border-b border-slate-100">
                <tr>
                    <th class="py-3 px-5">Nama Tindakan</th>
                    <th class="py-3 px-5" style="width:200px">Pelaksana</th>
                    <th class="py-3 px-5" style="width:180px">Waktu</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-medium text-slate-600">
            <?php foreach ($tindakan as $t): ?>
                <tr class="hover:bg-slate-50/80">
                    <td class="py-3 px-5"><?= e($t['nama_tindakan']) ?></td>
                    <td class="py-3 px-5"><?= e($t['nama_tenaga'] ?: '-') ?></td>
                    <td class="py-3 px-5 text-slate-500">
                        <?= e($t['tanggal_tindakan'] ? date('d/m/Y', strtotime($t['tanggal_tindakan'])) : '-') ?>
                        <?= e(substr($t['jam_tindakan'] ?? '', 0, 5)) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- ============================================================
     RESEP
     ============================================================ -->
<?php if ($resep): ?>
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm">
    <div class="p-5 border-b border-slate-100 flex items-center gap-2">
        <i class="ri-prescription-line text-mint-600 text-lg"></i>
        <h3 class="font-bold text-slate-900 text-base">Resep Obat</h3>
    </div>
    <div class="p-5 space-y-5">
        <?php foreach ($resep as $r): ?>
        <div>
            <div class="text-xs text-slate-500 mb-2">
                <b class="text-slate-700">Dokter:</b> <?= e($r['nama_dokter'] ?: '-') ?>
                &middot; <b class="text-slate-700">Tanggal:</b> <?= $r['tanggal_resep'] ? e(date('d/m/Y', strtotime($r['tanggal_resep']))) : '-' ?>
            </div>
            <div class="overflow-x-auto rounded-xl border border-slate-100">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50/80 text-slate-500 uppercase tracking-wider font-semibold border-b border-slate-100">
                        <tr>
                            <th class="py-2.5 px-4">Nama Obat</th>
                            <th class="py-2.5 px-4" style="width:100px">Jumlah</th>
                            <th class="py-2.5 px-4" style="width:250px">Aturan Pakai</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium text-slate-600">
                    <?php foreach (($resepDetail[$r['id']] ?? []) as $rd): ?>
                        <tr>
                            <td class="py-2.5 px-4"><?= e($rd['nama_obat'] ?: $rd['nama_obat_snapshot']) ?></td>
                            <td class="py-2.5 px-4"><?= e($rd['jumlah'] ?: '-') ?></td>
                            <td class="py-2.5 px-4 text-slate-500"><?= e($rd['aturan_pakai'] ?: '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ============================================================
     LABORATORIUM
     ============================================================ -->
<?php if ($lab): ?>
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm">
    <div class="p-5 border-b border-slate-100 flex items-center gap-2">
        <i class="ri-flask-line text-mint-600 text-lg"></i>
        <h3 class="font-bold text-slate-900 text-base">Laboratorium</h3>
    </div>
    <div class="p-5 space-y-3">
        <?php foreach ($lab as $l): ?>
        <div class="bg-slate-50/50 border border-slate-100 rounded-xl p-4">
            <div class="text-[11px] text-slate-400 font-semibold mb-1">
                <i class="ri-time-line"></i>
                <?= $l['tanggal'] ? e(date('d/m/Y', strtotime($l['tanggal']))) : '-' ?>
                <?= e(substr($l['jam'] ?? '', 0, 5)) ?>
            </div>
            <div class="text-xs text-slate-700 whitespace-pre-wrap"><?= e($l['hasil'] ?: '-') ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ============================================================
     BOTTOM ACTION: Lihat Resume Medis Lengkap
     ============================================================ -->
<?php if (!empty($k['pasien_id']) && $totalKunjunganPasien > 1): ?>
<div class="bg-gradient-to-r from-mint-50 to-white rounded-2xl border border-mint-200/60 shadow-sm p-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <div class="flex items-start gap-3">
        <div class="p-3 bg-mint-500 text-white rounded-2xl shadow-md shadow-mint-500/20">
            <i class="ri-file-text-line text-2xl"></i>
        </div>
        <div>
            <div class="font-bold text-slate-900">Lihat Riwayat Lengkap Pasien</div>
            <div class="text-xs text-slate-600 mt-0.5">
                Pasien ini memiliki <b><?= $totalKunjunganPasien ?> kunjungan</b>. Buka Resume Medis untuk melihat timeline lengkap.
            </div>
        </div>
    </div>
    <a href="<?= base_url('resume-medis?id=' . (int)$k['pasien_id']) ?>"
       class="flex items-center justify-center gap-1.5 px-5 py-3 bg-mint-500 hover:bg-mint-600 text-white text-xs font-bold rounded-xl transition-all shadow-md shadow-mint-500/20 active:scale-95 whitespace-nowrap">
        <i class="ri-external-link-line"></i> Buka Resume Medis
    </a>
</div>
<?php endif; ?>

<?php require BASE_PATH . '/layouts/footer.php'; ?>