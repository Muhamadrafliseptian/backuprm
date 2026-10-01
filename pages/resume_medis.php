<?php
/**
 * file   : resume_medis.php
 * path   : C:\xampp\htdocs\backuprm\pages\resume_medis.php
 * fungsi : Resume Medis pasien lintas kunjungan (kronologis)
 *          URL: /resume-medis?id={pasien_id}
 *          Support print preview profesional
 */
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/internal_guard.php';

require BASE_PATH . '/layouts/header.php';
require BASE_PATH . '/layouts/sidebar.php';

$pasienId = (int)($_GET['id'] ?? 0);
if ($pasienId <= 0) {
    echo '<div class="bg-rose-50 border border-rose-200 text-rose-700 rounded-xl p-4 text-sm">ID pasien tidak valid.</div>';
    require BASE_PATH . '/layouts/footer.php';
    exit;
}

$pdo = db();

/* ---------- Data Pasien ---------- */
$stmt = $pdo->prepare("SELECT * FROM m_pasien WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $pasienId]);
$pasien = $stmt->fetch();

if (!$pasien) {
    echo '<div class="bg-rose-50 border border-rose-200 text-rose-700 rounded-xl p-4 text-sm">Pasien tidak ditemukan.</div>';
    require BASE_PATH . '/layouts/footer.php';
    exit;
}

/* ---------- Kunjungan ---------- */
$stmt = $pdo->prepare("
    SELECT k.*,
           (SELECT COUNT(*) FROM t_diagnosis  WHERE kunjungan_id = k.id) AS jml_diagnosis,
           (SELECT COUNT(*) FROM t_tindakan   WHERE kunjungan_id = k.id) AS jml_tindakan,
           (SELECT COUNT(*) FROM d_resep_detail rd
                INNER JOIN t_resep r ON r.id = rd.resep_id
                WHERE r.kunjungan_id = k.id) AS jml_obat,
           (SELECT COUNT(*) FROM t_laboratorium WHERE kunjungan_id = k.id) AS jml_lab
    FROM t_kunjungan k
    WHERE k.pasien_id = :pid
    ORDER BY k.tanggal_kunjungan ASC, k.jam_kunjungan ASC
");
$stmt->execute([':pid' => $pasienId]);
$kunjunganList = $stmt->fetchAll();

/* ---------- Detail tiap kunjungan ---------- */
$detailMap = [];
if ($kunjunganList) {
    $kunjunganIds = array_column($kunjunganList, 'id');
    $placeholders = implode(',', array_fill(0, count($kunjunganIds), '?'));

    $stmt = $pdo->prepare("SELECT * FROM t_pemeriksaan WHERE kunjungan_id IN ($placeholders)");
    $stmt->execute($kunjunganIds);
    foreach ($stmt->fetchAll() as $row) $detailMap[$row['kunjungan_id']]['pemeriksaan'] = $row;

    $stmt = $pdo->prepare("SELECT * FROM t_diagnosis WHERE kunjungan_id IN ($placeholders) ORDER BY kunjungan_id, urutan");
    $stmt->execute($kunjunganIds);
    foreach ($stmt->fetchAll() as $row) $detailMap[$row['kunjungan_id']]['diagnosis'][] = $row;

    $stmt = $pdo->prepare("
        SELECT t.*, n.nama AS nama_tenaga
        FROM t_tindakan t
        LEFT JOIN m_tenaga_kesehatan n ON n.id = t.tenaga_kesehatan_id
        WHERE t.kunjungan_id IN ($placeholders)
    ");
    $stmt->execute($kunjunganIds);
    foreach ($stmt->fetchAll() as $row) $detailMap[$row['kunjungan_id']]['tindakan'][] = $row;

    $stmt = $pdo->prepare("
        SELECT r.id AS resep_id, r.kunjungan_id, r.tanggal_resep, r.jam_resep,
               n.nama AS nama_dokter
        FROM t_resep r
        LEFT JOIN m_tenaga_kesehatan n ON n.id = r.dokter_id
        WHERE r.kunjungan_id IN ($placeholders)
    ");
    $stmt->execute($kunjunganIds);
    $resepList = $stmt->fetchAll();

    $resepIds = array_column($resepList, 'resep_id');
    $resepDetailMap = [];
    if ($resepIds) {
        $ph2 = implode(',', array_fill(0, count($resepIds), '?'));
        $stmt = $pdo->prepare("
            SELECT rd.*, o.nama_obat
            FROM d_resep_detail rd
            LEFT JOIN m_obat o ON o.id = rd.obat_id
            WHERE rd.resep_id IN ($ph2)
            ORDER BY rd.id
        ");
        $stmt->execute($resepIds);
        foreach ($stmt->fetchAll() as $row) $resepDetailMap[$row['resep_id']][] = $row;
    }
    foreach ($resepList as $r) {
        $r['detail'] = $resepDetailMap[$r['resep_id']] ?? [];
        $detailMap[$r['kunjungan_id']]['resep'][] = $r;
    }

    $stmt = $pdo->prepare("SELECT * FROM t_laboratorium WHERE kunjungan_id IN ($placeholders)");
    $stmt->execute($kunjunganIds);
    foreach ($stmt->fetchAll() as $row) $detailMap[$row['kunjungan_id']]['lab'][] = $row;
}

/* ---------- Statistik ---------- */
$totalKunjungan = count($kunjunganList);
$kunjunganPertama = $kunjunganList ? reset($kunjunganList) : null;
$kunjunganTerakhir = $kunjunganList ? end($kunjunganList) : null;

$jkLabel = match ((int)$pasien['jenis_kelamin']) {
    1 => 'Laki-laki',
    2 => 'Perempuan',
    default => '-'
};

$usia = '-';
if (!empty($pasien['tanggal_lahir'])) {
    $lahir = new DateTime($pasien['tanggal_lahir']);
    $now   = new DateTime();
    $usia  = $lahir->diff($now)->y . ' tahun';
}
?>

<!-- ============================================================
     SCREEN: TOMBOL AKSI (TIDAK DI-PRINT)
     ============================================================ -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 fade-in-up screen-only">
    <div>
        <h2 class="text-xl md:text-2xl font-bold text-slate-900 tracking-tight">Resume Medis</h2>
        <p class="text-xs md:text-sm text-slate-500">Riwayat lengkap kunjungan pasien secara kronologis.</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="<?= base_url('pasien') ?>"
           class="flex items-center gap-1.5 px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-colors shadow-sm">
            <i class="ri-arrow-left-line"></i> Kembali
        </a>
        <button type="button" onclick="cetakResume()"
                class="flex items-center gap-1.5 px-3 py-2 bg-mint-500 hover:bg-mint-600 text-white rounded-xl text-xs font-bold transition-all shadow-md shadow-mint-500/20">
            <i class="ri-printer-line"></i> Cetak
        </button>
    </div>
</div>

<!-- ============================================================
     PRINT: KOP SURAT
     ============================================================ -->
<div class="print-only mb-3 pb-2 border-b-2 border-black">
    <table style="width:100%;border:none">
        <tr>
            <td style="border:none;text-align:center;padding:0">
                <div style="font-size:11pt;font-weight:700;text-transform:uppercase;letter-spacing:0.5px">
                    Pemerintah Provinsi DKI Jakarta
                </div>
                <div style="font-size:13pt;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-top:2px">
                    Puskesmas Setiabudi
                </div>
                <div style="font-size:9pt;margin-top:2px">
                    Jl. Setiabudi Raya No. 1, Jakarta Selatan 12910
                </div>
                <div style="font-size:9pt">
                    Telp. (021) 1234567 | Email: puskesmas.setiabudi@jakarta.go.id
                </div>
            </td>
        </tr>
    </table>
    <div style="text-align:center;margin-top:8px">
        <div style="font-size:12pt;font-weight:700;text-decoration:underline;text-transform:uppercase">
            Resume Medis Pasien
        </div>
        <div style="font-size:9pt;margin-top:2px">
            No. RM: <?= e($pasien['no_rm']) ?> &nbsp;|&nbsp; Nama: <?= e($pasien['nama']) ?>
        </div>
    </div>
</div>

<!-- ============================================================
     IDENTITAS PASIEN (TABEL, TAMPIL DI PRINT & SCREEN)
     ============================================================ -->
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden avoid-break screen-only">
    <div class="p-3 border-b border-slate-100">
        <h3 class="font-bold text-slate-900 text-sm flex items-center gap-2">
            <i class="ri-user-heart-line text-mint-600"></i> Identitas Pasien
        </h3>
    </div>
    <div class="p-4">
        <table class="w-full text-xs" style="border-collapse:collapse">
            <tbody>
                <tr>
                    <td class="py-1.5 pr-3 text-slate-500 font-semibold" style="width:140px">Nama Lengkap</td>
                    <td class="py-1.5 font-bold text-slate-800">: <?= e($pasien['nama']) ?></td>
                    <td class="py-1.5 pr-3 text-slate-500 font-semibold" style="width:140px">No. RM</td>
                    <td class="py-1.5 font-bold text-mint-700">: <?= e($pasien['no_rm']) ?></td>
                </tr>
                <tr>
                    <td class="py-1.5 pr-3 text-slate-500 font-semibold">NIK</td>
                    <td class="py-1.5">: <?= e($pasien['nik'] ?: '-') ?></td>
                    <td class="py-1.5 pr-3 text-slate-500 font-semibold">Jenis Kelamin</td>
                    <td class="py-1.5">: <?= e($jkLabel) ?></td>
                </tr>
                <tr>
                    <td class="py-1.5 pr-3 text-slate-500 font-semibold">Tempat, Tgl Lahir</td>
                    <td class="py-1.5">: <?= e($pasien['tempat_lahir'] ?: '-') ?>, <?= $pasien['tanggal_lahir'] ? e(date('d/m/Y', strtotime($pasien['tanggal_lahir']))) : '-' ?></td>
                    <td class="py-1.5 pr-3 text-slate-500 font-semibold">Usia</td>
                    <td class="py-1.5">: <?= e($usia) ?></td>
                </tr>
                <tr>
                    <td class="py-1.5 pr-3 text-slate-500 font-semibold">Status Peserta</td>
                    <td class="py-1.5">: <?= e($pasien['status_kepesertaan'] ?: '-') ?></td>
                    <td class="py-1.5 pr-3 text-slate-500 font-semibold">No. Telepon</td>
                    <td class="py-1.5">: <?= e($pasien['no_telepon'] ?: '-') ?></td>
                </tr>
                <tr>
                    <td class="py-1.5 pr-3 text-slate-500 font-semibold align-top">Alamat</td>
                    <td class="py-1.5" colspan="3">: <?= e($pasien['alamat'] ?: '-') ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Versi PRINT identitas pasien (tabel rapi) -->
<div class="print-only" style="margin-bottom:8pt">
    <div style="font-size:10pt;font-weight:700;border-bottom:1px solid #000;padding-bottom:2pt;margin-bottom:4pt">
        IDENTITAS PASIEN
    </div>
    <table style="width:100%;border:1px solid #333;border-collapse:collapse;font-size:9pt">
        <tr>
            <td style="border:1px solid #999;padding:2pt 4pt;width:18%;background:#f0f0f0;font-weight:700">Nama Lengkap</td>
            <td style="border:1px solid #999;padding:2pt 4pt" colspan="3"><?= e($pasien['nama']) ?></td>
        </tr>
        <tr>
            <td style="border:1px solid #999;padding:2pt 4pt;background:#f0f0f0;font-weight:700">No. RM</td>
            <td style="border:1px solid #999;padding:2pt 4pt;width:32%"><?= e($pasien['no_rm']) ?></td>
            <td style="border:1px solid #999;padding:2pt 4pt;width:18%;background:#f0f0f0;font-weight:700">NIK</td>
            <td style="border:1px solid #999;padding:2pt 4pt"><?= e($pasien['nik'] ?: '-') ?></td>
        </tr>
        <tr>
            <td style="border:1px solid #999;padding:2pt 4pt;background:#f0f0f0;font-weight:700">Tempat, Tgl Lahir</td>
            <td style="border:1px solid #999;padding:2pt 4pt"><?= e($pasien['tempat_lahir'] ?: '-') ?>, <?= $pasien['tanggal_lahir'] ? e(date('d/m/Y', strtotime($pasien['tanggal_lahir']))) : '-' ?></td>
            <td style="border:1px solid #999;padding:2pt 4pt;background:#f0f0f0;font-weight:700">Jenis Kelamin</td>
            <td style="border:1px solid #999;padding:2pt 4pt"><?= e($jkLabel) ?></td>
        </tr>
        <tr>
            <td style="border:1px solid #999;padding:2pt 4pt;background:#f0f0f0;font-weight:700">Usia</td>
            <td style="border:1px solid #999;padding:2pt 4pt"><?= e($usia) ?></td>
            <td style="border:1px solid #999;padding:2pt 4pt;background:#f0f0f0;font-weight:700">Status Peserta</td>
            <td style="border:1px solid #999;padding:2pt 4pt"><?= e($pasien['status_kepesertaan'] ?: '-') ?></td>
        </tr>
        <tr>
            <td style="border:1px solid #999;padding:2pt 4pt;background:#f0f0f0;font-weight:700">Alamat</td>
            <td style="border:1px solid #999;padding:2pt 4pt" colspan="3"><?= e($pasien['alamat'] ?: '-') ?></td>
        </tr>
    </table>
</div>

<!-- ============================================================
     STATISTIK (HANYA TAMPIL DI LAYAR)
     ============================================================ -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 screen-only">
    <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total Kunjungan</p>
        <h3 class="text-2xl font-bold text-slate-900 mt-1"><?= number_format($totalKunjungan) ?></h3>
    </div>
    <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Kunjungan Terakhir</p>
        <h3 class="text-base font-bold text-slate-900 mt-1">
            <?= $kunjunganTerakhir ? e(date('d/m/Y', strtotime($kunjunganTerakhir['tanggal_kunjungan']))) : '-' ?>
        </h3>
    </div>
    <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Kunjungan Pertama</p>
        <h3 class="text-base font-bold text-slate-900 mt-1">
            <?= $kunjunganPertama ? e(date('d/m/Y', strtotime($kunjunganPertama['tanggal_kunjungan']))) : '-' ?>
        </h3>
    </div>
    <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total Kunjungan</p>
        <h3 class="text-2xl font-bold text-slate-900 mt-1"><?= number_format($totalKunjungan) ?>x</h3>
    </div>
</div>

<!-- ============================================================
     RIWAYAT KUNJUNGAN — LAYAR (timeline)
     ============================================================ -->
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm screen-only">
    <div class="p-5 border-b border-slate-100 flex items-center justify-between flex-wrap gap-2">
        <div class="flex items-center gap-2">
            <i class="ri-history-line text-mint-600 text-lg"></i>
            <h3 class="font-bold text-slate-900 text-base">Riwayat Kunjungan</h3>
        </div>
        <span class="text-xs text-slate-500">
            Urut dari <b>terbaru</b> ke <b>terlama</b>
        </span>
    </div>

    <?php if (!$kunjunganList): ?>
        <div class="p-10 text-center text-slate-400">
            <i class="ri-inbox-line text-4xl block mb-2"></i>
            <p class="text-sm">Belum ada kunjungan untuk pasien ini.</p>
        </div>
    <?php else: ?>
    <div class="p-5 space-y-5">
        <?php foreach (array_reverse($kunjunganList) as $idx => $k):
            $kid = (int)$k['id'];
            $detail = $detailMap[$kid] ?? [];
            $periksa = $detail['pemeriksaan'] ?? null;
            $diagnosis = $detail['diagnosis'] ?? [];
            $tindakan = $detail['tindakan'] ?? [];
            $resepList = $detail['resep'] ?? [];
            $lab = $detail['lab'] ?? [];

            $umurSaatItu = '-';
            if (!empty($pasien['tanggal_lahir'])) {
                $lahir = new DateTime($pasien['tanggal_lahir']);
                $kunj  = new DateTime($k['tanggal_kunjungan']);
                $diff  = $lahir->diff($kunj);
                $umurSaatItu = $diff->y . ' th ' . $diff->m . ' bl ' . $diff->d . ' hr';
            }
        ?>
        <div class="relative pl-6 border-l-2 border-mint-200 timeline-item">
            <div class="absolute -left-[9px] top-1 w-4 h-4 rounded-full bg-mint-500 border-2 border-white shadow-md timeline-dot"></div>

            <div class="bg-slate-50/50 rounded-2xl border border-slate-100 overflow-hidden">
                <div class="p-4 bg-gradient-to-r from-mint-50/70 to-white border-b border-slate-100">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-3 flex-wrap">
                            <div class="px-3 py-1.5 bg-white rounded-lg border border-mint-200 shadow-sm">
                                <div class="text-[10px] text-slate-400 font-semibold uppercase">Kunjungan <?= $totalKunjungan - $idx ?></div>
                                <div class="font-bold text-slate-800 text-sm"><?= e(date('d M Y', strtotime($k['tanggal_kunjungan']))) ?></div>
                                <div class="text-[11px] text-slate-500"><?= e(substr($k['jam_kunjungan'] ?? '', 0, 5)) ?></div>
                            </div>
                            <div class="text-xs">
                                <div class="text-slate-400 font-semibold uppercase text-[10px]">Usia saat itu</div>
                                <div class="text-slate-700 font-semibold"><?= e($umurSaatItu) ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-2 text-[11px] text-slate-500">
                        <code class="text-mint-600"><?= e($k['no_transaksi']) ?></code>
                        &middot; Sumber: <?= e($k['sumber_data'] ?: '-') ?>
                        &middot; Petugas: <?= e($k['petugas_input'] ?: '-') ?>
                    </div>
                </div>

                <div class="p-4 space-y-3">
                    <?php if ($periksa): ?>
                    <div>
                        <div class="text-[11px] font-semibold uppercase text-slate-400 mb-1.5">Tanda Vital</div>
                        <div class="grid grid-cols-3 sm:grid-cols-6 gap-2">
                            <?php
                            $vitals = [
                                ['label'=>'Kesadaran', 'val'=>$periksa['kesadaran']],
                                ['label'=>'TD',        'val'=>$periksa['tekanan_darah']],
                                ['label'=>'Nadi',      'val'=>$periksa['nadi'] ? $periksa['nadi'].' x/mnt' : null],
                                ['label'=>'RR',        'val'=>$periksa['respiratory_rate'] ? $periksa['respiratory_rate'].' x/mnt' : null],
                                ['label'=>'Suhu',      'val'=>$periksa['suhu'] ? $periksa['suhu'].'°C' : null],
                                ['label'=>'TB/BB',     'val'=>trim(($periksa['tinggi_badan'] ?? '').' / '.($periksa['berat_badan'] ?? ''), ' /')],
                            ];
                            foreach ($vitals as $v): ?>
                            <div class="bg-white rounded-lg border border-slate-100 p-2.5">
                                <div class="text-[10px] text-slate-400 uppercase font-semibold"><?= e($v['label']) ?></div>
                                <div class="text-xs font-bold text-slate-800 mt-0.5"><?= $v['val'] ? e($v['val']) : '-' ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($diagnosis): ?>
                    <div>
                        <div class="text-[11px] font-semibold uppercase text-slate-400 mb-1.5">Diagnosis (<?= count($diagnosis) ?>)</div>
                        <div class="flex flex-wrap gap-1.5">
                            <?php foreach ($diagnosis as $dx): ?>
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold border <?= $dx['jenis_diagnosis'] === 'Utama' ? 'bg-mint-50 text-mint-700 border-mint-200' : 'bg-slate-50 text-slate-600 border-slate-200' ?>">
                                    <?php if ($dx['kode_icd']): ?><code class="text-[10px]"><?= e($dx['kode_icd']) ?></code><?php endif; ?>
                                    <?= e($dx['nama_diagnosis']) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($tindakan): ?>
                    <div>
                        <div class="text-[11px] font-semibold uppercase text-slate-400 mb-1.5">Tindakan</div>
                        <div class="space-y-1">
                            <?php foreach ($tindakan as $t): ?>
                                <div class="flex items-center gap-2 text-xs bg-white rounded-lg border border-slate-100 p-2">
                                    <i class="ri-checkbox-circle-fill text-mint-500"></i>
                                    <span class="font-semibold text-slate-800"><?= e($t['nama_tindakan']) ?></span>
                                    <?php if ($t['nama_tenaga']): ?><span class="text-slate-500">— <?= e($t['nama_tenaga']) ?></span><?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($resepList): ?>
                    <div>
                        <div class="text-[11px] font-semibold uppercase text-slate-400 mb-1.5">Resep Obat</div>
                        <div class="space-y-1.5">
                            <?php foreach ($resepList as $r): ?>
                                <div class="bg-white rounded-lg border border-slate-100 p-2.5">
                                    <?php if ($r['nama_dokter']): ?>
                                        <div class="text-[10px] text-slate-400 mb-1">Dokter: <b class="text-slate-600"><?= e($r['nama_dokter']) ?></b></div>
                                    <?php endif; ?>
                                    <div class="flex flex-wrap gap-1.5">
                                        <?php foreach ($r['detail'] as $rd): ?>
                                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-mint-50 text-mint-800 text-[11px] border border-mint-200">
                                                <b><?= e($rd['nama_obat'] ?: $rd['nama_obat_snapshot']) ?></b>
                                                <?php if ($rd['jumlah']): ?><span class="text-mint-600">(<?= e($rd['jumlah']) ?>)</span><?php endif; ?>
                                                <?php if ($rd['aturan_pakai']): ?><span class="text-slate-500">&middot; <?= e($rd['aturan_pakai']) ?></span><?php endif; ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($lab): ?>
                    <div>
                        <div class="text-[11px] font-semibold uppercase text-slate-400 mb-1.5">Laboratorium</div>
                        <div class="space-y-1.5">
                            <?php foreach ($lab as $l): ?>
                                <div class="bg-white rounded-lg border border-slate-100 p-2.5">
                                    <div class="text-[10px] text-slate-400 mb-1">
                                        <?= $l['tanggal'] ? e(date('d/m/Y', strtotime($l['tanggal']))) : '-' ?>
                                        <?= e(substr($l['jam'] ?? '', 0, 5)) ?>
                                    </div>
                                    <div class="text-[11px] text-slate-700 whitespace-pre-wrap font-mono leading-relaxed"><?= e($l['hasil'] ?: '-') ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!$periksa && !$diagnosis && !$tindakan && !$resepList && !$lab): ?>
                        <div class="text-center text-xs text-slate-400 py-3 italic">Tidak ada detail pemeriksaan tercatat.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ============================================================
     RIWAYAT KUNJUNGAN — PRINT (tabel rapi per kunjungan)
     ============================================================ -->
<div class="print-only">
    <div style="font-size:10pt;font-weight:700;border-bottom:1px solid #000;padding-bottom:2pt;margin:8pt 0 4pt 0">
        RIWAYAT KUNJUNGAN (<?= $totalKunjungan ?>x)
    </div>

    <?php if (!$kunjunganList): ?>
        <div style="text-align:center;font-style:italic;color:#666;padding:10pt">Belum ada kunjungan.</div>
    <?php else: ?>
        <?php foreach ($kunjunganList as $idx => $k):
            $kid = (int)$k['id'];
            $detail = $detailMap[$kid] ?? [];
            $periksa = $detail['pemeriksaan'] ?? null;
            $diagnosis = $detail['diagnosis'] ?? [];
            $tindakan = $detail['tindakan'] ?? [];
            $resepList = $detail['resep'] ?? [];
            $lab = $detail['lab'] ?? [];

            $umurSaatItu = '-';
            if (!empty($pasien['tanggal_lahir'])) {
                $lahir = new DateTime($pasien['tanggal_lahir']);
                $kunj  = new DateTime($k['tanggal_kunjungan']);
                $diff  = $lahir->diff($kunj);
                $umurSaatItu = $diff->y . ' th';
            }
        ?>
        <div style="border:1px solid #333;margin-bottom:6pt;page-break-inside:avoid">
            <!-- Header kunjungan -->
            <div style="background:#e8e8e8;border-bottom:1px solid #333;padding:3pt 5pt;display:flex;justify-content:space-between">
                <div>
                    <b style="font-size:10pt">Kunjungan #<?= $idx + 1 ?></b>
                    &nbsp;|&nbsp;
                    <?= e(date('d/m/Y', strtotime($k['tanggal_kunjungan']))) ?>
                    <?= e(substr($k['jam_kunjungan'] ?? '', 0, 5)) ?>
                    &nbsp;|&nbsp; Usia: <?= e($umurSaatItu) ?>
                </div>
                <div style="font-size:8pt">
                    No.Trx: <?= e($k['no_transaksi']) ?>
                </div>
            </div>

            <div style="padding:4pt 6pt">
                <!-- Tanda Vital -->
                <?php if ($periksa): ?>
                <div style="margin-bottom:4pt">
                    <div style="font-weight:700;font-size:9pt;margin-bottom:2pt">Tanda Vital:</div>
                    <table style="width:100%;border-collapse:collapse;font-size:8.5pt">
                        <tr>
                            <td style="border:1px solid #999;padding:1.5pt 3pt;background:#f5f5f5;width:14%">Kesadaran</td>
                            <td style="border:1px solid #999;padding:1.5pt 3pt;width:19%"><?= e($periksa['kesadaran'] ?: '-') ?></td>
                            <td style="border:1px solid #999;padding:1.5pt 3pt;background:#f5f5f5;width:14%">Nadi</td>
                            <td style="border:1px solid #999;padding:1.5pt 3pt;width:19%"><?= $periksa['nadi'] ? e($periksa['nadi']) . ' x/mnt' : '-' ?></td>
                            <td style="border:1px solid #999;padding:1.5pt 3pt;background:#f5f5f5;width:14%">TD</td>
                            <td style="border:1px solid #999;padding:1.5pt 3pt;width:20%"><?= e($periksa['tekanan_darah'] ?: '-') ?></td>
                        </tr>
                        <tr>
                            <td style="border:1px solid #999;padding:1.5pt 3pt;background:#f5f5f5">RR</td>
                            <td style="border:1px solid #999;padding:1.5pt 3pt"><?= $periksa['respiratory_rate'] ? e($periksa['respiratory_rate']) . ' x/mnt' : '-' ?></td>
                            <td style="border:1px solid #999;padding:1.5pt 3pt;background:#f5f5f5">Suhu</td>
                            <td style="border:1px solid #999;padding:1.5pt 3pt"><?= $periksa['suhu'] ? e($periksa['suhu']) . ' °C' : '-' ?></td>
                            <td style="border:1px solid #999;padding:1.5pt 3pt;background:#f5f5f5">TB/BB</td>
                            <td style="border:1px solid #999;padding:1.5pt 3pt"><?= e(trim(($periksa['tinggi_badan'] ?? '') . '/' . ($periksa['berat_badan'] ?? ''), '/') ?: '-') ?></td>
                        </tr>
                    </table>
                </div>
                <?php endif; ?>

                <!-- Diagnosis -->
                <?php if ($diagnosis): ?>
                <div style="margin-bottom:4pt">
                    <div style="font-weight:700;font-size:9pt;margin-bottom:2pt">Diagnosis:</div>
                    <table style="width:100%;border-collapse:collapse;font-size:8.5pt">
                        <thead>
                            <tr>
                                <th style="border:1px solid #999;padding:1.5pt 3pt;background:#f0f0f0;width:15%">Kode ICD</th>
                                <th style="border:1px solid #999;padding:1.5pt 3pt;background:#f0f0f0">Nama Diagnosis</th>
                                <th style="border:1px solid #999;padding:1.5pt 3pt;background:#f0f0f0;width:18%">Jenis</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($diagnosis as $dx): ?>
                            <tr>
                                <td style="border:1px solid #999;padding:1.5pt 3pt"><?= e($dx['kode_icd'] ?: '-') ?></td>
                                <td style="border:1px solid #999;padding:1.5pt 3pt"><?= e($dx['nama_diagnosis']) ?></td>
                                <td style="border:1px solid #999;padding:1.5pt 3pt"><?= e($dx['jenis_diagnosis']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <!-- Tindakan -->
                <?php if ($tindakan): ?>
                <div style="margin-bottom:4pt">
                    <div style="font-weight:700;font-size:9pt;margin-bottom:2pt">Tindakan:</div>
                    <table style="width:100%;border-collapse:collapse;font-size:8.5pt">
                        <thead>
                            <tr>
                                <th style="border:1px solid #999;padding:1.5pt 3pt;background:#f0f0f0">Nama Tindakan</th>
                                <th style="border:1px solid #999;padding:1.5pt 3pt;background:#f0f0f0;width:35%">Pelaksana</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tindakan as $t): ?>
                            <tr>
                                <td style="border:1px solid #999;padding:1.5pt 3pt"><?= e($t['nama_tindakan']) ?></td>
                                <td style="border:1px solid #999;padding:1.5pt 3pt"><?= e($t['nama_tenaga'] ?: '-') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <!-- Resep -->
                <?php if ($resepList): ?>
                <div style="margin-bottom:4pt">
                    <div style="font-weight:700;font-size:9pt;margin-bottom:2pt">Resep Obat:</div>
                    <?php foreach ($resepList as $r): ?>
                        <?php if ($r['nama_dokter']): ?>
                            <div style="font-size:8pt;color:#555;margin-bottom:1pt">Dokter: <?= e($r['nama_dokter']) ?></div>
                        <?php endif; ?>
                        <table style="width:100%;border-collapse:collapse;font-size:8.5pt;margin-bottom:3pt">
                            <thead>
                                <tr>
                                    <th style="border:1px solid #999;padding:1.5pt 3pt;background:#f0f0f0;width:5%">#</th>
                                    <th style="border:1px solid #999;padding:1.5pt 3pt;background:#f0f0f0">Nama Obat</th>
                                    <th style="border:1px solid #999;padding:1.5pt 3pt;background:#f0f0f0;width:12%">Jumlah</th>
                                    <th style="border:1px solid #999;padding:1.5pt 3pt;background:#f0f0f0;width:25%">Aturan Pakai</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($r['detail'] as $i => $rd): ?>
                                <tr>
                                    <td style="border:1px solid #999;padding:1.5pt 3pt;text-align:center"><?= $i + 1 ?></td>
                                    <td style="border:1px solid #999;padding:1.5pt 3pt"><?= e($rd['nama_obat'] ?: $rd['nama_obat_snapshot']) ?></td>
                                    <td style="border:1px solid #999;padding:1.5pt 3pt"><?= e($rd['jumlah'] ?: '-') ?></td>
                                    <td style="border:1px solid #999;padding:1.5pt 3pt"><?= e($rd['aturan_pakai'] ?: '-') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Lab -->
                <?php if ($lab): ?>
                <div style="margin-bottom:4pt">
                    <div style="font-weight:700;font-size:9pt;margin-bottom:2pt">Laboratorium:</div>
                    <?php foreach ($lab as $l): ?>
                        <div style="font-size:8pt;color:#555;margin-bottom:1pt">
                            <?= $l['tanggal'] ? e(date('d/m/Y', strtotime($l['tanggal']))) : '-' ?>
                        </div>
                        <div style="font-size:8.5pt;padding:2pt 4pt;border:1px solid #999;background:#fafafa;white-space:pre-wrap"><?= e($l['hasil'] ?: '-') ?></div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Tanda tangan -->
    <div style="margin-top:14pt;font-size:9pt">
        <div style="display:flex;justify-content:space-between">
            <div>
                Dicetak: <?= e(date('d/m/Y H:i:s')) ?> WIB
            </div>
            <div style="text-align:center">
                <div>Jakarta, <?= e(date('d/m/Y')) ?></div>
                <div style="margin-top:2pt">Dokter Pemeriksa,</div>
                <div style="height:40pt"></div>
                <div style="border-top:1px solid #000;padding-top:2pt;min-width:150pt">
                    (...........................................)
                </div>
            </div>
        </div>
    </div>
</div>

<?php require BASE_PATH . '/layouts/footer.php'; ?>

<script>
function cetakResume() {
    var originalTitle = document.title;
    document.title = 'Resume Medis - <?= e($pasien['no_rm']) ?> - <?= e($pasien['nama']) ?>';
    setTimeout(function () {
        window.print();
        setTimeout(function () {
            document.title = originalTitle;
        }, 1000);
    }, 100);
}

document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
        e.preventDefault();
        cetakResume();
    }
});
</script>