<?php
/**
 * file   : proses_preview_data.php
 * path   : C:\xampp\htdocs\backuprm\actions\proses_preview_data.php
 * fungsi : Parse s_backup_raw → return statistik + sample 15 baris
 */
declare(strict_types=1);

require_once BASE_PATH . '/core/parser_sql.php';

csrf_check_or_die();
set_time_limit(600);
ini_set('memory_limit', '512M');

if (!auth_check()) {
    json_response(['ok' => false, 'msg' => 'Unauthorized'], 401);
}

$rows = db()->query("SELECT id, source_row_id, raw_data FROM s_backup_raw ORDER BY id ASC")->fetchAll();

if (!$rows) {
    json_response(['ok' => false, 'msg' => 'Tidak ada data di s_backup_raw.'], 400);
}

$records = [];
$stats = [
    'raw_rows'         => count($rows),
    'm_pasien'           => 0,
    't_kunjungan'        => 0,
    't_pemeriksaan'      => 0,
    't_diagnosis'        => 0,
    't_tindakan'         => 0,
    'm_tenaga_kesehatan' => 0,
    't_resep'            => 0,
    'd_resep_detail'     => 0,
    'm_obat'             => 0,
    't_laboratorium'     => 0,
    'skipped'          => 0,
];

$seenPasien    = [];
$seenKunjungan = [];
$seenObat      = [];
$seenTenaga    = [];

