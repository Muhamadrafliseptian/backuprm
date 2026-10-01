<?php
/**
 * file   : hash_helper.php
 * path   : C:\xampp\htdocs\backuprm\core\hash_helper.php
 * fungsi : Fungsi hashing untuk verifikasi integritas data upload
 *
 * FIX: placeholder duplikat (:bid dipakai 2x) → dipisah jadi :bid1 dan :bid2
 *      karena MySQL prepared statement tidak boleh pakai placeholder sama > 1x.
 */
declare(strict_types=1);

/**
 * Hitung SHA256 dari file fisik.
 */
function hash_file_sha256(string $filePath): ?string
{
    if (!file_exists($filePath) || !is_readable($filePath)) {
        return null;
    }
    $hash = hash_file('sha256', $filePath);
    return $hash !== false ? $hash : null;
}

/**
 * Hitung SHA256 dari string.
 */
function hash_string_sha256(string $content): string
{
    return hash('sha256', $content);
}

/**
 * Hitung hash dari hasil query (konsisten, terurut).
 */
function hash_result_set(array $rows, array $columns = []): string
{
    if (empty($rows)) {
        return hash('sha256', 'EMPTY');
    }

    if (empty($columns)) {
        $columns = array_keys($rows[0]);
    }

    sort($columns);

    $ctx = hash_init('sha256');
    foreach ($rows as $row) {
        $line = [];
        foreach ($columns as $col) {
            $line[] = $col . '=' . ($row[$col] ?? '');
        }
        hash_update($ctx, implode('|', $line) . "\n");
    }
    return hash_final($ctx);
}

/**
 * Hitung hash dari s_backup_raw — berdasarkan raw_data saja (terurut by id).
 *
 * FIX: gunakan 2 placeholder berbeda (:bid1, :bid2) untuk query OR.
 */
function hash_backup_raw(?string $batchId = null): string
{
    $sql = "SELECT id, raw_data FROM s_backup_raw";
    $params = [];

    if ($batchId !== null && $batchId !== '') {
        $sql .= " WHERE batch_id = :bid1 OR import_batch = :bid2";
        $params[':bid1'] = $batchId;
        $params[':bid2'] = $batchId;
    }
    $sql .= " ORDER BY id ASC";

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    $ctx = hash_init('sha256');
    while ($row = $stmt->fetch()) {
        hash_update($ctx, $row['raw_data'] . "\n");
    }
    return hash_final($ctx);
}

/**
 * Hitung hash dari hasil generate (10 tabel) — ringkasan agregat.
 */
function hash_generated_result(): array
{
    $pdo = db();

    $tables = [
        'm_pasien'           => ['id', 'no_rm', 'nik', 'nama', 'tanggal_lahir', 'jenis_kelamin'],
        't_kunjungan'        => ['id', 'pasien_id', 'no_transaksi', 'tanggal_kunjungan', 'jam_kunjungan'],
        't_pemeriksaan'      => ['id', 'kunjungan_id', 'nadi', 'tekanan_darah', 'suhu'],
        't_diagnosis'        => ['id', 'kunjungan_id', 'kode_icd', 'nama_diagnosis', 'jenis_diagnosis'],
        't_tindakan'         => ['id', 'kunjungan_id', 'nama_tindakan'],
        'm_tenaga_kesehatan' => ['id', 'nama', 'jenis_tenaga'],
        't_resep'            => ['id', 'kunjungan_id', 'dokter_id', 'tanggal_resep'],
        'd_resep_detail'     => ['id', 'resep_id', 'obat_id', 'nama_obat_snapshot', 'jumlah'],
        'm_obat'             => ['id', 'nama_obat'],
        't_laboratorium'     => ['id', 'kunjungan_id', 'hasil'],
    ];

    $tableHashes = [];
    $tableCounts = [];

    foreach ($tables as $table => $cols) {
        $colList = implode(', ', array_map(fn($c) => "`{$c}`", $cols));

        try {
            $stmt = $pdo->query("SELECT {$colList} FROM `{$table}` ORDER BY id ASC");
            $rows = $stmt->fetchAll();
        } catch (Throwable $e) {
            $tableHashes[$table] = 'ERROR';
            $tableCounts[$table] = 0;
            continue;
        }

        $tableCounts[$table] = count($rows);
        $tableHashes[$table] = hash_result_set($rows, $cols);
    }

    $ctx = hash_init('sha256');
    foreach ($tableHashes as $t => $h) {
        hash_update($ctx, "{$t}:{$h}\n");
    }
    $resultHash = hash_final($ctx);

    return [
        'hash'      => $resultHash,
        'per_table' => $tableHashes,
        'counts'    => $tableCounts,
    ];
}

/**
 * Buat batch_id unik.
 */
function generate_batch_id(): string
{
    return 'BATCH-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3));
}