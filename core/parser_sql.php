<?php
/**
 * file   : parser_sql.php
 * path   : C:\xampp\htdocs\backuprm\core\parser_sql.php
 * fungsi : Parser SQL backup RME — index berdasarkan data asli (244 kolom)
 */
declare(strict_types=1);

require_once BASE_PATH . '/core/parser_helper.php';

/* ============================================================
   INDEX KOLOM (base 0) — diverifikasi dari data asli (244 kolom)
   ============================================================ */
const COL = [
    // Header (kolom 0-45)
    'no'                     => 0,
    'id_backup'              => 1,
    'tanggal_backup'         => 2,
    'petugas_input'          => 3,
    'sumber_data'            => 4,
    'nama'                   => 5,
    'no_rm'                  => 6,
    'nik'                    => 7,
    'no_identitas_lain'      => 8,
    'nama_ibu'               => 9,
    'tempat_lahir'           => 10,
    'tanggal_lahir'          => 11,
    'jenis_kelamin'          => 12,
    'agama'                  => 13,
    'alamat'                 => 16,
    'rt'                     => 17,
    'rw'                     => 18,
    'kode_kelurahan'         => 19,
    'kode_kecamatan'         => 20,
    'kode_kabupaten_kota'    => 21,
    'kode_pos'               => 22,
    'kode_provinsi'          => 23,
    'alamat_domisili'        => 25,
    'rt_domisili'            => 26,
    'rw_domisili'            => 27,
    'kode_kelurahan_dom'     => 28,
    'kode_kecamatan_dom'     => 29,
    'kode_kabupaten_dom'     => 30,
    'kode_pos_dom'           => 31,
    'kode_provinsi_dom'      => 32,
    'no_telepon'             => 34,
    'no_telepon_alt'         => 35,
    // FIX: peta indeks geser. Data SIKDA aktual (2962 baris, diverifikasi 30/09/2026):
    //   idx 36 = PENDIDIKAN kode numerik (0-7) + teks ('TIDAK TAHU')
    //   idx 37 = PEKERJAAN teks ('IBU R.TANGGA', 'PELAJAR', 'LAIN-LAIN', ...)
    //   idx 38 = STATUS PERKAWINAN kode numerik (1-4)
    //   idx 39 = NAMA PASIEN pada baris resep/anak (bukan kolom master)
    // Sebelumnya 'pendidikan' => 37 sehingga kolom pendidikan menerima
    // nilai pekerjaan ('IBU R.TANGGA' = 12 char) dan error 1406 Data too long.
    'pendidikan'             => 36,
    'pekerjaan'              => 37,
    'status_perkawinan'      => 38,
    'status_kepesertaan'     => 45,

    // Kunjungan
    'tanggal_kunjungan'      => 46,
    'jam_kunjungan'          => 47,

    // Diagnosis
    'diagnosis_awal'         => 65,
    'diagnosis_akhir'        => 192,
    'diagnosis_primer'       => 193,
    'diagnosis_sekunder'     => 194,

    // Pemeriksaan
    'kesadaran'              => 71,
    'vital_sign_raw'         => 72,
    'nadi'                   => 73,
    'respiratory_rate'       => 74,
    'tekanan_darah'          => 75,
    'sistole'                => 76,
    'diastole'               => 77,
    'suhu'                   => 78,

    // Tenaga kesehatan
    'dokter_umum'            => 197,
    'perawat'                => 198,
    'dokter_tindakan'        => 212,

    // Tindakan (FIX: index disesuaikan dengan data aktual)
    'tindakan_nama'          => 213,   // FIX: sebelumnya 214
    'tindakan_pelaksana'     => 215,   // FIX: sebelumnya 212
    'tindakan_tanggal'       => 216,
    'tindakan_jam'           => 217,

    // Resep
    'resep_raw'              => 218,
    'daftar_obat'            => 226,
    'resep_jumlah'           => 229,
    'resep_aturan'           => 230,
    'resep_dokter'           => 237,
    'resep_tanggal'          => 239,
    'resep_jam'              => 240,

    // TB/BB
    'tinggi_badan'           => 222,
    'berat_badan'            => 223,

    // Lab (scan via helper)
    'lab_hasil'              => 175,
];

/* ============================================================
   PARSE ROW
   ============================================================ */
