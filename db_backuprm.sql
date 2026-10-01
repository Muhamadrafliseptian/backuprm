-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 28 Sep 2026 pada 10.26
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_backuprm`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `d_resep_detail`
--

CREATE TABLE `d_resep_detail` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `resep_id` bigint(20) UNSIGNED NOT NULL,
  `obat_id` bigint(20) UNSIGNED DEFAULT NULL,
  `nama_obat_snapshot` varchar(255) DEFAULT NULL,
  `jumlah` varchar(30) DEFAULT NULL,
  `satuan` varchar(30) DEFAULT NULL,
  `aturan_pakai` varchar(255) DEFAULT NULL,
  `dosis` varchar(100) DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `l_activity`
--

CREATE TABLE `l_activity` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_nrk` varchar(30) DEFAULT NULL,
  `user_nama` varchar(150) DEFAULT NULL,
  `role` varchar(20) DEFAULT NULL,
  `aksi` varchar(100) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `level` enum('info','warning','error') NOT NULL DEFAULT 'info',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `l_activity`
--

INSERT INTO `l_activity` (`id`, `user_id`, `user_nrk`, `user_nama`, `role`, `aksi`, `deskripsi`, `ip_address`, `user_agent`, `level`, `created_at`) VALUES
(1, 1, '8813084', 'Novida Iskandar, S.Kom', 'admin', 'reset_all', 'RESET ALL oleh Novida Iskandar, S.Kom (8813084): 13 tabel, 4 baris dihapus, file log dibersihkan: activity.log. Detail: {\"l_activity\":4,\"s_upload_batch\":0,\"s_backup_raw\":0,\"d_resep_detail\":0,\"t_resep\":0,\"t_tindakan\":0,\"t_diagnosis\":0,\"t_pemeriksaan\":0,\"t_laboratorium\":0,\"t_kunjungan\":0,\"m_pasien\":0,\"m_obat\":0,\"m_tenaga_kesehatan\":0}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', 'warning', '2026-09-28 15:26:36'),
(2, 1, '8813084', 'Novida Iskandar, S.Kom', 'admin', 'logout', 'User logout (manual): 8813084', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', 'info', '2026-09-28 15:26:43');

-- --------------------------------------------------------

--
-- Struktur dari tabel `m_obat`
--

CREATE TABLE `m_obat` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `kode_obat` varchar(30) DEFAULT NULL,
  `nama_obat` varchar(255) NOT NULL,
  `bentuk_sediaan` varchar(50) DEFAULT NULL,
  `kekuatan` varchar(50) DEFAULT NULL,
  `satuan` varchar(30) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'aktif',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `m_pasien`
--

CREATE TABLE `m_pasien` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `no_rm` varchar(30) NOT NULL,
  `nik` varchar(30) DEFAULT NULL,
  `no_kartu_bpjs` varchar(30) DEFAULT NULL,
  `no_asuransi_lain` varchar(30) DEFAULT NULL,
  `nama` varchar(150) NOT NULL,
  `tempat_lahir` varchar(100) DEFAULT NULL,
  `tanggal_lahir` date DEFAULT NULL,
  `jenis_kelamin` tinyint(4) DEFAULT NULL,
  `status_perkawinan` tinyint(4) DEFAULT NULL,
  `pendidikan` varchar(10) DEFAULT NULL,
  `agama` varchar(20) DEFAULT NULL,
  `pekerjaan` varchar(100) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `rt` varchar(10) DEFAULT NULL,
  `rw` varchar(10) DEFAULT NULL,
  `kode_kelurahan` varchar(20) DEFAULT NULL,
  `kode_kecamatan` varchar(20) DEFAULT NULL,
  `kode_kabupaten_kota` varchar(20) DEFAULT NULL,
  `kode_provinsi` varchar(20) DEFAULT NULL,
  `kode_pos` varchar(10) DEFAULT NULL,
  `alamat_domisili` text DEFAULT NULL,
  `rt_domisili` varchar(10) DEFAULT NULL,
  `rw_domisili` varchar(10) DEFAULT NULL,
  `kode_kelurahan_domisili` varchar(20) DEFAULT NULL,
  `kode_kecamatan_domisili` varchar(20) DEFAULT NULL,
  `kode_kabupaten_domisili` varchar(20) DEFAULT NULL,
  `kode_provinsi_domisili` varchar(20) DEFAULT NULL,
  `kode_pos_domisili` varchar(10) DEFAULT NULL,
  `no_kk` varchar(30) DEFAULT NULL,
  `no_telepon` varchar(30) DEFAULT NULL,
  `no_telepon_alternatif` varchar(30) DEFAULT NULL,
  `status_kepesertaan` varchar(30) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `m_pegawai`
--

CREATE TABLE `m_pegawai` (
  `peg_id` bigint(20) UNSIGNED NOT NULL,
  `peg_nrk` varchar(30) NOT NULL,
  `peg_password` varchar(255) NOT NULL,
  `peg_nama` varchar(150) NOT NULL,
  `peg_tempattugas` varchar(150) DEFAULT NULL,
  `peg_jabatan` varchar(100) DEFAULT NULL,
  `peg_status` varchar(20) NOT NULL DEFAULT 'aktif',
  `peg_role` varchar(20) NOT NULL DEFAULT 'user',
  `failed_attempts` int(11) NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `m_pegawai`
--

INSERT INTO `m_pegawai` (`peg_id`, `peg_nrk`, `peg_password`, `peg_nama`, `peg_tempattugas`, `peg_jabatan`, `peg_status`, `peg_role`, `failed_attempts`, `locked_until`, `last_login`, `created_at`, `updated_at`) VALUES
(1, '8813084', '$2a$12$LlwDjhbw2arYvEB.1Tf91.yI.CwsMx3FD3/ScCaF0.59/danMmeby', 'Novida Iskandar, S.Kom', 'Puskesmas Setiabudi', 'IT', 'aktif', 'admin', 0, NULL, '2026-09-28 15:19:31', '2026-09-27 21:06:38', '2026-09-28 15:19:31');

-- --------------------------------------------------------

--
-- Struktur dari tabel `m_tenaga_kesehatan`
--

CREATE TABLE `m_tenaga_kesehatan` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nama` varchar(150) NOT NULL,
  `jenis_tenaga` varchar(50) DEFAULT NULL,
  `no_str` varchar(50) DEFAULT NULL,
  `no_sip` varchar(50) DEFAULT NULL,
  `nip` varchar(50) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'aktif',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `s_backup_raw`
--

CREATE TABLE `s_backup_raw` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `source_row_id` varchar(50) DEFAULT NULL,
  `raw_data` longtext NOT NULL,
  `import_batch` varchar(50) DEFAULT NULL,
  `batch_id` varchar(50) DEFAULT NULL,
  `imported_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `s_upload_batch`
--

CREATE TABLE `s_upload_batch` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `batch_id` varchar(50) NOT NULL,
  `uploaded_by` bigint(20) UNSIGNED DEFAULT NULL,
  `uploaded_by_nrk` varchar(30) DEFAULT NULL,
  `uploaded_by_nama` varchar(150) DEFAULT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp(),
  `file_name` varchar(255) DEFAULT NULL,
  `file_size` bigint(20) UNSIGNED DEFAULT NULL,
  `file_hash` char(64) DEFAULT NULL COMMENT 'SHA256 file asli',
  `raw_hash` char(64) DEFAULT NULL COMMENT 'SHA256 dari s_backup_raw',
  `result_hash` char(64) DEFAULT NULL COMMENT 'SHA256 dari hasil generate',
  `mode` varchar(20) DEFAULT 'replace',
  `total_rows` int(11) DEFAULT 0,
  `imported_rows` int(11) DEFAULT 0,
  `skipped_rows` int(11) DEFAULT 0,
  `status` enum('uploaded','generated','verified','mismatch','failed') NOT NULL DEFAULT 'uploaded',
  `verified_at` datetime DEFAULT NULL,
  `verify_result` varchar(20) DEFAULT NULL COMMENT 'MATCH / MISMATCH',
  `verify_detail` longtext DEFAULT NULL COMMENT 'JSON detail per tabel',
  `catatan` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `t_diagnosis`
--

CREATE TABLE `t_diagnosis` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `kunjungan_id` bigint(20) UNSIGNED NOT NULL,
  `kode_icd` varchar(20) DEFAULT NULL,
  `nama_diagnosis` text DEFAULT NULL,
  `jenis_diagnosis` varchar(20) DEFAULT 'Utama',
  `urutan` int(11) DEFAULT 1,
  `catatan` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `t_kunjungan`
--

CREATE TABLE `t_kunjungan` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `pasien_id` bigint(20) UNSIGNED NOT NULL,
  `no_transaksi` varchar(80) NOT NULL,
  `tanggal_kunjungan` date DEFAULT NULL,
  `jam_kunjungan` time DEFAULT NULL,
  `sumber_data` varchar(50) DEFAULT NULL,
  `tanggal_backup` date DEFAULT NULL,
  `petugas_input` varchar(150) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'selesai',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `t_laboratorium`
--

CREATE TABLE `t_laboratorium` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `kunjungan_id` bigint(20) UNSIGNED NOT NULL,
  `ada_data` tinyint(1) DEFAULT 0,
  `tanggal` date DEFAULT NULL,
  `jam` time DEFAULT NULL,
  `jenis_pemeriksaan` varchar(150) DEFAULT NULL,
  `hasil` longtext DEFAULT NULL,
  `petugas_lab` varchar(150) DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `t_pemeriksaan`
--

CREATE TABLE `t_pemeriksaan` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `kunjungan_id` bigint(20) UNSIGNED NOT NULL,
  `kesadaran` varchar(50) DEFAULT NULL,
  `nadi` varchar(20) DEFAULT NULL,
  `respiratory_rate` varchar(20) DEFAULT NULL,
  `tekanan_darah` varchar(20) DEFAULT NULL,
  `sistole` varchar(10) DEFAULT NULL,
  `diastole` varchar(10) DEFAULT NULL,
  `suhu` varchar(10) DEFAULT NULL,
  `tinggi_badan` varchar(10) DEFAULT NULL,
  `berat_badan` varchar(10) DEFAULT NULL,
  `imt` varchar(10) DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `t_resep`
--

CREATE TABLE `t_resep` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `kunjungan_id` bigint(20) UNSIGNED NOT NULL,
  `dokter_id` bigint(20) UNSIGNED DEFAULT NULL,
  `tanggal_resep` date DEFAULT NULL,
  `jam_resep` time DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `t_tindakan`
--

CREATE TABLE `t_tindakan` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `kunjungan_id` bigint(20) UNSIGNED NOT NULL,
  `kode_tindakan` varchar(30) DEFAULT NULL,
  `nama_tindakan` varchar(255) DEFAULT NULL,
  `tenaga_kesehatan_id` bigint(20) UNSIGNED DEFAULT NULL,
  `tanggal_tindakan` date DEFAULT NULL,
  `jam_tindakan` time DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `d_resep_detail`
--
ALTER TABLE `d_resep_detail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_resep` (`resep_id`),
  ADD KEY `idx_obat` (`obat_id`),
  ADD KEY `idx_resep_obat` (`resep_id`,`obat_id`);

--
-- Indeks untuk tabel `l_activity`
--
ALTER TABLE `l_activity`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_user_nrk` (`user_nrk`),
  ADD KEY `idx_aksi` (`aksi`),
  ADD KEY `idx_level` (`level`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_level_created` (`level`,`created_at`),
  ADD KEY `idx_aksi_created` (`aksi`,`created_at`),
  ADD KEY `idx_user_created` (`user_id`,`created_at`);

--
-- Indeks untuk tabel `m_obat`
--
ALTER TABLE `m_obat`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_nama_obat` (`nama_obat`),
  ADD KEY `idx_kode` (`kode_obat`);

--
-- Indeks untuk tabel `m_pasien`
--
ALTER TABLE `m_pasien`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_no_rm` (`no_rm`),
  ADD KEY `idx_nik` (`nik`),
  ADD KEY `idx_nama` (`nama`);

--
-- Indeks untuk tabel `m_pegawai`
--
ALTER TABLE `m_pegawai`
  ADD PRIMARY KEY (`peg_id`),
  ADD UNIQUE KEY `uk_peg_nrk` (`peg_nrk`),
  ADD KEY `idx_status` (`peg_status`),
  ADD KEY `idx_role` (`peg_role`);

--
-- Indeks untuk tabel `m_tenaga_kesehatan`
--
ALTER TABLE `m_tenaga_kesehatan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_nama_tenaga` (`nama`),
  ADD KEY `idx_jenis` (`jenis_tenaga`);

--
-- Indeks untuk tabel `s_backup_raw`
--
ALTER TABLE `s_backup_raw`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_batch` (`import_batch`),
  ADD KEY `idx_rowid` (`source_row_id`),
  ADD KEY `idx_batch_id` (`batch_id`);

--
-- Indeks untuk tabel `s_upload_batch`
--
ALTER TABLE `s_upload_batch`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_batch_id` (`batch_id`),
  ADD KEY `idx_uploaded_by` (`uploaded_by`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_uploaded_at` (`uploaded_at`);

--
-- Indeks untuk tabel `t_diagnosis`
--
ALTER TABLE `t_diagnosis`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_kunjungan` (`kunjungan_id`),
  ADD KEY `idx_icd` (`kode_icd`),
  ADD KEY `idx_kunjungan_urutan` (`kunjungan_id`,`urutan`);

--
-- Indeks untuk tabel `t_kunjungan`
--
ALTER TABLE `t_kunjungan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_no_transaksi` (`no_transaksi`),
  ADD KEY `idx_pasien` (`pasien_id`),
  ADD KEY `idx_tanggal` (`tanggal_kunjungan`),
  ADD KEY `idx_tanggal_status` (`tanggal_kunjungan`,`status`);

--
-- Indeks untuk tabel `t_laboratorium`
--
ALTER TABLE `t_laboratorium`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_kunjungan` (`kunjungan_id`);

--
-- Indeks untuk tabel `t_pemeriksaan`
--
ALTER TABLE `t_pemeriksaan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_kunjungan` (`kunjungan_id`);

--
-- Indeks untuk tabel `t_resep`
--
ALTER TABLE `t_resep`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_kunjungan` (`kunjungan_id`),
  ADD KEY `idx_dokter` (`dokter_id`);

--
-- Indeks untuk tabel `t_tindakan`
--
ALTER TABLE `t_tindakan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_kunjungan` (`kunjungan_id`),
  ADD KEY `idx_tenaga` (`tenaga_kesehatan_id`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `d_resep_detail`
--
ALTER TABLE `d_resep_detail`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `l_activity`
--
ALTER TABLE `l_activity`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `m_obat`
--
ALTER TABLE `m_obat`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `m_pasien`
--
ALTER TABLE `m_pasien`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `m_pegawai`
--
ALTER TABLE `m_pegawai`
  MODIFY `peg_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `m_tenaga_kesehatan`
--
ALTER TABLE `m_tenaga_kesehatan`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `s_backup_raw`
--
ALTER TABLE `s_backup_raw`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `s_upload_batch`
--
ALTER TABLE `s_upload_batch`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `t_diagnosis`
--
ALTER TABLE `t_diagnosis`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `t_kunjungan`
--
ALTER TABLE `t_kunjungan`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `t_laboratorium`
--
ALTER TABLE `t_laboratorium`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `t_pemeriksaan`
--
ALTER TABLE `t_pemeriksaan`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `t_resep`
--
ALTER TABLE `t_resep`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `t_tindakan`
--
ALTER TABLE `t_tindakan`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `d_resep_detail`
--
ALTER TABLE `d_resep_detail`
  ADD CONSTRAINT `fk_d_resep_detail_m_obat` FOREIGN KEY (`obat_id`) REFERENCES `m_obat` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_d_resep_detail_t_resep` FOREIGN KEY (`resep_id`) REFERENCES `t_resep` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `l_activity`
--
ALTER TABLE `l_activity`
  ADD CONSTRAINT `fk_l_activity_m_pegawai` FOREIGN KEY (`user_id`) REFERENCES `m_pegawai` (`peg_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `s_upload_batch`
--
ALTER TABLE `s_upload_batch`
  ADD CONSTRAINT `fk_s_upload_batch_m_pegawai` FOREIGN KEY (`uploaded_by`) REFERENCES `m_pegawai` (`peg_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `t_diagnosis`
--
ALTER TABLE `t_diagnosis`
  ADD CONSTRAINT `fk_t_diagnosis_t_kunjungan` FOREIGN KEY (`kunjungan_id`) REFERENCES `t_kunjungan` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `t_kunjungan`
--
ALTER TABLE `t_kunjungan`
  ADD CONSTRAINT `fk_t_kunjungan_m_pasien` FOREIGN KEY (`pasien_id`) REFERENCES `m_pasien` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `t_laboratorium`
--
ALTER TABLE `t_laboratorium`
  ADD CONSTRAINT `fk_t_laboratorium_t_kunjungan` FOREIGN KEY (`kunjungan_id`) REFERENCES `t_kunjungan` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `t_pemeriksaan`
--
ALTER TABLE `t_pemeriksaan`
  ADD CONSTRAINT `fk_t_pemeriksaan_t_kunjungan` FOREIGN KEY (`kunjungan_id`) REFERENCES `t_kunjungan` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `t_resep`
--
ALTER TABLE `t_resep`
  ADD CONSTRAINT `fk_t_resep_m_tenaga_kesehatan` FOREIGN KEY (`dokter_id`) REFERENCES `m_tenaga_kesehatan` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_t_resep_t_kunjungan` FOREIGN KEY (`kunjungan_id`) REFERENCES `t_kunjungan` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `t_tindakan`
--
ALTER TABLE `t_tindakan`
  ADD CONSTRAINT `fk_t_tindakan_m_tenaga_kesehatan` FOREIGN KEY (`tenaga_kesehatan_id`) REFERENCES `m_tenaga_kesehatan` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_t_tindakan_t_kunjungan` FOREIGN KEY (`kunjungan_id`) REFERENCES `t_kunjungan` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
