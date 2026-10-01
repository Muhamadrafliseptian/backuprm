<?php
/**
 * file   : proses_simpan_hasil.php
 * path   : C:\xampp\htdocs\backuprm\actions\proses_simpan_hasil.php
 * fungsi : Parse s_backup_raw → INSERT ke 10 tabel normalisasi
 *          + hitung result_hash + update s_upload_batch (status=generated)
 */
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/internal_guard.php';

require_once BASE_PATH . '/core/parser_sql.php';

csrf_check_or_die();
set_time_limit(900);
ini_set('memory_limit', '512M');

if (!auth_check()) {
    json_response(['ok' => false, 'msg' => 'Unauthorized'], 401);
}

$rows = db()->query("SELECT id, source_row_id, raw_data FROM s_backup_raw ORDER BY id ASC")->fetchAll();

if (!$rows) {
    json_response(['ok' => false, 'msg' => 'Tidak ada data di s_backup_raw.'], 400);
}

$pdo = db();
$pdo->beginTransaction();

$stats = [
    'm_pasien' => 0, 't_kunjungan' => 0, 't_pemeriksaan' => 0,
    't_diagnosis' => 0, 't_tindakan' => 0, 'm_tenaga_kesehatan' => 0,
    't_resep' => 0, 'd_resep_detail' => 0, 'm_obat' => 0, 't_laboratorium' => 0,
];

$pasienMap    = [];
$kunjunganMap = [];
$tenagaMap    = [];
$obatMap      = [];

