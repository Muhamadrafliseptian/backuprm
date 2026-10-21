<?php
/**
 * file   : actions/simpan-hasil.php
 * fungsi : Menyimpan data s_backup_raw ke tabel relasional secara bertahap (Cursor / Last ID)
 */
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/internal_guard.php';
require_once BASE_PATH . '/core/parser_sql.php';

// Bypass CSRF untuk batching request berulang
// csrf_check_or_die();

set_time_limit(300);
ini_set('memory_limit', '1024M');

if (!auth_check()) {
    json_response(['ok' => false, 'msg' => 'Unauthorized'], 401);
}

$lastId = (int)($_POST['last_id'] ?? 0);
$limit  = 2000; 

$pdo = db();

$totalRows = (int)$pdo->query("SELECT COUNT(*) FROM s_backup_raw")->fetchColumn();
if ($totalRows === 0) {
    json_response(['ok' => false, 'msg' => 'Tidak ada data di s_backup_raw.'], 400);
}

$stmtFetch = $pdo->prepare("SELECT id, source_row_id, raw_data FROM s_backup_raw WHERE id > :last_id ORDER BY id ASC LIMIT :lim");
$stmtFetch->bindValue(':last_id', $lastId, PDO::PARAM_INT);
$stmtFetch->bindValue(':lim', $limit, PDO::PARAM_INT);
$stmtFetch->execute();
$rows = $stmtFetch->fetchAll();

if (empty($rows)) {
    json_response([
        'ok'          => true,
        'finished'    => true,
        'processed'   => $lastId,
        'total'       => $totalRows,
        'stats'       => [],
        'result_hash' => ''
    ]);
}

$newLastId = (int)end($rows)['id'];

$stats = [
    'm_pasien' => 0, 't_kunjungan' => 0, 't_pemeriksaan' => 0,
    't_diagnosis' => 0, 't_tindakan' => 0, 'm_tenaga_kesehatan' => 0,
    't_resep' => 0, 'd_resep_detail' => 0, 'm_obat' => 0, 't_laboratorium' => 0,
];

$parsedRows = [];
$noRmList = [];
$dokterList = [];

foreach ($rows as $row) {
    $r = sql_parse_row($row['raw_data']);
    if (!sql_row_valid($r)) continue;

    if (sql_row_has_lab($r)) {
        $r['lab_hasil_raw'] = sql_extract_lab_hasil($r);
    }

    $parsedRows[] = $r;
    if (!empty($r['no_rm'])) $noRmList[$r['no_rm']] = true;
    if (!empty($r['dokter'])) $dokterList[$r['dokter']] = true;
    if (!empty($r['resep_dokter'])) $dokterList[$r['resep_dokter']] = true;
    if (!empty($r['nama_pelaksana'])) $dokterList[$r['nama_pelaksana']] = true;
}

$pdo->beginTransaction();

