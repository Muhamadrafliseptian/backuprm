<?php
// declare(strict_types=1);

// require_once __DIR__ . '/../bootstrap/internal_guard.php';
// require_once BASE_PATH . '/core/parser_sql.php';

// csrf_check_or_die();
// set_time_limit(300); 
// ini_set('memory_limit', '1024M');

// if (!auth_check()) {
//     json_response(['ok' => false, 'msg' => 'Unauthorized'], 401);
// }

// $totalRows = (int)db()->query("SELECT COUNT(*) FROM s_backup_raw")->fetchColumn();
// if ($totalRows === 0) {
//     json_response(['ok' => false, 'msg' => 'Tidak ada data di s_backup_raw.'], 400);
// }

// $stats = [
//     'raw_rows'           => $totalRows,
//     'm_pasien'           => 0,
//     't_kunjungan'        => 0,
//     't_pemeriksaan'      => 0,
//     't_diagnosis'        => 0,
//     't_tindakan'         => 0,
//     'm_tenaga_kesehatan' => 0,
//     't_resep'            => 0,
//     'd_resep_detail'     => 0,
//     'm_obat'             => 0,
//     't_laboratorium'     => 0,
//     'skipped'            => 0,
// ];

// $samples = [
//     'm_pasien'           => [],
//     't_kunjungan'        => [],
//     't_pemeriksaan'      => [],
//     't_diagnosis'        => [],
//     't_tindakan'         => [],
//     'm_tenaga_kesehatan' => [],
//     't_resep'            => [],
//     'd_resep_detail'     => [],
//     'm_obat'             => [],
//     't_laboratorium'     => [],
// ];

// $seenPasien    = [];
// $seenKunjungan = [];
// $seenObat      = [];
// $seenTenaga    = [];

// // GANTI FETCHALL DENGAN CURSOR AGAR TIDAK MEMAKAN RAM BESAR
// $stmt = db()->query("SELECT id, source_row_id, raw_data FROM s_backup_raw ORDER BY id ASC");
// while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
//     $r = sql_parse_row($row['raw_data']);

//     if (!sql_row_valid($r)) {
//         $stats['skipped']++;
//         continue;
//     }

//     // ============ m_pasien ============
//     if (!isset($seenPasien[$r['no_rm']])) {
//         $seenPasien[$r['no_rm']] = true;
//         $stats['m_pasien']++;
        
//         if (count($samples['m_pasien']) < 15) {
//             $samples['m_pasien'][] = [
//                 'no_rm'             => $r['no_rm'],
//                 'nik'               => $r['nik'],
//                 'nama'              => $r['nama'] ?? '-',
//                 'tanggal_lahir'     => $r['tanggal_lahir'],
//                 'jenis_kelamin'     => $r['jenis_kelamin'],
//                 'alamat'            => $r['alamat'],
//             ];
//         }
//     }

//     // ============ TENAGA KESEHATAN ============
//     if (!empty($r['dokter']) && !isset($seenTenaga[$r['dokter']])) {
//         $seenTenaga[$r['dokter']] = true;
//         $stats['m_tenaga_kesehatan']++;
        
//         if (count($samples['m_tenaga_kesehatan']) < 15) {
//             $samples['m_tenaga_kesehatan'][] = [
//                 'nama'         => $r['dokter'],
//                 'jenis_tenaga' => 'Dokter',
//             ];
//         }
//     }

//     // ============ t_kunjungan ============
//     if (!empty($r['no_transaksi']) && !isset($seenKunjungan[$r['no_transaksi']])) {
//         $seenKunjungan[$r['no_transaksi']] = true;
//         $stats['t_kunjungan']++;
        
//         if (count($samples['t_kunjungan']) < 15) {
//             $samples['t_kunjungan'][] = [
//                 'no_rm'             => $r['no_rm'],
//                 'no_transaksi'      => $r['no_transaksi'],
//                 'tanggal_kunjungan' => $r['tanggal_kunjungan'],
//             ];
//         }

//         // ---- t_pemeriksaan ----
//         if (!empty($r['tekanan_darah']) || !empty($r['suhu']) || !empty($r['nadi'])) {
//             $stats['t_pemeriksaan']++;
//             if (count($samples['t_pemeriksaan']) < 15) {
//                 $samples['t_pemeriksaan'][] = [
//                     'no_transaksi'      => $r['no_transaksi'],
//                     'tekanan_darah'     => $r['tekanan_darah'],
//                     'suhu'              => $r['suhu'],
//                     'nadi'              => $r['nadi'],
//                 ];
//             }
//         }

