<?php
/**
 * file   : actions/simpan-hasil.php
 * fungsi : Menyimpan data s_backup_raw ke tabel relasional secara bertahap (Cursor / Last ID) - Fully Sanitized & Fixed Stats
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
$limit  = 10000; 

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
    
    // Kumpulkan SEMUA jenis dokter/nakes agar otomatis terdaftar di m_tenaga_kesehatan
    if (!empty($r['dokter'])) $dokterList[trim($r['dokter'])] = true;
    if (!empty($r['resep_dokter'])) $dokterList[trim($r['resep_dokter'])] = true;
    if (!empty($r['nama_pelaksana'])) $dokterList[trim($r['nama_pelaksana'])] = true;
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
                $stats['m_pasien']++; // Tetap hitung pasien yang terproses pada chunk ini
            } else {
                $stmtInsPasien->execute([
                    ':no_rm'      => substr((string)$noRm, 0, 50),
                    ':nik'        => substr((string)($r['nik'] ?? ''), 0, 30),
                    ':asuransi'   => substr((string)($r['no_asuransi_lain'] ?? ''), 0, 50),
                    ':nama'       => substr((string)($r['nama'] ?? '-'), 0, 150),
                    ':tempat'     => substr((string)($r['tempat_lahir'] ?? ''), 0, 100),
                    ':tgl_lahir'  => $r['tanggal_lahir'] ?? null,
                    ':jk'         => $r['jenis_kelamin'] ?? null,
                    ':kawin'      => $r['status_perkawinan'] ?? null,
                    ':pendidikan' => substr((string)($r['pendidikan'] ?? ''), 0, 50),
                    ':pekerjaan'  => substr((string)($r['pekerjaan'] ?? ''), 0, 50),
                    ':agama'      => substr((string)($r['agama'] ?? ''), 0, 50),
                    ':alamat'     => substr((string)($r['alamat'] ?? ''), 0, 255),
                    ':rt'         => substr((string)($r['rt'] ?? ''), 0, 10),
                    ':rw'         => substr((string)($r['rw'] ?? ''), 0, 10),
                    ':kel'        => substr((string)($r['kode_kelurahan'] ?? ''), 0, 20),
                    ':kec'        => substr((string)($r['kode_kecamatan'] ?? ''), 0, 20),
                    ':kab'        => substr((string)($r['kode_kabupaten_kota'] ?? ''), 0, 20),
                    ':prov'       => substr((string)($r['kode_provinsi'] ?? ''), 0, 20),
                    ':pos'        => substr((string)($r['kode_pos'] ?? ''), 0, 10),
                    ':alamat_dom' => substr((string)($r['alamat_domisili'] ?? ''), 0, 255),
                    ':rt_dom'     => substr((string)($r['rt_domisili'] ?? ''), 0, 10),
                    ':rw_dom'     => substr((string)($r['rw_domisili'] ?? ''), 0, 10),
                    ':kel_dom'    => substr((string)($r['kode_kelurahan_domisili'] ?? ''), 0, 20),
                    ':kec_dom'    => substr((string)($r['kode_kecamatan_domisili'] ?? ''), 0, 20),
                    ':kab_dom'    => substr((string)($r['kode_kabupaten_domisili'] ?? ''), 0, 20),
                    ':prov_dom'   => substr((string)($r['kode_provinsi_domisili'] ?? ''), 0, 20),
                    ':pos_dom'    => substr((string)($r['kode_pos_domisili'] ?? ''), 0, 10),
                    ':telp'       => substr((string)($r['no_telepon'] ?? ''), 0, 50),
                    ':telp_alt'   => substr((string)($r['no_telepon_alternatif'] ?? ''), 0, 50),
                    ':peserta'    => substr((string)($r['status_kepesertaan'] ?? ''), 0, 50),
                ]);
                $pasienId = (int)$pdo->lastInsertId();
                if (!$pasienId) {
                    $stmtGetPasien = $pdo->prepare("SELECT id FROM m_pasien WHERE no_rm = :rm LIMIT 1");
                    $stmtGetPasien->execute([':rm' => $noRm]);
                    $pasienId = (int)$stmtGetPasien->fetchColumn();
                }
                if ($pasienId) {
                    $pasienMap[$noRm] = $pasienId;
                    $stats['m_pasien']++;
                }
            }
        }

        // Helper nakes aman
        $getTenagaId = function($namaDokter) use ($pdo, &$tenagaMap, $stmtInsTenaga, &$stats) {
            $namaDokter = trim((string)$namaDokter);
            if ($namaDokter === '' || strcasecmp($namaDokter, '-') === 0) return null;
            if (isset($tenagaMap[$namaDokter])) return $tenagaMap[$namaDokter];

            $stmtInsTenaga->execute([':n' => substr($namaDokter, 0, 150)]);
            $stmt = $pdo->prepare("SELECT id FROM m_tenaga_kesehatan WHERE nama = :n LIMIT 1");
            $stmt->execute([':n' => $namaDokter]);
            $tid = (int)$stmt->fetchColumn();
            
            if ($tid) {
                $tenagaMap[$namaDokter] = $tid;
                $stats['m_tenaga_kesehatan']++;
            }
            return $tid > 0 ? $tid : null;
        };

        if (!empty($r['dokter'])) {
            $getTenagaId($r['dokter']);
        }

        // --- t_kunjungan ---
        $kunjunganId = null;
        if (!empty($r['no_transaksi']) && $pasienId) {
            $stmtInsKunjungan->execute([
                ':pid'     => $pasienId,
                ':nt'      => substr((string)$r['no_transaksi'], 0, 100),
                ':tgl'     => $r['tanggal_kunjungan'] ?? null,
                ':jam'     => $r['jam_kunjungan'] ?? null,
                ':sumber'  => substr((string)($r['sumber_data'] ?? ''), 0, 50),
                ':tgl_bck' => $r['tanggal_backup'] ?? null,
                ':petugas' => substr((string)($r['petugas_input'] ?? ''), 0, 100),
            ]);
            
            $stmtK = $pdo->prepare("SELECT id FROM t_kunjungan WHERE no_transaksi = :nt LIMIT 1");
            $stmtK->execute([':nt' => $r['no_transaksi']]);
            $kunjunganId = (int)$stmtK->fetchColumn();
            
            if ($kunjunganId) {
                $stats['t_kunjungan']++; // Tetap hitung kunjungan valid pada chunk ini
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
                $diagClean = substr((string)$r['diagnosis_raw'], 0, 255);
                $stmtCheckDiag = $pdo->prepare("SELECT id FROM t_diagnosis WHERE kunjungan_id = :kid AND nama_diagnosis = :diag LIMIT 1");
                $stmtCheckDiag->execute([':kid' => $kunjunganId, ':diag' => $diagClean]);
                if (!$stmtCheckDiag->fetchColumn()) {
                    $pdo->prepare("INSERT INTO t_diagnosis (kunjungan_id, jenis_diagnosis, nama_diagnosis) VALUES (:kid, 'Utama', :diag)")
                        ->execute([':kid' => $kunjunganId, ':diag' => $diagClean]);
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
                            ':hasil' => (string)$r['lab_hasil_raw'],
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
                        ':nama'  => substr((string)$r['nama_tindakan'], 0, 255),
                        ':nakes' => $nakesId,
                        ':tgl'   => $r['tanggal_tindakan'] ?? $r['tanggal_kunjungan'] ?? date('Y-m-d'),
                    ]);
                $stats['t_tindakan']++;
            }

            // ---- t_resep & m_obat ----
            if ($kunjunganId && !empty($r['resep_nama_obat'])) {
                $namaObat = substr(trim((string)$r['resep_nama_obat']), 0, 150);
                if ($namaObat !== '') {
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
                                ':jml'    => substr((string)($r['resep_jumlah'] ?? '1'), 0, 50),
                                ':aturan' => substr((string)($r['resep_aturan'] ?? '-'), 0, 100),
                            ]);
                        $stats['d_resep_detail']++;
                    }
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

        // HANYA update batch yang sedang aktif/terakhir di-upload
        $stmtB = $pdo->query("SELECT id FROM s_upload_batch WHERE status IN ('uploaded','generated') ORDER BY uploaded_at DESC LIMIT 1");
        $batchId = $stmtB->fetchColumn();
        if ($batchId) {
            $pdo->prepare("UPDATE s_upload_batch SET result_hash = :rhash, status = 'generated', verify_detail = :detail WHERE id = :id")
                ->execute([
                    ':rhash'  => $resultHash, 
                    ':detail' => json_encode($resultHashData), 
                    ':id'     => $batchId
                ]);
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
    
    // Debug info detail baris yang error
    $debugContext = '';
    if (isset($row['raw_data'])) {
        $debugContext = '<br><br><b>Data mentah penyebab error:</b><br><code style="font-size:10px; color:#d97706; background:#fffbeb; padding:4px; display:block; max-height:100px; overflow:auto;">' . htmlspecialchars($row['raw_data']) . '</code>';
    }

    log_error('proses_simpan_hasil: ' . $e->getMessage());
    json_response([
        'ok'  => false, 
        'msg' => 'Gagal pada ID Baris <b>' . ($row['id'] ?? '-') . '</b><br>Error: ' . $e->getMessage() . $debugContext
    ], 500);
}