function sql_parse_row(string $raw): array
{
    $v = sql_extract_values($raw);
    if (empty($v)) return [];

    $get = fn($key) => $v[COL[$key] ?? -1] ?? null;

    return [
        'no_transaksi'      => parse_null($get('id_backup')),
        'tanggal_backup'    => parse_dt($get('tanggal_backup')),
        'petugas_input'     => parse_null($get('petugas_input')),
        'sumber_data'       => parse_null($get('sumber_data')),

        // PASIEN
        'nama'              => parse_null($get('nama')),
        'no_rm'             => parse_null($get('no_rm')),
        'nik'               => parse_null($get('nik')),
        'no_asuransi_lain'  => parse_null($get('no_identitas_lain')),
        'tempat_lahir'      => parse_null($get('tempat_lahir')),
        'tanggal_lahir'     => parse_dt($get('tanggal_lahir')),
        'jenis_kelamin'     => parse_int($get('jenis_kelamin')),
        'agama'             => parse_null($get('agama')),
        'alamat'            => parse_null($get('alamat')),
        'rt'                => parse_null($get('rt')),
        'rw'                => parse_null($get('rw')),
        'kode_kelurahan'    => parse_null($get('kode_kelurahan')),
        'kode_kecamatan'    => parse_null($get('kode_kecamatan')),
        'kode_kabupaten_kota' => parse_null($get('kode_kabupaten_kota')),
        'kode_pos'          => parse_null($get('kode_pos')),
        'kode_provinsi'     => parse_null($get('kode_provinsi')),
        'alamat_domisili'   => parse_null($get('alamat_domisili')),
        'rt_domisili'       => parse_null($get('rt_domisili')),
        'rw_domisili'       => parse_null($get('rw_domisili')),
        'kode_kelurahan_domisili' => parse_null($get('kode_kelurahan_dom')),
        'kode_kecamatan_domisili' => parse_null($get('kode_kecamatan_dom')),
        'kode_kabupaten_domisili' => parse_null($get('kode_kabupaten_dom')),
        'kode_pos_domisili' => parse_null($get('kode_pos_dom')),
        'kode_provinsi_domisili'  => parse_null($get('kode_provinsi_dom')),
        'no_telepon'        => parse_null($get('no_telepon')),
        'no_telepon_alternatif' => parse_null($get('no_telepon_alt')),
        'pendidikan'        => parse_null($get('pendidikan')),
        'pekerjaan'         => parse_null($get('pekerjaan')),
        'status_perkawinan' => parse_int($get('status_perkawinan')),
        'status_kepesertaan'=> parse_null($get('status_kepesertaan')),
        'tinggi_badan'      => parse_null($get('tinggi_badan')),
        'berat_badan'       => parse_null($get('berat_badan')),

        // KUNJUNGAN
        'tanggal_kunjungan' => parse_dt($get('tanggal_kunjungan')),
        'jam_kunjungan'     => parse_jam($get('jam_kunjungan')),

        // PEMERIKSAAN
        'kesadaran'         => parse_null($get('kesadaran')),
        'nadi'              => parse_null($get('nadi')),
        'respiratory_rate'  => parse_null($get('respiratory_rate')),
        'tekanan_darah'     => parse_null($get('tekanan_darah')),
        'sistole'           => parse_null($get('sistole')),
        'diastole'          => parse_null($get('diastole')),
        'suhu'              => parse_null($get('suhu')),

        // DIAGNOSIS
        'diagnosis_raw'     => parse_null($get('diagnosis_akhir'))
                                ?: parse_null($get('diagnosis_awal')),

        // TINDAKAN
        'nama_tindakan'     => parse_null($get('tindakan_nama')),
        'nama_pelaksana'    => parse_null($get('tindakan_pelaksana')),
        'tanggal_tindakan'  => parse_dt($get('tindakan_tanggal')),
        'jam_tindakan'      => parse_jam($get('tindakan_jam')),

        // DOKTER (untuk tenaga_kesehatan)
        'dokter'            => parse_null($get('dokter_umum'))
                                ?: parse_null($get('dokter_tindakan'))
                                ?: parse_null($get('resep_dokter')),

        // LABORATORIUM
        'lab_hasil_raw'     => null,   // di-scan via helper

        // RESEP
        'resep_nama_obat'   => parse_null($get('daftar_obat'))
                                ?: parse_null($get('resep_raw')),
        'resep_jumlah'      => parse_null($get('resep_jumlah')),
        'resep_aturan'      => parse_null($get('resep_aturan')),
        'resep_dokter'      => parse_null($get('resep_dokter')),
        'resep_tanggal'     => parse_dt($get('resep_tanggal')),
        'resep_jam'         => parse_jam($get('resep_jam')),

        '_raw'              => $v,
    ];
}

function sql_row_valid(array $r): bool
{
    return !empty($r['no_rm']) && !empty($r['no_transaksi']);
}

/**
 * Cek apakah ada data lab (scan semua value).
 */
function sql_row_has_lab(array $r): bool
{
    foreach ($r['_raw'] as $val) {
        if ($val === null) continue;
        $s = (string)$val;
        if (stripos($s, 'hemoglobin') !== false) return true;
        if (stripos($s, 'Ada data laboratorium') !== false) return true;
    }
    return false;
}

/**
 * Ambil nilai lab dari raw row.
 */
function sql_extract_lab_hasil(array $r): ?string
{
    foreach ($r['_raw'] as $val) {
        if ($val === null) continue;
        $s = (string)$val;
        if (stripos($s, 'hemoglobin') !== false) return $s;
    }
    return null;
}

/* ============================================================
   PARSE VALUES
   ============================================================ */
function sql_extract_values(string $line): array
{
    if (!preg_match('#VALUES\s*\((.*)\)\s*;?\s*$#is', $line, $m)) {
        return [];
    }
    $raw = $m[1];

    $values = [];
    $len = strlen($raw);
    $buf = '';
    $inStr = false;
    $i = 0;

    while ($i < $len) {
        $c = $raw[$i];

        if ($inStr) {
            if ($c === "'") {
                if ($i + 1 < $len && $raw[$i + 1] === "'") {
                    $buf .= "'";
                    $i += 2;
                    continue;
                }
                $inStr = false;
                $i++;
                continue;
            }
            if ($c === '\\' && $i + 1 < $len) {
                $buf .= $raw[$i + 1];
                $i += 2;
                continue;
            }
            $buf .= $c;
            $i++;
            continue;
        }

        if ($c === "'") { $inStr = true; $i++; continue; }
        if ($c === ',') { $values[] = trim($buf); $buf = ''; $i++; continue; }

        $buf .= $c;
        $i++;
    }
    $values[] = trim($buf);

    return array_map(function ($v) {
        $v = trim($v);
        if (strcasecmp($v, 'null') === 0) return null;
        return $v;
    }, $values);
}