//         // ---- t_diagnosis ----
//         $listDiag = parse_diagnosis_list($r['diagnosis_raw'] ?? '');
//         foreach ($listDiag as $i => $d) {
//             $stats['t_diagnosis']++;
//             if (count($samples['t_diagnosis']) < 15) {
//                 $samples['t_diagnosis'][] = [
//                     'no_transaksi'    => $r['no_transaksi'],
//                     'kode_icd'        => $d['kode'],
//                     'nama_diagnosis'  => $d['nama'],
//                     'jenis_diagnosis' => $i === 0 ? 'Utama' : 'Sekunder',
//                 ];
//             }
//         }

//         // ---- t_tindakan ----
//         if (!empty($r['nama_tindakan'])) {
//             if (!empty($r['nama_pelaksana']) && !isset($seenTenaga[$r['nama_pelaksana']])) {
//                 $seenTenaga[$r['nama_pelaksana']] = true;
//                 $stats['m_tenaga_kesehatan']++;
//                 if (count($samples['m_tenaga_kesehatan']) < 15) {
//                     $samples['m_tenaga_kesehatan'][] = [
//                         'nama'         => $r['nama_pelaksana'],
//                         'jenis_tenaga' => 'Dokter',
//                     ];
//                 }
//             }
//             $stats['t_tindakan']++;
//             if (count($samples['t_tindakan']) < 15) {
//                 $samples['t_tindakan'][] = [
//                     'no_transaksi'   => $r['no_transaksi'],
//                     'nama_tindakan'  => $r['nama_tindakan'],
//                     'nama_pelaksana' => $r['nama_pelaksana'],
//                 ];
//             }
//         }

//         // ---- t_resep ----
//         if (!empty($r['resep_nama_obat'])) {
//             if (!empty($r['resep_dokter']) && !isset($seenTenaga[$r['resep_dokter']])) {
//                 $seenTenaga[$r['resep_dokter']] = true;
//                 $stats['m_tenaga_kesehatan']++;
//             }

//             $stats['t_resep']++;
//             if (count($samples['t_resep']) < 15) {
//                 $samples['t_resep'][] = [
//                     'no_transaksi' => $r['no_transaksi'],
//                     'nama_dokter'  => $r['resep_dokter'],
//                 ];
//             }

//             $namaList   = parse_obat_list($r['resep_nama_obat']);
//             $jumlahList = parse_jumlah_list($r['resep_jumlah']);
//             $aturanList = parse_aturan_list($r['resep_aturan']);

//             foreach ($namaList as $i => $nama) {
//                 $nama = trim($nama);
//                 if ($nama === '') continue;

//                 if (!isset($seenObat[$nama])) {
//                     $seenObat[$nama] = true;
//                     $stats['m_obat']++;
//                     if (count($samples['m_obat']) < 15) {
//                         $samples['m_obat'][] = ['nama_obat' => $nama];
//                     }
//                 }

//                 $stats['d_resep_detail']++;
//                 if (count($samples['d_resep_detail']) < 15) {
//                     $samples['d_resep_detail'][] = [
//                         'no_transaksi' => $r['no_transaksi'],
//                         'nama_obat'    => $nama,
//                         'jumlah'       => $jumlahList[$i] ?? null,
//                         'aturan_pakai' => $aturanList[$i] ?? null,
//                     ];
//                 }
//             }
//         }

//         // ---- t_laboratorium ----
//         if (sql_row_has_lab($r)) {
//             $stats['t_laboratorium']++;
//             if (count($samples['t_laboratorium']) < 15) {
//                 $samples['t_laboratorium'][] = [
//                     'no_transaksi' => $r['no_transaksi'],
//                     'hasil'        => sql_extract_lab_hasil($r),
//                 ];
//             }
//         }
//     }
// }

// // Susun struktur format output
// $sampleOutput = [];
// foreach ($stats as $key => $totalCount) {
//     if ($key === 'raw_rows' || $key === 'skipped') continue;
//     $sampleOutput[$key] = [
//         'total'  => $totalCount,
//         'sample' => $samples[$key] ?? [],
//     ];
// }

// log_activity('preview_data', 'Preview: ' . json_encode($stats));

// json_response([
//     'ok'     => true,
//     'msg'    => 'Preview berhasil dibuat.',
//     'stats'  => $stats,
//     'sample' => $sampleOutput,
// ]);
// <?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/internal_guard.php';
require_once BASE_PATH . '/core/parser_sql.php';