try {
    // 1. Bulk Lookup Pasien
    $pasienMap = [];
    if (!empty($noRmList)) {
        $inRm = array_keys($noRmList);
        $placeholders = implode(',', array_fill(0, count($inRm), '?'));
        $stmtMap = $pdo->prepare("SELECT no_rm, id FROM m_pasien WHERE no_rm IN ($placeholders)");
        $stmtMap->execute($inRm);
        $pasienMap = $stmtMap->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    // 2. Bulk Lookup Tenaga Kesehatan
    $tenagaMap = [];
    if (!empty($dokterList)) {
        $inDokter = array_keys($dokterList);
        $placeholders = implode(',', array_fill(0, count($inDokter), '?'));
        $stmtMap = $pdo->prepare("SELECT nama, id FROM m_tenaga_kesehatan WHERE nama IN ($placeholders)");
        $stmtMap->execute($inDokter);
        $tenagaMap = $stmtMap->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    $stmtInsPasien = $pdo->prepare("INSERT IGNORE INTO m_pasien
        (no_rm, nik, no_asuransi_lain, nama, tempat_lahir, tanggal_lahir,
         jenis_kelamin, status_perkawinan, pendidikan, pekerjaan, agama,
         alamat, rt, rw, kode_kelurahan, kode_kecamatan,
         kode_kabupaten_kota, kode_provinsi, kode_pos,
         alamat_domisili, rt_domisili, rw_domisili,
         kode_kelurahan_domisili, kode_kecamatan_domisili,
         kode_kabupaten_domisili, kode_provinsi_domisili,
         kode_pos_domisili, no_telepon, no_telepon_alternatif, status_kepesertaan)
        VALUES
        (:no_rm, :nik, :asuransi, :nama, :tempat, :tgl_lahir,
         :jk, :kawin, :pendidikan, :pekerjaan, :agama,
         :alamat, :rt, :rw, :kel, :kec, :kab, :prov, :pos,
         :alamat_dom, :rt_dom, :rw_dom, :kel_dom, :kec_dom,
         :kab_dom, :prov_dom, :pos_dom, :telp, :telp_alt, :peserta)");

    $stmtInsTenaga = $pdo->prepare("INSERT IGNORE INTO m_tenaga_kesehatan (nama, jenis_tenaga) VALUES (:n, 'Dokter')");
    $stmtInsKunjungan = $pdo->prepare("INSERT IGNORE INTO t_kunjungan
        (pasien_id, no_transaksi, tanggal_kunjungan, jam_kunjungan, sumber_data, tanggal_backup, petugas_input, status)
        VALUES (:pid, :nt, :tgl, :jam, :sumber, :tgl_bck, :petugas, 'selesai')");

    foreach ($parsedRows as $r) {
        // --- m_pasien ---
        $noRm = $r['no_rm'] ?? null;
        $pasienId = null;

        if ($noRm) {
            if (isset($pasienMap[$noRm])) {
                $pasienId = $pasienMap[$noRm];
            } else {
                $stmtInsPasien->execute([
                    ':no_rm'      => $noRm,
                    ':nik'        => $r['nik'] ?? null,
                    ':asuransi'   => $r['no_asuransi_lain'] ?? null,
                    ':nama'       => $r['nama'] ?? '-',
                    ':tempat'     => $r['tempat_lahir'] ?? null,
                    ':tgl_lahir'  => $r['tanggal_lahir'] ?? null,
                    ':jk'         => $r['jenis_kelamin'] ?? null,
                    ':kawin'      => $r['status_perkawinan'] ?? null,
                    ':pendidikan' => $r['pendidikan'] ?? null,
                    ':pekerjaan'  => $r['pekerjaan'] ?? null,
                    ':agama'      => $r['agama'] ?? null,
                    ':alamat'     => $r['alamat'] ?? null,
                    ':rt'         => $r['rt'] ?? null,
                    ':rw'         => $r['rw'] ?? null,
                    ':kel'        => $r['kode_kelurahan'] ?? null,
                    ':kec'        => $r['kode_kecamatan'] ?? null,
                    ':kab'        => $r['kode_kabupaten_kota'] ?? null,
                    ':prov'       => $r['kode_provinsi'] ?? null,
                    ':pos'        => $r['kode_pos'] ?? null,
                    ':alamat_dom' => $r['alamat_domisili'] ?? null,
                    ':rt_dom'     => $r['rt_domisili'] ?? null,
                    ':rw_dom'     => $r['rw_domisili'] ?? null,
                    ':kel_dom'    => $r['kode_kelurahan_domisili'] ?? null,
                    ':kec_dom'    => $r['kode_kecamatan_domisili'] ?? null,
                    ':kab_dom'    => $r['kode_kabupaten_domisili'] ?? null,
                    ':prov_dom'   => $r['kode_provinsi_domisili'] ?? null,
                    ':pos_dom'    => $r['kode_pos_domisili'] ?? null,
                    ':telp'       => $r['no_telepon'] ?? null,
                    ':telp_alt'   => $r['no_telepon_alternatif'] ?? null,
                    ':peserta'    => $r['status_kepesertaan'] ?? null,
                ]);
                $pasienId = (int)$pdo->lastInsertId();
                if ($pasienId) {
                    $pasienMap[$noRm] = $pasienId;
                    $stats['m_pasien']++;
                }
            }
        }

        // Helper nakes
        $getTenagaId = function($namaDokter) use ($pdo, &$tenagaMap, $stmtInsTenaga, &$stats) {
            if (empty($namaDokter)) return null;
            if (isset($tenagaMap[$namaDokter])) return $tenagaMap[$namaDokter];

            $stmtInsTenaga->execute([':n' => $namaDokter]);
            $stmt = $pdo->prepare("SELECT id FROM m_tenaga_kesehatan WHERE nama = :n LIMIT 1");
            $stmt->execute([':n' => $namaDokter]);
            $tid = (int)$stmt->fetchColumn();
            
            if ($tid) {
                $tenagaMap[$namaDokter] = $tid;
                $stats['m_tenaga_kesehatan']++;
            }
            return $tid;
        };

        if (!empty($r['dokter'])) {
            $getTenagaId($r['dokter']);
        }

        // --- t_kunjungan ---
        $kunjunganId = null;
        if (!empty($r['no_transaksi']) && $pasienId) {
            $stmtInsKunjungan->execute([
                ':pid'     => $pasienId,
                ':nt'      => $r['no_transaksi'],
                ':tgl'     => $r['tanggal_kunjungan'] ?? null,
                ':jam'     => $r['jam_kunjungan'] ?? null,
                ':sumber'  => $r['sumber_data'] ?? null,
                ':tgl_bck' => $r['tanggal_backup'] ?? null,
                ':petugas' => $r['petugas_input'] ?? null,
            ]);
            
            $stmtK = $pdo->prepare("SELECT id FROM t_kunjungan WHERE no_transaksi = :nt LIMIT 1");
            $stmtK->execute([':nt' => $r['no_transaksi']]);
            $kunjunganId = (int)$stmtK->fetchColumn();
            if ($kunjunganId && $stmtInsKunjungan->rowCount() > 0) {
                $stats['t_kunjungan']++;
            }

            // ---- t_pemeriksaan ----
            if ($kunjunganId && (!empty($r['tekanan_darah']) || !empty($r['suhu']) || !empty($r['nadi']))) {
                $stmtCheck = $pdo->prepare("SELECT id FROM t_pemeriksaan WHERE kunjungan_id = :kid LIMIT 1");
                $stmtCheck->execute([':kid' => $kunjunganId]);
                if (!$stmtCheck->fetchColumn()) {
                    $pdo->prepare("INSERT INTO t_pemeriksaan
                        (kunjungan_id, kesadaran, nadi, respiratory_rate, tekanan_darah, sistole, diastole, suhu, tinggi_badan, berat_badan)
                        VALUES (:kid, :kes, :nadi, :rr, :td, :sis, :dias, :suhu, :tb, :bb)")
                    ->execute([
                        ':kid'  => $kunjunganId,
                        ':kes'  => substr((string)($r['kesadaran'] ?? ''), 0, 90),
                        ':nadi' => substr((string)($r['nadi'] ?? ''), 0, 30),
                        ':rr'   => substr((string)($r['respiratory_rate'] ?? ''), 0, 30),
                        ':td'   => substr((string)($r['tekanan_darah'] ?? ''), 0, 30),
                        ':sis'  => substr((string)($r['sistole'] ?? ''), 0, 20),
                        ':dias' => substr((string)($r['diastole'] ?? ''), 0, 20),
                        ':suhu' => substr((string)($r['suhu'] ?? ''), 0, 30),
                        ':tb'   => substr((string)($r['tinggi_badan'] ?? ''), 0, 30),
                        ':bb'   => substr((string)($r['berat_badan'] ?? ''), 0, 30),
                    ]);
                    $stats['t_pemeriksaan']++;
                }
            }

            // ---- t_diagnosis ----
            if ($kunjunganId && !empty($r['diagnosis_raw'])) {
                $stmtCheckDiag = $pdo->prepare("SELECT id FROM t_diagnosis WHERE kunjungan_id = :kid AND nama_diagnosis = :diag LIMIT 1");
                $stmtCheckDiag->execute([':kid' => $kunjunganId, ':diag' => $r['diagnosis_raw']]);
                if (!$stmtCheckDiag->fetchColumn()) {
                    $pdo->prepare("INSERT INTO t_diagnosis (kunjungan_id, jenis_diagnosis, nama_diagnosis) VALUES (:kid, 'Utama', :diag)")
                        ->execute([':kid' => $kunjunganId, ':diag' => $r['diagnosis_raw']]);
                    $stats['t_diagnosis']++;
                }
            }

            // ---- t_laboratorium ----
            if ($kunjunganId && !empty($r['lab_hasil_raw'])) {
                $stmtCheckLab = $pdo->prepare("SELECT id FROM t_laboratorium WHERE kunjungan_id = :kid LIMIT 1");
                $stmtCheckLab->execute([':kid' => $kunjunganId]);
                if (!$stmtCheckLab->fetchColumn()) {
                    $pdo->prepare("INSERT INTO t_laboratorium (kunjungan_id, ada_data, tanggal, hasil) VALUES (:kid, 1, :tgl, :hasil)")
                        ->execute([
                            ':kid'   => $kunjunganId,
                            ':tgl'   => $r['tanggal_kunjungan'] ?? date('Y-m-d'),
                            ':hasil' => $r['lab_hasil_raw'],
                        ]);
                    $stats['t_laboratorium']++;
                }
            }

            // ---- t_tindakan ----
            if ($kunjunganId && !empty($r['nama_tindakan'])) {
                $nakesId = $getTenagaId($r['nama_pelaksana']);
                $pdo->prepare("INSERT INTO t_tindakan (kunjungan_id, nama_tindakan, tenaga_kesehatan_id, tanggal_tindakan) VALUES (:kid, :nama, :nakes, :tgl)")
                    ->execute([
                        ':kid'   => $kunjunganId,
                        ':nama'  => $r['nama_tindakan'],
                        ':nakes' => $nakesId,
                        ':tgl'   => $r['tanggal_tindakan'] ?? $r['tanggal_kunjungan'] ?? date('Y-m-d'),
                    ]);
                $stats['t_tindakan']++;
            }

            // ---- t_resep & m_obat ----
            if ($kunjunganId && !empty($r['resep_nama_obat'])) {
                $namaObat = trim($r['resep_nama_obat']);
                $stmtCekObat = $pdo->prepare("SELECT id FROM m_obat WHERE nama_obat = :nama LIMIT 1");
                $stmtCekObat->execute([':nama' => $namaObat]);
                $obatId = (int)$stmtCekObat->fetchColumn();

                if (!$obatId) {
                    $pdo->prepare("INSERT INTO m_obat (nama_obat, satuan) VALUES (:nama, 'Tablet')")
                        ->execute([':nama' => $namaObat]);
                    $obatId = (int)$pdo->lastInsertId();
                    if ($obatId) $stats['m_obat']++;
                }

                $dokterResepId = $getTenagaId($r['resep_dokter']);
                $stmtInsResep = $pdo->prepare("INSERT INTO t_resep (kunjungan_id, dokter_id, tanggal_resep) VALUES (:kid, :did, :tgl)");
                $stmtInsResep->execute([
                    ':kid' => $kunjunganId,
                    ':did' => $dokterResepId,
                    ':tgl' => $r['resep_tanggal'] ?? $r['tanggal_kunjungan'] ?? date('Y-m-d'),
                ]);
                $resepId = (int)$pdo->lastInsertId();
                if ($resepId) $stats['t_resep']++;

                if ($resepId && $obatId) {
                    $pdo->prepare("INSERT INTO d_resep_detail (resep_id, obat_id, jumlah, aturan_pakai) VALUES (:rid, :oid, :jml, :aturan)")
                        ->execute([
                            ':rid'    => $resepId,
                            ':oid'    => $obatId,
                            ':jml'    => $r['resep_jumlah'] ?? 1,
                            ':aturan' => $r['resep_aturan'] ?? '-',
                        ]);
                    $stats['d_resep_detail']++;
                }
            }
        }
    }

    $pdo->commit();

    $isFinished = ($newLastId >= $totalRows || count($rows) < $limit);

    $resultHash = '';
    if ($isFinished) {
        require_once BASE_PATH . '/core/hash_helper.php';
        $resultHashData = hash_generated_result();
        $resultHash     = $resultHashData['hash'];

        $stmtB = $pdo->query("SELECT id FROM s_upload_batch WHERE status IN ('uploaded','generated') ORDER BY uploaded_at DESC LIMIT 1");
        $batchId = $stmtB->fetchColumn();
        if ($batchId) {
            $pdo->prepare("UPDATE s_upload_batch SET result_hash = :rhash, status = 'generated', verify_detail = :detail WHERE id = :id")
                ->execute([':rhash' => $resultHash, ':detail' => json_encode($resultHashData), ':id' => $batchId]);
        }
    }

    json_response([
        'ok'          => true,
        'finished'    => $isFinished,
        'last_id'     => $newLastId,
        'total'       => $totalRows,
        'stats'       => $stats,
        'result_hash' => $resultHash,
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    log_error('proses_simpan_hasil: ' . $e->getMessage());
    json_response(['ok' => false, 'msg' => 'Gagal: ' . $e->getMessage()], 500);
}