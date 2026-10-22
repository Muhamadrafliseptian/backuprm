<?php
/**
 * file   : actions/simpan-hasil.php
 * fungsi : Menyimpan data s_backup_raw ke tabel relasional secara bertahap (Fully Sanitized & Safe Lengths for ALL fields)
 */
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/internal_guard.php';
require_once BASE_PATH . '/core/parser_sql.php';

set_time_limit(300);
ini_set('memory_limit', '1024M');

if (!auth_check()) {
    json_response(['ok' => false, 'msg' => 'Unauthorized'], 401);
}

// Helper universal untuk membatasi panjang string secara aman
$safe_str = function($val, int $maxLen): ?string {
    if ($val === null) return null;
    $str = trim((string)$val);
    if ($str === '' || strcasecmp($str, 'null') === 0) return null;
    return mb_substr($str, 0, $maxLen, 'UTF-8');
};

$lastId  = (int)($_POST['last_id'] ?? 0);
$batchId = trim($_POST['batch_id'] ?? '');
$limit   = 10000; 

$pdo = db();

if ($batchId !== '') {
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM s_backup_raw WHERE batch_id = :bid");
    $stmtCount->execute([':bid' => $batchId]);
    $totalRows = (int)$stmtCount->fetchColumn();
} else {
    $totalRows = (int)$pdo->query("SELECT COUNT(*) FROM s_backup_raw")->fetchColumn();
}

if ($totalRows === 0) {
    json_response(['ok' => false, 'msg' => 'Tidak ada data di s_backup_raw.'], 400);
}

if ($batchId !== '') {
    $stmtFetch = $pdo->prepare("SELECT id, source_row_id, raw_data FROM s_backup_raw WHERE batch_id = :bid AND id > :last_id ORDER BY id ASC LIMIT :lim");
    $stmtFetch->bindValue(':bid', $batchId, PDO::PARAM_STR);
} else {
    $stmtFetch = $pdo->prepare("SELECT id, source_row_id, raw_data FROM s_backup_raw WHERE id > :last_id ORDER BY id ASC LIMIT :lim");
}

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
    
    if (!empty($r['dokter'])) $dokterList[trim($r['dokter'])] = true;
    if (!empty($r['resep_dokter'])) $dokterList[trim($r['resep_dokter'])] = true;
    if (!empty($r['nama_pelaksana'])) $dokterList[trim($r['nama_pelaksana'])] = true;
}

$pdo->beginTransaction();