csrf_check_or_die();
set_time_limit(180);
ini_set('memory_limit', '1024M');

if (!auth_check()) {
    json_response(['ok' => false, 'msg' => 'Unauthorized'], 401);
}

$pdo = db();

$totalRows = (int)$pdo->query("SELECT COUNT(*) FROM s_backup_raw")->fetchColumn();
if ($totalRows === 0) {
    json_response(['ok' => false, 'msg' => 'Tidak ada data di s_backup_raw.'], 400);
}

$stats = [
    'raw_rows'           => $totalRows,
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
    'skipped'            => 0,
];

$samples = [
    'm_pasien'           => [],
    't_kunjungan'        => [],
    't_pemeriksaan'      => [],
    't_diagnosis'        => [],
    't_tindakan'         => [],
    'm_tenaga_kesehatan' => [],
    't_resep'            => [],
    'd_resep_detail'     => [],
    'm_obat'             => [],
    't_laboratorium'     => [],
];

$seenPasien    = [];
$seenKunjungan = [];
$seenObat      = [];
$seenTenaga    = [];

/* 
 * KUNCI UTAMA: Batasi parsing preview misal 10,000 baris pertama saja 
 * agar tidak timeout / failed to fetch, tapi data statistik & sampel sudah sangat representatif.
*/
$maxPreviewRows = 10000;
$processedCount = 0;

