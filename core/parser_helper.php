<?php
/**
 * file   : parser_helper.php
 * path   : C:\xampp\htdocs\backuprm\core\parser_helper.php
 * fungsi : Fungsi bantu parsing data dari backup_raw (parsing nilai, tanggal, diagnosis, obat)
 */
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/internal_guard.php';

/**
 * Bersihkan nilai NULL literal dari string SQL.
 */
function parse_null(?string $val)
{
    if ($val === null) return null;
    $val = trim($val);
    $upper = strtoupper($val);
    if ($upper === 'NULL' || $val === '' || $val === "''") return null;
    return $val;
}

/**
 * Konversi tanggal dd/mm/yyyy ke yyyy-mm-dd.
 */
function parse_dt(?string $val): ?string
{
    $val = parse_null($val);
    if ($val === null) return null;
    if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $val, $m)) {
        return "{$m[3]}-{$m[2]}-{$m[1]}";
    }
    $ts = strtotime($val);
    return $ts ? date('Y-m-d', $ts) : null;
}

/**
 * Konversi jam HH:MM:SS ke TIME.
 */
function parse_jam(?string $val): ?string
{
    $val = parse_null($val);
    if ($val === null) return null;
    if (preg_match('#^(\d{1,2}):(\d{2})(?::(\d{2}))?$#', $val, $m)) {
        $h = str_pad($m[1], 2, '0', STR_PAD_LEFT);
        $i = $m[2];
        $s = $m[3] ?? '00';
        return "{$h}:{$i}:{$s}";
    }
    return null;
}

/**
 * Ekstrak angka dari string seperti "Nadi 80" -> 80.
 */
function parse_angka(?string $val): ?string
{
    $val = parse_null($val);
    if ($val === null) return null;
    if (preg_match('#(\d+(?:[.,]\d+)?)#', $val, $m)) {
        return str_replace(',', '.', $m[1]);
    }
    return null;
}

/**
 * Parsing list diagnosis yang dipisah koma + minus.
 */
function parse_diagnosis_list(?string $raw): array
{
    $raw = parse_null($raw);
    if ($raw === null) return [];

    $parts = preg_split('/,\s*-\s*/', ltrim($raw, '- '));
    $result = [];

    foreach ($parts as $p) {
        $p = trim($p, " \t\n\r\0\x0B-");
        if ($p === '') continue;

        if (preg_match('#^([A-Z]\d{2}(?:\.\d+)?)\s+(.+)$#', $p, $m)) {
            $result[] = ['kode' => $m[1], 'nama' => trim($m[2])];
        } else {
            $result[] = ['kode' => null, 'nama' => $p];
        }
    }
    return $result;
}

/**
 * Parsing list nama obat dipisah titik-koma.
 */
function parse_obat_list(?string $raw): array
{
    $raw = parse_null($raw);
    if ($raw === null) return [];
    $parts = array_map('trim', explode(';', $raw));
    return array_values(array_filter($parts, fn($v) => $v !== ''));
}

/**
 * Parsing list jumlah obat: "12; 10; 15".
 */
function parse_jumlah_list(?string $raw): array
{
    $raw = parse_null($raw);
    if ($raw === null) return [];
    $parts = array_map('trim', explode(';', $raw));
    return array_values($parts);
}

/**
 * Parsing aturan pakai: "2 tab setelah diare; 1x1".
 */
function parse_aturan_list(?string $raw): array
{
    return parse_jumlah_list($raw);
}

/**
 * Parsing detail resep mentah.
 */
function parse_resep_detail_raw(?string $raw): array
{
    $raw = parse_null($raw);
    if ($raw === null) return [];

    $parts = preg_split('/,\s*/', $raw);
    $result = [];

    foreach ($parts as $p) {
        $p = trim($p);
        if ($p === '') continue;

        $p = preg_replace('#<i>.*?</i>#', '', $p);
        $p = trim($p);

        if (preg_match('#^(.+?)\s*\(([^)]+)\)\s*(.*)$#', $p, $m)) {
            $nama   = trim($m[1]);
            $jumlah = trim($m[2]);
            $aturan = trim($m[3]);
            $result[] = ['nama' => $nama, 'jumlah' => $jumlah, 'aturan' => $aturan];
        } else {
            $result[] = ['nama' => $p, 'jumlah' => null, 'aturan' => null];
        }
    }
    return $result;
}

/**
 * Deteksi apakah ada data laboratorium.
 */
function detect_lab(?string $raw): bool
{
    $raw = parse_null($raw);
    if ($raw === null) return false;
    return stripos($raw, 'Ada data laboratorium') !== false;
}

/**
 * Konversi integer.
 */
function parse_int(?string $val): ?int
{
    $val = parse_null($val);
    if ($val === null) return null;
    return is_numeric($val) ? (int) $val : null;
}