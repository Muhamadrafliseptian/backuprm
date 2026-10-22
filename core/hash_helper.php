<?php
/**
 * Hitung hash dari hasil generate (10 tabel) — ringkasan agregat secara streaming (aman dari memory limit).
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

    $tableHashes = [];$tableCounts = [];

    foreach ($tables as $table =>$cols) {
        // Ambil jumlah total baris terlebih dahulu
        try {
            $countStmt =$pdo->query("SELECT COUNT(*) FROM `{$table}`");
            $totalRows = (int)$countStmt->fetchColumn();$tableCounts[$table] =$totalRows;

            if ($totalRows === 0) {
                $tableHashes[$table] = hash('sha256', 'EMPTY');
                continue;
            }

            sort($cols);
            $colList = implode(', ', array_map(fn($c) => "`{$c}`", $cols));

            // Gunakan unbuffered query atau cursor fetch satu per satu agar RAM tidak jebol
            $stmt = $pdo->query("SELECT {$colList} FROM `{$table}` ORDER BY id ASC");
            
            $ctx = hash_init('sha256');
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {$line = [];
                foreach ($cols as$col) {
                    $line[] =$col . '=' . ($row[$col] ?? '');
                }
                hash_update($ctx, implode('|', $line) . "\n");
            }
            $tableHashes[$table] = hash_final($ctx);

        } catch (Throwable $e) {
            $tableHashes[$table] = 'ERROR';
            $tableCounts[$table] = 0;
        }
    }

    $ctx = hash_init('sha256');
    foreach ($tableHashes as $t =>$h) {
        hash_update($ctx, "{$t}:{$h}\n");
    }
    $resultHash = hash_final($ctx);

    return [
        'hash'      => $resultHash,
        'per_table' => $tableHashes,
        'counts'    => $tableCounts,
    ];
}