$stmt = db()->query("SELECT id, raw_data FROM s_backup_raw ORDER BY id ASC LIMIT " . $maxPreviewRows);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $processedCount++;
    $r = sql_parse_row($row['raw_data']);

    if (!sql_row_valid($r)) {
        $stats['skipped']++;
        continue;
    }

    // ============ m_pasien ============
    if (!empty($r['no_rm']) && !isset($seenPasien[$r['no_rm']])) {
        $seenPasien[$r['no_rm']] = true;
        $stats['m_pasien']++;
        
        if (count($samples['m_pasien']) < 15) {
            $samples['m_pasien'][] = [
                'no_rm'             => $r['no_rm'],
                'nik'               => $r['nik'] ?? '-',
                'nama'              => $r['nama'] ?? '-',
                'tanggal_lahir'     => $r['tanggal_lahir'] ?? '-',
                'jenis_kelamin'     => $r['jenis_kelamin'] ?? '-',
                'alamat'            => $r['alamat'] ?? '-',
            ];
        }
    }

    // ============ TENAGA KESEHATAN ============
    if (!empty($r['dokter']) && !isset($seenTenaga[$r['dokter']])) {
        $seenTenaga[$r['dokter']] = true;
        $stats['m_tenaga_kesehatan']++;
        
        if (count($samples['m_tenaga_kesehatan']) < 15) {
            $samples['m_tenaga_kesehatan'][] = [
                'nama'         => $r['dokter'],
                'jenis_tenaga' => 'Dokter',
            ];
        }
    }

    // ============ t_kunjungan ============
    if (!empty($r['no_transaksi']) && !isset($seenKunjungan[$r['no_transaksi']])) {
        $seenKunjungan[$r['no_transaksi']] = true;
        $stats['t_kunjungan']++;
        
        if (count($samples['t_kunjungan']) < 15) {
            $samples['t_kunjungan'][] = [
                'no_rm'             => $r['no_rm'] ?? '-',
                'no_transaksi'      => $r['no_transaksi'],
                'tanggal_kunjungan' => $r['tanggal_kunjungan'] ?? '-',
            ];
        }

        // ---- t_pemeriksaan ----
        if (!empty($r['tekanan_darah']) || !empty($r['suhu']) || !empty($r['nadi'])) {
            $stats['t_pemeriksaan']++;
            if (count($samples['t_pemeriksaan']) < 15) {
                $samples['t_pemeriksaan'][] = [
                    'no_transaksi'      => $r['no_transaksi'],
                    'tekanan_darah'     => $r['tekanan_darah'] ?? '-',
                    'suhu'              => $r['suhu'] ?? '-',
                    'nadi'              => $r['nadi'] ?? '-',
                ];
            }
        }

        // ---- t_diagnosis ----
        if (!empty($r['diagnosis_raw'])) {
            $listDiag = parse_diagnosis_list($r['diagnosis_raw']);
            foreach ($listDiag as $i => $d) {
                $stats['t_diagnosis']++;
                if (count($samples['t_diagnosis']) < 15) {
                    $samples['t_diagnosis'][] = [
                        'no_transaksi'    => $r['no_transaksi'],
                        'kode_icd'        => $d['kode'] ?? '-',
                        'nama_diagnosis'  => $d['nama'] ?? '-',
                        'jenis_diagnosis' => $i === 0 ? 'Utama' : 'Sekunder',
                    ];
                }
            }
        }

        // ---- t_tindakan ----
        if (!empty($r['nama_tindakan'])) {
            if (!empty($r['nama_pelaksana']) && !isset($seenTenaga[$r['nama_pelaksana']])) {
                $seenTenaga[$r['nama_pelaksana']] = true;
                $stats['m_tenaga_kesehatan']++;
                if (count($samples['m_tenaga_kesehatan']) < 15) {
                    $samples['m_tenaga_kesehatan'][] = [
                        'nama'         => $r['nama_pelaksana'],
                        'jenis_tenaga' => 'Pelaksana',
                    ];
                }
            }
            $stats['t_tindakan']++;
            if (count($samples['t_tindakan']) < 15) {
                $samples['t_tindakan'][] = [
                    'no_transaksi'   => $r['no_transaksi'],
                    'nama_tindakan'  => $r['nama_tindakan'],
                    'nama_pelaksana' => $r['nama_pelaksana'] ?? '-',
                ];
            }
        }

        // ---- t_resep ----
        if (!empty($r['resep_nama_obat'])) {
            if (!empty($r['resep_dokter']) && !isset($seenTenaga[$r['resep_dokter']])) {
                $seenTenaga[$r['resep_dokter']] = true;
                $stats['m_tenaga_kesehatan']++;
            }

            $stats['t_resep']++;
            if (count($samples['t_resep']) < 15) {
                $samples['t_resep'][] = [
                    'no_transaksi' => $r['no_transaksi'],
                    'nama_dokter'  => $r['resep_dokter'] ?? '-',
                ];
            }

            $namaList   = parse_obat_list($r['resep_nama_obat']);
            $jumlahList = parse_jumlah_list($r['resep_jumlah'] ?? '');
            $aturanList = parse_aturan_list($r['resep_aturan'] ?? '');

            foreach ($namaList as $i => $nama) {
                $nama = trim($nama);
                if ($nama === '') continue;

                if (!isset($seenObat[$nama])) {
                    $seenObat[$nama] = true;
                    $stats['m_obat']++;
                    if (count($samples['m_obat']) < 15) {
                        $samples['m_obat'][] = ['nama_obat' => $nama];
                    }
                }

                $stats['d_resep_detail']++;
                if (count($samples['d_resep_detail']) < 15) {
                    $samples['d_resep_detail'][] = [
                        'no_transaksi' => $r['no_transaksi'],
                        'nama_obat'    => $nama,
                        'jumlah'       => $jumlahList[$i] ?? '-',
                        'aturan_pakai' => $aturanList[$i] ?? '-',
                    ];
                }
            }
        }

        // ---- t_laboratorium ----
        if (function_exists('sql_row_has_lab') && sql_row_has_lab($r)) {
            $stats['t_laboratorium']++;
            if (count($samples['t_laboratorium']) < 15) {
                $samples['t_laboratorium'][] = [
                    'no_transaksi' => $r['no_transaksi'],
                    'hasil'        => function_exists('sql_extract_lab_hasil') ? sql_extract_lab_hasil($r) : '-',
                ];
            }
        }
    }
}

// Jika baris yang diparse dibatasi, kalikan estimasi proporsional untuk total keseluruhan (opsional, agar angka mendekati real)
if ($totalRows > $maxPreviewRows && $processedCount > 0) {
    $multiplier = $totalRows / $processedCount;
    foreach ($stats as $key => $val) {
        if ($key === 'raw_rows' || $key === 'skipped') continue;
        $stats[$key] = (int)round($val * $multiplier);
    }
}

// Susun struktur format output
$sampleOutput = [];
foreach ($stats as $key => $totalCount) {
    if ($key === 'raw_rows' || $key === 'skipped') continue;
    $sampleOutput[$key] = [
        'total'  => $totalCount,
        'sample' => $samples[$key] ?? [],
    ];
}

log_activity('preview_data', 'Preview cepat sukses diproses.');

json_response([
    'ok'     => true,
    'msg'    => 'Preview berhasil dibuat.',
    'stats'  => $stats,
    'sample' => $sampleOutput,
]);