try {
    $pasienMap = [];
    if (!empty($noRmList)) {
        $inRm = array_keys($noRmList);
        $placeholders = implode(',', array_fill(0, count($inRm), '?'));
        $stmtMap = $pdo->prepare("SELECT no_rm, id FROM m_pasien WHERE no_rm IN ($placeholders)");
        $stmtMap->execute($inRm);
        $pasienMap = $stmtMap->fetchAll(PDO::FETCH_KEY_PAIR);
    }

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

    $processedKunjunganChunk = [];

    foreach ($parsedRows as $r) {
        // --- m_pasien ---
        $noRm = $r['no_rm'] ?? null;
        $pasienId = null;

        if ($noRm) {
            if (isset($pasienMap[$noRm])) {
                $pasienId = $pasienMap[$noRm];
            } else {
                $stmtInsPasien->execute([
                    ':no_rm'      => $safe_str($noRm, 45),
                    ':nik'        => $safe_str($r['nik'] ?? '', 25),
                    ':asuransi'   => $safe_str($r['no_asuransi_lain'] ?? '', 45),
                    ':nama'       => $safe_str($r['nama'] ?? '-', 140),
                    ':tempat'     => $safe_str($r['tempat_lahir'] ?? '', 90),
                    ':tgl_lahir'  => $r['tanggal_lahir'] ?? null,
                    ':jk'         => $safe_str($r['jenis_kelamin'] ?? '', 20),
                    ':kawin'      => $safe_str($r['status_perkawinan'] ?? '', 30),
                    ':pendidikan' => $safe_str($r['pendidikan'] ?? '', 45),
                    ':pekerjaan'  => $safe_str($r['pekerjaan'] ?? '', 45),
                    ':agama'      => $safe_str($r['agama'] ?? '', 30),
                    ':alamat'     => $safe_str($r['alamat'] ?? '', 240),
                    ':rt'         => $safe_str($r['rt'] ?? '', 8),
                    ':rw'         => $safe_str($r['rw'] ?? '', 8),
                    ':kel'        => $safe_str($r['kode_kelurahan'] ?? '', 18),
                    ':kec'        => $safe_str($r['kode_kecamatan'] ?? '', 18),
                    ':kab'        => $safe_str($r['kode_kabupaten_kota'] ?? '', 18),
                    ':prov'       => $safe_str($r['kode_provinsi'] ?? '', 18),
                    ':pos'        => $safe_str($r['kode_pos'] ?? '', 8),
                    ':alamat_dom' => $safe_str($r['alamat_domisili'] ?? '', 240),
                    ':rt_dom'     => $safe_str($r['rt_domisili'] ?? '', 8),
                    ':rw_dom'     => $safe_str($r['rw_domisili'] ?? '', 8),
                    ':kel_dom'    => $safe_str($r['kode_kelurahan_domisili'] ?? '', 18),
                    ':kec_dom'    => $safe_str($r['kode_kecamatan_domisili'] ?? '', 18),
                    ':kab_dom'    => $safe_str($r['kode_kabupaten_domisili'] ?? '', 18),
                    ':prov_dom'   => $safe_str($r['kode_provinsi_domisili'] ?? '', 18),
                    ':pos_dom'    => $safe_str($r['kode_pos_domisili'] ?? '', 8),
                    ':telp'       => $safe_str($r['no_telepon'] ?? '', 45),
                    ':telp_alt'   => $safe_str($r['no_telepon_alternatif'] ?? '', 45),
                    ':peserta'    => $safe_str($r['status_kepesertaan'] ?? '', 45),
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

        $getTenagaId = function($namaDokter) use ($pdo, &$tenagaMap, $stmtInsTenaga, &$stats, $safe_str) {
            $namaDokter = trim((string)$namaDokter);
            if ($namaDokter === '' || strcasecmp($namaDokter, '-') === 0) return null;
            if (isset($tenagaMap[$namaDokter])) return $tenagaMap[$namaDokter];

            $stmtInsTenaga->execute([':n' => $safe_str($namaDokter, 140)]);
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
            $noTransaksi = (string)$r['no_transaksi'];

            $stmtInsKunjungan->execute([
                ':pid'     => $pasienId,
                ':nt'      => $safe_str($noTransaksi, 90),
                ':tgl'     => $r['tanggal_kunjungan'] ?? null,
                ':jam'     => $r['jam_kunjungan'] ?? null,
                ':sumber'  => $safe_str($r['sumber_data'] ?? '', 45),
                ':tgl_bck' => $r['tanggal_backup'] ?? null,
                ':petugas' => $safe_str($r['petugas_input'] ?? '', 90),
            ]);
            
            $stmtK = $pdo->prepare("SELECT id FROM t_kunjungan WHERE no_transaksi = :nt LIMIT 1");
            $stmtK->execute([':nt' => $noTransaksi]);
            $kunjunganId = (int)$stmtK->fetchColumn();
            
            if ($kunjunganId && !isset($processedKunjunganChunk[$noTransaksi])) {
                $processedKunjunganChunk[$noTransaksi] = true;
                $stats['t_kunjungan']++;
            }

            // ---- t_pemeriksaan (100% Fully Safe & Strict) ----
            if ($kunjunganId && (!empty($r['tekanan_darah']) || !empty($r['suhu']) || !empty($r['nadi']) || !empty($r['sistole']) || !empty($r['diastole']))) {
                $stmtCheck = $pdo->prepare("SELECT id FROM t_pemeriksaan WHERE kunjungan_id = :kid LIMIT 1");
                $stmtCheck->execute([':kid' => $kunjunganId]);
                if (!$stmtCheck->fetchColumn()) {
                    
                    $tdRaw   = trim((string)($r['tekanan_darah'] ?? ''));
                    if (strlen($tdRaw) > 20) $tdRaw = '';

                    $pdo->prepare("INSERT INTO t_pemeriksaan
                        (kunjungan_id, kesadaran, nadi, respiratory_rate, tekanan_darah, sistole, diastole, suhu, tinggi_badan, berat_badan)
                        VALUES (:kid, :kes, :nadi, :rr, :td, :sis, :dias, :suhu, :tb, :bb)")
                    ->execute([
                        ':kid'  => $kunjunganId,
                        ':kes'  => $safe_str($r['kesadaran'] ?? '', 80),
                        ':nadi' => $safe_str($r['nadi'] ?? '', 20),
                        ':rr'   => $safe_str($r['respiratory_rate'] ?? '', 20),
                        ':td'   => $safe_str($tdRaw, 20),
                        ':sis'  => $safe_str($r['sistole'] ?? '', 15),
                        ':dias' => $safe_str($r['diastole'] ?? '', 15),
                        ':suhu' => $safe_str($r['suhu'] ?? '', 20),
                        ':tb'   => $safe_str($r['tinggi_badan'] ?? '', 20),
                        ':bb'   => $safe_str($r['berat_badan'] ?? '', 20),
                    ]);
                    $stats['t_pemeriksaan']++;
                }
            }

            // ---- t_diagnosis ----
            if ($kunjunganId && !empty($r['diagnosis_raw'])) {
                $diagClean = $safe_str($r['diagnosis_raw'], 240);
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
                        ':nama'  => $safe_str($r['nama_tindakan'], 240),
                        ':nakes' => $nakesId,
                        ':tgl'   => $r['tanggal_tindakan'] ?? $r['tanggal_kunjungan'] ?? date('Y-m-d'),
                    ]);
                $stats['t_tindakan']++;
            }

            // ---- t_resep & m_obat ----
            if ($kunjunganId && !empty($r['resep_nama_obat'])) {
                $namaObat = $safe_str($r['resep_nama_obat'], 140);
                if ($namaObat !== null && $namaObat !== '') {
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
                                ':jml'    => $safe_str($r['resep_jumlah'] ?? '1', 45),
                                ':aturan' => $safe_str($r['resep_aturan'] ?? '-', 90),
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

        if ($batchId !== '') {
            $pdo->prepare("UPDATE s_upload_batch SET result_hash = :rhash, status = 'generated', verify_detail = :detail WHERE batch_id = :bid")
                ->execute([
                    ':rhash'  => $resultHash, 
                    ':detail' => json_encode($resultHashData), 
                    ':bid'    => $batchId
                ]);
        } else {
            $stmtB = $pdo->query("SELECT id FROM s_upload_batch WHERE status IN ('uploaded','generated') ORDER BY uploaded_at DESC LIMIT 1");
            $batchIdRec = $stmtB->fetchColumn();
            if ($batchIdRec) {
                $pdo->prepare("UPDATE s_upload_batch SET result_hash = :rhash, status = 'generated', verify_detail = :detail WHERE id = :id")
                    ->execute([
                        ':rhash'  => $resultHash, 
                        ':detail' => json_encode($resultHashData), 
                        ':id'     => $batchIdRec
                    ]);
            }
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