try {
    foreach ($rows as $row) {
        $r = sql_parse_row($row['raw_data']);
        if (!sql_row_valid($r)) continue;

        // ============ 1. m_pasien ============
        if (!isset($pasienMap[$r['no_rm']])) {
            $stmt = $pdo->prepare("SELECT id FROM m_pasien WHERE no_rm = :rm LIMIT 1");
            $stmt->execute([':rm' => $r['no_rm']]);
            $pid = $stmt->fetchColumn();

            if (!$pid) {
                $stmt = $pdo->prepare("INSERT INTO m_pasien
                    (no_rm, nik, no_asuransi_lain, nama, tempat_lahir, tanggal_lahir,
                     jenis_kelamin, status_perkawinan, pendidikan, pekerjaan, agama,
                     alamat, rt, rw, kode_kelurahan, kode_kecamatan,
                     kode_kabupaten_kota, kode_provinsi, kode_pos,
                     alamat_domisili, rt_domisili, rw_domisili,
                     kode_kelurahan_domisili, kode_kecamatan_domisili,
                     kode_kabupaten_domisili, kode_provinsi_domisili,
                     kode_pos_domisili, no_telepon, no_telepon_alternatif,
                     status_kepesertaan)
                    VALUES
                    (:no_rm, :nik, :asuransi, :nama, :tempat, :tgl_lahir,
                     :jk, :kawin, :pendidikan, :pekerjaan, :agama,
                     :alamat, :rt, :rw, :kel, :kec, :kab, :prov, :pos,
                     :alamat_dom, :rt_dom, :rw_dom, :kel_dom, :kec_dom,
                     :kab_dom, :prov_dom, :pos_dom, :telp, :telp_alt, :peserta)");
                $stmt->execute([
                    ':no_rm'      => $r['no_rm'],
                    ':nik'        => $r['nik'],
                    ':asuransi'   => $r['no_asuransi_lain'],
                    ':nama'       => $r['nama'] ?? '-',
                    ':tempat'     => $r['tempat_lahir'],
                    ':tgl_lahir'  => $r['tanggal_lahir'],
                    ':jk'         => $r['jenis_kelamin'],
                    ':kawin'      => $r['status_perkawinan'],
                    ':pendidikan' => $r['pendidikan'],
                    ':pekerjaan'  => $r['pekerjaan'],
                    ':agama'       => $r['agama'],
                    ':alamat'      => $r['alamat'],
                    ':rt'         => $r['rt'],
                    ':rw'         => $r['rw'],
                    ':kel'        => $r['kode_kelurahan'],
                    ':kec'        => $r['kode_kecamatan'],
                    ':kab'        => $r['kode_kabupaten_kota'],
                    ':prov'       => $r['kode_provinsi'],
                    ':pos'        => $r['kode_pos'],
                    ':alamat_dom' => $r['alamat_domisili'],
                    ':rt_dom'     => $r['rt_domisili'],
                    ':rw_dom'     => $r['rw_domisili'],
                    ':kel_dom'    => $r['kode_kelurahan_domisili'],
                    ':kec_dom'    => $r['kode_kecamatan_domisili'],
                    ':kab_dom'    => $r['kode_kabupaten_domisili'],
                    ':prov_dom'   => $r['kode_provinsi_domisili'],
                    ':pos_dom'    => $r['kode_pos_domisili'],
                    ':telp'       => $r['no_telepon'],
                    ':telp_alt'   => $r['no_telepon_alternatif'],
                    ':peserta'    => $r['status_kepesertaan'],
                ]);
                $pid = (int)$pdo->lastInsertId();
                $stats['m_pasien']++;
            }
            $pasienMap[$r['no_rm']] = (int)$pid;
        }

        // ============ 2. TENAGA KESEHATAN (dari dokter) ============
        if ($r['dokter'] && !isset($tenagaMap[$r['dokter']])) {
            $stmt = $pdo->prepare("SELECT id FROM m_tenaga_kesehatan WHERE nama = :n LIMIT 1");
            $stmt->execute([':n' => $r['dokter']]);
            $tid = $stmt->fetchColumn();
            if (!$tid) {
                $stmt = $pdo->prepare("INSERT INTO m_tenaga_kesehatan (nama, jenis_tenaga) VALUES (:n, 'Dokter')");
                $stmt->execute([':n' => $r['dokter']]);
                $tid = (int)$pdo->lastInsertId();
                $stats['m_tenaga_kesehatan']++;
            }
            $tenagaMap[$r['dokter']] = (int)$tid;
        }

        // ============ 3. t_kunjungan ============
        if (!isset($kunjunganMap[$r['no_transaksi']])) {
            $stmt = $pdo->prepare("SELECT id FROM t_kunjungan WHERE no_transaksi = :nt LIMIT 1");
            $stmt->execute([':nt' => $r['no_transaksi']]);
            $kid = $stmt->fetchColumn();

            if (!$kid) {
                $stmt = $pdo->prepare("INSERT INTO t_kunjungan
                    (pasien_id, no_transaksi, tanggal_kunjungan, jam_kunjungan,
                     sumber_data, tanggal_backup, petugas_input, status)
                    VALUES (:pid, :nt, :tgl, :jam, :sumber, :tgl_bck, :petugas, 'selesai')");
                $stmt->execute([
                    ':pid'     => $pasienMap[$r['no_rm']],
                    ':nt'      => $r['no_transaksi'],
                    ':tgl'     => $r['tanggal_kunjungan'],
                    ':jam'     => $r['jam_kunjungan'],
                    ':sumber'  => $r['sumber_data'],
                    ':tgl_bck' => $r['tanggal_backup'],
                    ':petugas' => $r['petugas_input'],
                ]);
                $kid = (int)$pdo->lastInsertId();
                $stats['t_kunjungan']++;
            }
            $kunjunganMap[$r['no_transaksi']] = (int)$kid;
            $kid = (int)$kid;

            // ---- 4. t_pemeriksaan ----
            if ($r['tekanan_darah'] || $r['suhu'] || $r['nadi']) {
                $stmt = $pdo->prepare("SELECT id FROM t_pemeriksaan WHERE kunjungan_id = :kid LIMIT 1");
                $stmt->execute([':kid' => $kid]);
                if (!$stmt->fetchColumn()) {
                    $stmt = $pdo->prepare("INSERT INTO t_pemeriksaan
                        (kunjungan_id, kesadaran, nadi, respiratory_rate, tekanan_darah,
                         sistole, diastole, suhu, tinggi_badan, berat_badan)
                        VALUES (:kid, :kes, :nadi, :rr, :td, :sis, :dias, :suhu, :tb, :bb)");
                    $stmt->execute([
                        ':kid'  => $kid,
                        ':kes'  => $r['kesadaran'],
                        ':nadi' => $r['nadi'],
                        ':rr'   => $r['respiratory_rate'],
                        ':td'   => $r['tekanan_darah'],
                        ':sis'  => $r['sistole'],
                        ':dias' => $r['diastole'],
                        ':suhu' => $r['suhu'],
                        ':tb'   => $r['tinggi_badan'],
                        ':bb'   => $r['berat_badan'],
                    ]);
                    $stats['t_pemeriksaan']++;
                }
            }

            // ---- 5. t_diagnosis ----
            $listDiag = parse_diagnosis_list($r['diagnosis_raw']);
            if ($listDiag) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM t_diagnosis WHERE kunjungan_id = :kid");
                $stmt->execute([':kid' => $kid]);
                if ((int)$stmt->fetchColumn() === 0) {
                    $stmt = $pdo->prepare("INSERT INTO t_diagnosis
                        (kunjungan_id, kode_icd, nama_diagnosis, jenis_diagnosis, urutan)
                        VALUES (:kid, :icd, :nama, :jenis, :urut)");
                    foreach ($listDiag as $i => $d) {
                        $stmt->execute([
                            ':kid'   => $kid,
                            ':icd'   => $d['kode'],
                            ':nama'  => $d['nama'],
                            ':jenis' => $i === 0 ? 'Utama' : 'Sekunder',
                            ':urut'  => $i + 1,
                        ]);
                        $stats['t_diagnosis']++;
                    }
                }
            }

            // ---- 6. t_tindakan ----
            if ($r['nama_tindakan']) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM t_tindakan WHERE kunjungan_id = :kid");
                $stmt->execute([':kid' => $kid]);
                if ((int)$stmt->fetchColumn() === 0) {
                    $tid = null;
                    if ($r['nama_pelaksana']) {
                        $tid = $tenagaMap[$r['nama_pelaksana']] ?? null;
                        if (!$tid) {
                            $stmt = $pdo->prepare("SELECT id FROM m_tenaga_kesehatan WHERE nama = :n LIMIT 1");
                            $stmt->execute([':n' => $r['nama_pelaksana']]);
                            $tid = $stmt->fetchColumn();
                            if (!$tid) {
                                $stmt = $pdo->prepare("INSERT INTO m_tenaga_kesehatan (nama, jenis_tenaga) VALUES (:n, 'Dokter')");
                                $stmt->execute([':n' => $r['nama_pelaksana']]);
                                $tid = (int)$pdo->lastInsertId();
                                $stats['m_tenaga_kesehatan']++;
                            }
                            $tenagaMap[$r['nama_pelaksana']] = (int)$tid;
                        }
                    }
                    $stmt = $pdo->prepare("INSERT INTO t_tindakan
                        (kunjungan_id, nama_tindakan, tenaga_kesehatan_id,
                         tanggal_tindakan, jam_tindakan)
                        VALUES (:kid, :nama, :tid, :tgl, :jam)");
                    $stmt->execute([
                        ':kid'  => $kid,
                        ':nama' => $r['nama_tindakan'],
                        ':tid'  => $tid,
                        ':tgl'  => $r['tanggal_tindakan'],
                        ':jam'  => $r['jam_tindakan'],
                    ]);
                    $stats['t_tindakan']++;
                }
            }

            // ---- 7. t_resep + DETAIL ----
            if ($r['resep_nama_obat']) {
                $stmt = $pdo->prepare("SELECT id FROM t_resep WHERE kunjungan_id = :kid LIMIT 1");
                $stmt->execute([':kid' => $kid]);
                $rid = $stmt->fetchColumn();

                if (!$rid) {
                    $did = null;
                    if ($r['resep_dokter']) {
                        $did = $tenagaMap[$r['resep_dokter']] ?? null;
                        if (!$did) {
                            $stmt = $pdo->prepare("SELECT id FROM m_tenaga_kesehatan WHERE nama = :n LIMIT 1");
                            $stmt->execute([':n' => $r['resep_dokter']]);
                            $did = $stmt->fetchColumn();
                            if (!$did) {
                                $stmt = $pdo->prepare("INSERT INTO m_tenaga_kesehatan (nama, jenis_tenaga) VALUES (:n, 'Dokter')");
                                $stmt->execute([':n' => $r['resep_dokter']]);
                                $did = (int)$pdo->lastInsertId();
                                $stats['m_tenaga_kesehatan']++;
                            }
                            $tenagaMap[$r['resep_dokter']] = (int)$did;
                        }
                    }
                    $stmt = $pdo->prepare("INSERT INTO t_resep
                        (kunjungan_id, dokter_id, tanggal_resep, jam_resep)
                        VALUES (:kid, :did, :tgl, :jam)");
                    $stmt->execute([
                        ':kid' => $kid,
                        ':did' => $did,
                        ':tgl' => $r['resep_tanggal'] ?: $r['tanggal_kunjungan'],
                        ':jam' => $r['resep_jam'] ?: $r['jam_kunjungan'],
                    ]);
                    $rid = (int)$pdo->lastInsertId();
                    $stats['t_resep']++;

                    // Parse detail
                    $namaList   = parse_obat_list($r['resep_nama_obat']);
                    $jumlahList = parse_jumlah_list($r['resep_jumlah']);
                    $aturanList = parse_aturan_list($r['resep_aturan']);

                    $stmt = $pdo->prepare("INSERT INTO d_resep_detail
                        (resep_id, obat_id, nama_obat_snapshot, jumlah, aturan_pakai)
                        VALUES (:rid, :oid, :nama, :jumlah, :aturan)");

                    foreach ($namaList as $i => $nama) {
                        $nama = trim($nama);
                        if ($nama === '') continue;

                        if (isset($obatMap[$nama])) {
                            $oid = $obatMap[$nama];
                        } else {
                            $stmt2 = $pdo->prepare("SELECT id FROM m_obat WHERE nama_obat = :n LIMIT 1");
                            $stmt2->execute([':n' => $nama]);
                            $oid = $stmt2->fetchColumn();
                            if (!$oid) {
                                $stmt2 = $pdo->prepare("INSERT INTO m_obat (nama_obat) VALUES (:n)");
                                $stmt2->execute([':n' => $nama]);
                                $oid = (int)$pdo->lastInsertId();
                                $stats['m_obat']++;
                            }
                            $obatMap[$nama] = (int)$oid;
                        }

                        $stmt->execute([
                            ':rid'    => $rid,
                            ':oid'    => $oid,
                            ':nama'   => $nama,
                            ':jumlah' => $jumlahList[$i] ?? null,
                            ':aturan' => $aturanList[$i] ?? null,
                        ]);
                        $stats['d_resep_detail']++;
                    }
                }
            }

            // ---- 8. t_laboratorium ----
            if (sql_row_has_lab($r)) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM t_laboratorium WHERE kunjungan_id = :kid");
                $stmt->execute([':kid' => $kid]);
                if ((int)$stmt->fetchColumn() === 0) {
                    $stmt = $pdo->prepare("INSERT INTO t_laboratorium
                        (kunjungan_id, ada_data, tanggal, jam, hasil)
                        VALUES (:kid, 1, :tgl, :jam, :hasil)");
                    $stmt->execute([
                        ':kid'   => $kid,
                        ':tgl'   => $r['tanggal_kunjungan'],
                        ':jam'   => $r['jam_kunjungan'],
                        ':hasil' => sql_extract_lab_hasil($r),
                    ]);
                    $stats['t_laboratorium']++;
                }
            }
        }
    }

    $pdo->commit();

    /* ============================================================
       HITUNG HASH HASIL GENERATE + UPDATE BATCH
       ============================================================ */
    require_once BASE_PATH . '/core/hash_helper.php';

    $resultHashData = hash_generated_result();
    $resultHash     = $resultHashData['hash'];

    /* Ambil batch terakhir yang statusnya uploaded/generated */
    $stmtB = $pdo->query("
        SELECT id, batch_id
        FROM s_upload_batch
        WHERE status IN ('uploaded','generated')
        ORDER BY uploaded_at DESC
        LIMIT 1
    ");
    $batchRow = $stmtB->fetch();

    if ($batchRow) {
        /* Update result_hash + status.
         * CATATAN: verify_result TIDAK diisi di sini.
         * verify_result diisi oleh verifikasi.php / proses_mark_verified.php
         * setelah membandingkan hash tersimpan vs hash saat ini.
         */
        $stmtUpd = $pdo->prepare("
            UPDATE s_upload_batch
               SET result_hash   = :rhash,
                   status        = 'generated',
                   verify_detail = :detail
             WHERE id = :id
        ");
        $stmtUpd->execute([
            ':rhash'  => $resultHash,
            ':detail' => json_encode($resultHashData),
            ':id'     => $batchRow['id'],
        ]);
    }

    log_activity('simpan_hasil', sprintf(
        'Sukses: %s | result_hash=%s...',
        json_encode($stats),
        substr($resultHash, 0, 12)
    ));

    json_response([
        'ok'          => true,
        'msg'         => 'Data berhasil disimpan ke database.',
        'stats'       => $stats,
        'result_hash' => $resultHash,
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    log_error('simpan_hasil: ' . $e->getMessage());
    json_response(['ok' => false, 'msg' => 'Gagal: ' . $e->getMessage()], 500);
}