foreach ($rows as $row) {
    $r = sql_parse_row($row['raw_data']);

    if (!sql_row_valid($r)) {
        $stats['skipped']++;
        continue;
    }

    // ============ m_pasien ============
    if (!isset($seenPasien[$r['no_rm']])) {
        $seenPasien[$r['no_rm']] = true;
        $records['m_pasien'][] = [
            'no_rm'             => $r['no_rm'],
            'nik'               => $r['nik'],
            'no_asuransi_lain'  => $r['no_asuransi_lain'],
            'nama'              => $r['nama'] ?? '-',
            'tempat_lahir'      => $r['tempat_lahir'],
            'tanggal_lahir'     => $r['tanggal_lahir'],
            'jenis_kelamin'     => $r['jenis_kelamin'],
            'status_perkawinan' => $r['status_perkawinan'],
            'pendidikan'        => $r['pendidikan'],
            'pekerjaan'         => $r['pekerjaan'],
            'alamat'            => $r['alamat'],
            'rt'                => $r['rt'],
            'rw'                => $r['rw'],
            'kode_kelurahan'    => $r['kode_kelurahan'],
            'kode_kecamatan'    => $r['kode_kecamatan'],
            'kode_kabupaten_kota' => $r['kode_kabupaten_kota'],
            'kode_provinsi'     => $r['kode_provinsi'],
            'kode_pos'          => $r['kode_pos'],
            'alamat_domisili'   => $r['alamat_domisili'],
            'rt_domisili'       => $r['rt_domisili'],
            'rw_domisili'       => $r['rw_domisili'],
            'kode_kelurahan_domisili' => $r['kode_kelurahan_domisili'],
            'kode_kecamatan_domisili' => $r['kode_kecamatan_domisili'],
            'kode_kabupaten_domisili' => $r['kode_kabupaten_domisili'],
            'kode_provinsi_domisili'  => $r['kode_provinsi_domisili'],
            'kode_pos_domisili' => $r['kode_pos_domisili'],
            'no_telepon'        => $r['no_telepon'],
            'no_telepon_alternatif' => $r['no_telepon_alternatif'],
            'status_kepesertaan'=> $r['status_kepesertaan'],
        ];
        $stats['m_pasien']++;
    }

    // ============ TENAGA KESEHATAN (dari field dokter) ============
    if ($r['dokter'] && !isset($seenTenaga[$r['dokter']])) {
        $seenTenaga[$r['dokter']] = true;
        $records['m_tenaga_kesehatan'][] = [
            'nama'         => $r['dokter'],
            'jenis_tenaga' => 'Dokter',
        ];
        $stats['m_tenaga_kesehatan']++;
    }

    // ============ t_kunjungan ============
    if (!isset($seenKunjungan[$r['no_transaksi']])) {
        $seenKunjungan[$r['no_transaksi']] = true;
        $records['t_kunjungan'][] = [
            'no_rm'             => $r['no_rm'],
            'no_transaksi'      => $r['no_transaksi'],
            'tanggal_kunjungan' => $r['tanggal_kunjungan'],
            'jam_kunjungan'     => $r['jam_kunjungan'],
            'sumber_data'       => $r['sumber_data'],
            'tanggal_backup'    => $r['tanggal_backup'],
            'petugas_input'     => $r['petugas_input'],
        ];
        $stats['t_kunjungan']++;

        // ---- t_pemeriksaan ----
        if ($r['tekanan_darah'] || $r['suhu'] || $r['nadi']) {
            $records['t_pemeriksaan'][] = [
                'no_transaksi'      => $r['no_transaksi'],
                'kesadaran'         => $r['kesadaran'],
                'nadi'              => $r['nadi'],
                'respiratory_rate'  => $r['respiratory_rate'],
                'tekanan_darah'     => $r['tekanan_darah'],
                'sistole'           => $r['sistole'],
                'diastole'          => $r['diastole'],
                'suhu'              => $r['suhu'],
                'tinggi_badan'      => $r['tinggi_badan'],
                'berat_badan'       => $r['berat_badan'],
            ];
            $stats['t_pemeriksaan']++;
        }

        // ---- t_diagnosis ----
        $listDiag = parse_diagnosis_list($r['diagnosis_raw']);
        foreach ($listDiag as $i => $d) {
            $records['t_diagnosis'][] = [
                'no_transaksi'    => $r['no_transaksi'],
                'kode_icd'        => $d['kode'],
                'nama_diagnosis'  => $d['nama'],
                'jenis_diagnosis' => $i === 0 ? 'Utama' : 'Sekunder',
                'urutan'          => $i + 1,
            ];
            $stats['t_diagnosis']++;
        }

        // ---- t_tindakan ----
        if ($r['nama_tindakan']) {
            if ($r['nama_pelaksana'] && !isset($seenTenaga[$r['nama_pelaksana']])) {
                $seenTenaga[$r['nama_pelaksana']] = true;
                $records['m_tenaga_kesehatan'][] = [
                    'nama'         => $r['nama_pelaksana'],
                    'jenis_tenaga' => 'Dokter',
                ];
                $stats['m_tenaga_kesehatan']++;
            }
            $records['t_tindakan'][] = [
                'no_transaksi'     => $r['no_transaksi'],
                'nama_tindakan'    => $r['nama_tindakan'],
                'nama_pelaksana'   => $r['nama_pelaksana'],
                'tanggal_tindakan' => $r['tanggal_tindakan'],
                'jam_tindakan'     => $r['jam_tindakan'],
            ];
            $stats['t_tindakan']++;
        }

        // ---- t_resep ----
        if ($r['resep_nama_obat']) {
            if ($r['resep_dokter'] && !isset($seenTenaga[$r['resep_dokter']])) {
                $seenTenaga[$r['resep_dokter']] = true;
                $records['m_tenaga_kesehatan'][] = [
                    'nama'         => $r['resep_dokter'],
                    'jenis_tenaga' => 'Dokter',
                ];
                $stats['m_tenaga_kesehatan']++;
            }

            $records['t_resep'][] = [
                'no_transaksi'  => $r['no_transaksi'],
                'nama_dokter'   => $r['resep_dokter'],
                'tanggal_resep' => $r['resep_tanggal'] ?: $r['tanggal_kunjungan'],
                'jam_resep'     => $r['resep_jam'] ?: $r['jam_kunjungan'],
            ];
            $stats['t_resep']++;

            // Parse detail m_obat dari daftar_obat (dipisah ';')
            $namaList   = parse_obat_list($r['resep_nama_obat']);
            $jumlahList = parse_jumlah_list($r['resep_jumlah']);
            $aturanList = parse_aturan_list($r['resep_aturan']);

            foreach ($namaList as $i => $nama) {
                $nama = trim($nama);
                if ($nama === '') continue;

                if (!isset($seenObat[$nama])) {
                    $seenObat[$nama] = true;
                    $records['m_obat'][] = ['nama_obat' => $nama];
                    $stats['m_obat']++;
                }

                $records['d_resep_detail'][] = [
                    'no_transaksi'       => $r['no_transaksi'],
                    'nama_obat'          => $nama,
                    'nama_obat_snapshot' => $nama,
                    'jumlah'             => $jumlahList[$i] ?? null,
                    'aturan_pakai'       => $aturanList[$i] ?? null,
                ];
                $stats['d_resep_detail']++;
            }
        }

        // ---- t_laboratorium ----
        if (sql_row_has_lab($r)) {
            $records['t_laboratorium'][] = [
                'no_transaksi' => $r['no_transaksi'],
                'tanggal'      => $r['tanggal_kunjungan'],
                'jam'          => $r['jam_kunjungan'],
                'hasil'        => sql_extract_lab_hasil($r),
            ];
            $stats['t_laboratorium']++;
        }
    }
}

$sample = [];
foreach ($records as $entity => $r) {
    $sample[$entity] = [
        'total'  => count($r),
        'sample' => array_slice($r, 0, 15),
    ];
}

log_activity('preview_data', 'Preview: ' . json_encode($stats));

json_response([
    'ok'     => true,
    'msg'    => 'Preview berhasil dibuat.',
    'stats'  => $stats,
    'sample' => $sample,
]);