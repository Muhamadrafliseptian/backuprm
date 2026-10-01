================================================================================
                    BACKUP RM — SISTEM BACKUP & VERIFIKASI
                           REKAM MEDIS RAWAT JALAN
================================================================================

Aplikasi web untuk meng-upload, mem-parsing, menyimpan, dan memverifikasi
data backup rekam medis (rawat jalan) dari Dinas Kesehatan ke dalam
database terstruktur (10 tabel normalisasi) dengan integritas data
yang bisa diaudit (hash SHA256).

Versi   : 1.0.0
PHP     : 8.0+ (rekomendasi 8.2)
MySQL   : 5.7+ / MariaDB 10.4+
Lisensi : Internal - Puskesmas


================================================================================
1. DAFTAR ISI
================================================================================

    1.  Daftar Isi
    2.  Fitur Utama
    3.  Struktur Folder
    4.  Kebutuhan Sistem
    5.  Instalasi
    6.  Akun Default
    7.  Alur Kerja Utama (Workflow)
    8.  Struktur Database (13 Tabel)
    9.  Struktur Hash (Verifikasi Integritas)
    10. Routing & Alur Bootstrap
    11. Penjelasan Tiap File — BOOTSTRAP
    12. Penjelasan Tiap File — CONFIG
    13. Penjelasan Tiap File — CORE
    14. Penjelasan Tiap File — LAYOUTS
    15. Penjelasan Tiap File — PAGES
    16. Penjelasan Tiap File — ACTIONS
    17. Alur Login & Session
    18. Alur Upload File SQL
    19. Alur Preview Data
    20. Alur Simpan ke Database
    21. Alur Verifikasi Hash Chain
    22. Alur Backup All Record DB
    23. Alur Reset Data
    24. Alur Print Resume Medis
    25. Keamanan
    26. Konfigurasi Penting (.env)
    27. Troubleshooting
    28. Catatan Production
    29. Backup Rutin
    30. Kontak & Bantuan


================================================================================
2. FITUR UTAMA
================================================================================

    [✓] Upload file .sql (backup RME) ke staging s_backup_raw
    [✓] Identifikasi batch upload (user, waktu, nama file, SHA256)
    [✓] Preview data sebelum disimpan ke database (10 tabel)
    [✓] Parsing otomatis:
            - Pasien
            - Kunjungan
            - Pemeriksaan (tanda vital)
            - Diagnosis (ICD-10)
            - Tindakan medis
            - Tenaga kesehatan
            - Resep + detail resep
            - Obat
            - Laboratorium
    [✓] Verifikasi integritas dengan hash chain:
            File Hash -> Raw Hash -> Result Hash
    [✓] Auto-clear staging setelah data berhasil disimpan
    [✓] Log aktivitas lengkap (file + database)
    [✓] Session timeout sinkron (server & client)
    [✓] Reset data (per tabel / semua tabel) dengan konfirmasi password
    [✓] Riwayat upload dengan status visual (belum / sudah disimpan)
    [✓] Backup All Record DB (download semua tabel → .sql / .json)
    [✓] Resume Medis per pasien (kronologis)
    [✓] Print preview profesional (kop surat, tanda tangan)
    [✓] Lockout login (5x salah → 15 menit)
    [✓] CSRF protection di semua form POST


================================================================================
3. STRUKTUR FOLDER
================================================================================

backuprm/
|
|-- .env                          Konfigurasi environment
|-- .htaccess                     Rewrite + security headers
|-- index.php                     FRONT CONTROLLER (entry point)
|-- README.md                     File ini
|
|-- actions/                      12 file handler POST
|   |-- proses_clear_backup.php
|   |-- proses_clear_log.php
|   |-- proses_download_backup.php    (BARU: backup all record)
|   |-- proses_login.php
|   |-- proses_logout.php
|   |-- proses_mark_verified.php
|   |-- proses_ping.php
|   |-- proses_preview_data.php
|   |-- proses_reset_all.php
|   |-- proses_reset_data.php
|   |-- proses_simpan_hasil.php
|   |-- proses_upload_sql.php
|
|-- assets/
|   |-- css/style.css
|   |-- js/main.js
|   |-- js/theme-helper.js
|   |-- ico/                      (kosong — pakai CDN)
|
|-- backups/                      Folder backup + .htaccess
|   |-- .htaccess                 (blokir akses web)
|
|-- bootstrap/                    Bootstrap pipeline
|   |-- .htaccess                 (blokir akses web)
|   |-- config_core.php
|   |-- timeout_sanitasi.php
|   |-- redirect_root.php
|   |-- routes.php
|   |-- action_routes.php
|   |-- validasi_konsistensi.php
|   |-- post_action.php
|   |-- security_route.php
|
|-- config/                       Konfigurasi aplikasi
|   |-- app.php
|   |-- constants.php
|   |-- database.php
|
|-- core/                         Library inti
|   |-- auth.php
|   |-- bootstrap.php
|   |-- csrf.php
|   |-- hash_helper.php
|   |-- helper.php
|   |-- parser_helper.php
|   |-- parser_sql.php
|   |-- session.php
|   |-- validator.php
|
|-- database/                     Folder database + .htaccess
|   |-- .htaccess                 (blokir akses web)
|
|-- layouts/                      Template layout
|   |-- header.php
|   |-- sidebar.php
|   |-- footer.php
|
|-- logs/                         File log + .htaccess
|   |-- .htaccess                 (blokir akses web)
|   |-- activity.log
|   |-- error.log
|
|-- pages/                        Halaman (view) - 17 file
|   |-- 403.php
|   |-- 404.php
|   |-- about.php
|   |-- backup_all.php            (BARU: halaman backup DB)
|   |-- dashboard.php
|   |-- generate.php
|   |-- kunjungan.php
|   |-- kunjungan_detail.php
|   |-- laboratorium.php
|   |-- log.php
|   |-- login.php
|   |-- obat.php
|   |-- pasien.php
|   |-- resume_medis.php
|   |-- tenaga_kesehatan.php
|   |-- upload.php
|   |-- verifikasi.php
|
|-- uploads/                      Folder upload + .htaccess
|   |-- .htaccess                 (blokir akses web)


================================================================================
4. KEBUTUHAN SISTEM
================================================================================

Server:
    - Apache 2.4+ dengan mod_rewrite aktif
    - PHP 8.0+ (disarankan 8.2)
    - MySQL 5.7+ / MariaDB 10.4+

Ekstensi PHP:
    - pdo_mysql
    - mbstring
    - fileinfo
    - openssl
    - json

Browser:
    - Chrome 80+
    - Edge 80+
    - Firefox 75+
    - Safari 13+


================================================================================
5. INSTALASI
================================================================================

LANGKAH 1 — Copy project ke webroot
------------------------------------
Copy folder `backuprm` ke:
    C:\xampp\htdocs\backuprm


LANGKAH 2 — Import database
----------------------------
1. Buka phpMyAdmin: http://localhost/phpmyadmin
2. Buat database baru: `db_backuprm` (utf8mb4_unicode_ci)
3. Import file `db_backuprm.sql`


LANGKAH 3 — Konfigurasi .env
-----------------------------
Edit file `.env`:

    APP_NAME="Backup RM"
    APP_ENV=development
    APP_DEBUG=true
    APP_TIMEZONE=Asia/Jakarta

    DB_HOST=127.0.0.1
    DB_PORT=3306
    DB_NAME=db_backuprm
    DB_USER=root
    DB_PASS=
    DB_CHARSET=utf8mb4

    SESSION_LIFETIME=1800
    SESSION_NAME=BACKUPRM_SESS

    UPLOAD_MAX_SIZE=52428800


LANGKAH 4 — Aktifkan mod_rewrite
---------------------------------
Edit `C:\xampp\apache\conf\httpd.conf`:

    LoadModule rewrite_module modules/mod_rewrite.so    (uncomment)

    <Directory "C:/xampp/htdocs">
        AllowOverride All
        Require all granted
    </Directory>

Restart Apache.


LANGKAH 5 — Akses aplikasi
---------------------------
Buka browser: http://localhost/backuprm


================================================================================
6. AKUN DEFAULT
================================================================================

NRK       : 8813084
Password  : (sesuai saat instalasi awal)
Role      : admin
Nama      : Novida Iskandar, S.Kom

Hak akses:
    - Full akses ke semua halaman
    - Satu-satunya user yang boleh melakukan RESET ALL
    - Akses Backup All Record DB

Catatan: Ganti password via phpMyAdmin jika perlu.
         Hash password pakai bcrypt (password_hash).


================================================================================
7. ALUR KERJA UTAMA (WORKFLOW)
================================================================================

+-------------------------------------------------------------+
|  [1] LOGIN                                                  |
|      - User login dengan NRK + password                     |
|      - Session aktif selama SESSION_LIFETIME (default 30 m) |
+-------------------------------------------------------------+
                            |
                            v
+-------------------------------------------------------------+
|  [2] UPLOAD FILE SQL                                        |
|      - Menu: Upload & Proses                                |
|      - Pilih file .sql                                      |
|      - Pilih mode: Ganti (replace) / Tambah (append)        |
|      - Klik "Upload File"                                   |
|      - File masuk ke s_backup_raw (staging)                 |
+-------------------------------------------------------------+
                            |
                            v
+-------------------------------------------------------------+
|  [3] PREVIEW DATA                                           |
|      - Klik "Preview Data"                                  |
|      - Sistem parse s_backup_raw                            |
|      - Statistik + sample 10 baris per tabel                |
|      - Cek apakah data sudah benar                          |
+-------------------------------------------------------------+
                            |
                            v
+-------------------------------------------------------------+
|  [4] SIMPAN KE DATABASE                                     |
|      - Klik "Simpan ke Database"                            |
|      - Insert ke 10 tabel normalisasi                       |
|      - Hitung result_hash                                   |
|      - Auto-clear s_backup_raw                              |
|      - Status batch: generated                              |
+-------------------------------------------------------------+
                            |
                            v
+-------------------------------------------------------------+
|  [5] VERIFIKASI                                             |
|      - Menu: Verifikasi                                     |
|      - Pilih batch                                          |
|      - Cek 3 level hash (File, Raw, Result)                 |
|      - Status: verified / superseded / mismatch / failed    |
+-------------------------------------------------------------+
                            |
                            v
+-------------------------------------------------------------+
|  [6] LIHAT DATA                                             |
|      - Pasien, Kunjungan, Laboratorium, Obat, Tenaga Kes.   |
|      - Resume Medis (per pasien, kronologis)                |
|      - Print Resume Medis                                   |
+-------------------------------------------------------------+


================================================================================
8. STRUKTUR DATABASE (14 TABEL)
================================================================================

TABEL MASTER
------------
m_pasien              Data master pasien
m_obat                Data master obat
m_tenaga_kesehatan    Data master tenaga kesehatan
m_pegawai             Data pegawai (untuk login)

TABEL TRANSAKSI
---------------
t_kunjungan           Data kunjungan pasien
t_pemeriksaan         Data pemeriksaan (tanda vital)
t_diagnosis           Data diagnosis (ICD-10)
t_tindakan            Data tindakan medis
t_resep               Data resep (per kunjungan)
d_resep_detail        Detail item resep
t_laboratorium        Data hasil laboratorium

TABEL STAGING & LOG
-------------------
s_backup_raw          Data mentah dari file .sql (staging)
s_upload_batch        Identitas upload + hash verifikasi
l_activity            Log aktivitas pengguna


================================================================================
9. STRUKTUR HASH (VERIFIKASI INTEGRITAS)
================================================================================

File Hash (file_hash)
    - SHA256 dari file .sql asli
    - Disimpan saat upload
    - Immutable — tidak pernah berubah
    - Tujuan: memastikan file asli tidak berubah

Raw Hash (raw_hash)
    - SHA256 dari semua baris di s_backup_raw
    - Disimpan saat upload
    - Dihitung dengan urutan by id ASC
    - Tujuan: memastikan data mentah tidak berubah

Result Hash (result_hash)
    - SHA256 dari gabungan hash 10 tabel hasil generate
    - Disimpan setelah "Simpan ke Database"
    - Dihitung ulang saat verifikasi
    - Tujuan: memastikan data yang tersimpan utuh

Alur verifikasi:
    1. Baca file_hash, raw_hash, result_hash dari s_upload_batch
    2. Hitung ulang raw_hash & result_hash saat ini
    3. Bandingkan:
            - Cocok semua            -> verified
            - Raw sudah dihapus      -> superseded
            - Cocok sebagian         -> mismatch
            - Belum di-generate      -> not_generated
            - Upload gagal           -> failed


================================================================================
10. ROUTING & ALUR BOOTSTRAP
================================================================================

Setiap request masuk melalui index.php (front controller).

Alur bootstrap (urutan load):
    1. config_core.php       - Load .env, set timezone, definisi BASE_URL
    2. timeout_sanitasi.php  - Trim semua input GET/POST
    3. redirect_root.php     - Tentukan ROUTE_PATH dari URL
    4. routes.php            - Peta route GET ke pages/*
    5. action_routes.php     - Peta route POST ke actions/*
    6. validasi_konsistensi  - Pastikan folder & file log ada
    7. post_action.php       - Eksekusi action jika ada
    8. security_route.php    - Cek login + render halaman

Contoh routing:
    GET  /backuprm/dashboard         -> pages/dashboard.php
    GET  /backuprm/pasien?q=123      -> pages/pasien.php
    POST /backuprm/actions/login     -> actions/proses_login.php
    POST /backuprm/actions/upload-sql -> actions/proses_upload_sql.php


================================================================================
11. PENJELASAN TIAP FILE — BOOTSTRAP
================================================================================

config_core.php
    - Load file .env
    - Set timezone default
    - Error reporting (debug / production)
    - Deteksi BASE_URL otomatis
    - Load constants, app config, database config
    - Load semua core library

timeout_sanitasi.php
    - Trim spasi berlebih di semua input GET & POST
    - Mencegah input "  admin  " tidak cocok dengan "admin"

redirect_root.php
    - Parse URL, hilangkan base directory
    - Tentukan ROUTE_PATH (default: dashboard atau login)
    - Contoh: /backuprm/pasien?q=123 -> ROUTE_PATH = 'pasien'

routes.php
    - Peta route GET ke file di folder pages/
    - Contoh: 'pasien' => 'pasien.php'
    - 404 jika route tidak ditemukan

action_routes.php
    - Peta route POST /actions/{name} ke file di folder actions/
    - Contoh: 'login' => 'proses_login.php'
    - 404 jika action tidak ditemukan

validasi_konsistensi.php
    - Pastikan folder backups/, logs/, uploads/ ada
    - Pastikan file activity.log & error.log ada
    - Auto-create jika belum ada

post_action.php
    - Eksekusi file action jika ROUTE_PATH mengarah ke /actions/*
    - Setelah eksekusi, exit — tidak lanjut ke security_route

security_route.php
    - Cek apakah user sudah login
    - Route publik: ['login']
    - Jika belum login dan bukan route publik -> redirect ke login
    - Jika sudah login -> render halaman dari pages/


================================================================================
12. PENJELASAN TIAP FILE — CONFIG
================================================================================

app.php
    - Return array konfigurasi aplikasi
    - Nama, URL, versi, timezone, palet warna tema

constants.php
    - Definisi konstanta global:
            APP_NAME, APP_VERSION
            URL_ASSETS, URL_BACKUPS
            PATH_BACKUPS, PATH_LOGS, PATH_UPLOADS
            SESSION_LIFETIME, SESSION_NAME
            UPLOAD_MAX_SIZE
            ROLE_ADMIN, ROLE_USER
            LOG_ACTIVITY, LOG_ERROR

database.php
    - Fungsi db() — singleton PDO connection
    - Set error mode exception
    - Set fetch mode associative
    - Set emulate prepares = false (native prepared)
    - Log error jika koneksi gagal


================================================================================
13. PENJELASAN TIAP FILE — CORE
================================================================================

auth.php
    - auth_user()           : ambil data user dari session
    - auth_check()          : cek user sudah login
    - auth_role()           : ambil role user
    - auth_login()          : set session user + regenerate ID
    - auth_logout()         : hapus session user
    - auth_required()       : redirect ke login jika belum login
    - auth_admin()          : cek user adalah admin
    - auth_find_by_nrk()    : cari pegawai by NRK
    - auth_is_locked()      : cek akun terkunci
    - auth_register_failed(): catat failed attempt + lockout
    - auth_reset_attempts() : reset failed attempts setelah sukses
    - auth_attempt()        : verifikasi login (return array hasil)

    Konstanta:
        LOGIN_MAX_ATTEMPTS = 5
        LOGIN_LOCK_MINUTES = 15

bootstrap.php
    - Set global exception handler
    - Catat error ke log
    - Tampilkan stack trace jika APP_DEBUG=true

csrf.php
    - csrf_token()       : generate token (32 bytes hex)
    - csrf_verify()      : verifikasi token
    - csrf_check_or_die(): exit jika token invalid

hash_helper.php
    - hash_file_sha256()    : SHA256 dari file fisik
    - hash_string_sha256()  : SHA256 dari string
    - hash_result_set()     : hash dari array hasil query
    - hash_backup_raw()     : hash dari s_backup_raw
    - hash_generated_result(): hash dari 10 tabel hasil
    - generate_batch_id()   : buat batch_id unik

helper.php
    - base_url()         : URL absolut
    - asset()            : URL asset
    - redirect()         : redirect + exit
    - e()                : htmlspecialchars wrapper
    - flash()            : set/get flash message
    - log_activity()     : catat aktivitas (file + DB)
    - log_error()        : catat error (file + DB)
    - format_tanggal()   : format tanggal Indonesia
    - parse_tanggal_id() : parse dd/mm/yyyy -> yyyy-mm-dd
    - json_response()    : kirim JSON + exit
    - csrf_field()       : input hidden CSRF
    - is_post()          : cek method POST
    - is_ajax()          : cek request AJAX

parser_helper.php
    - parse_null()           : bersihkan NULL literal SQL
    - parse_dt()             : konversi dd/mm/yyyy -> yyyy-mm-dd
    - parse_jam()            : konversi HH:MM:SS ke TIME
    - parse_angka()          : ekstrak angka dari string
    - parse_diagnosis_list() : parsing list diagnosis
    - parse_obat_list()      : parsing list nama obat
    - parse_jumlah_list()    : parsing list jumlah
    - parse_aturan_list()    : parsing aturan pakai
    - parse_resep_detail_raw(): parsing detail resep mentah
    - detect_lab()           : cek ada data lab
    - parse_int()            : konversi integer

parser_sql.php
    - COL                     : mapping index kolom (244 kolom)
    - sql_parse_row()         : parse 1 baris SQL -> array terstruktur
    - sql_row_valid()         : cek row valid (ada no_rm & no_transaksi)
    - sql_row_has_lab()       : cek row ada data lab
    - sql_extract_lab_hasil() : ambil nilai lab dari raw row
    - sql_extract_values()    : ekstrak nilai dari statement INSERT

session.php
    - session_start_safe()   : start session + timeout otomatis
    - Set cookie params (lifetime, httponly, samesite)
    - Regenerate ID saat session baru
    - Cek timeout idle (server-side)
    - Update _last_activity tiap request

validator.php
    - Class Validator
    - required()  : field wajib diisi
    - min()       : panjang minimal
    - max()       : panjang maksimal
    - in()        : nilai harus dalam list
    - fails()     : cek ada error
    - errors()    : ambil semua error
    - firstError(): ambil error pertama


================================================================================
14. PENJELASAN TIAP FILE — LAYOUTS
================================================================================

header.php
    - Deklarasi HTML head
    - Load Tailwind CDN + config
    - Load Remixicon CDN
    - Load Chart.js, DataTables, SweetAlert2, Select2, NProgress
    - Load custom CSS
    - Favicon SVG data-URI
    - Buka <body> + struktur flex

sidebar.php
    - Sidebar menu utama dengan 4 kategori:
            UTAMA       : Dashboard
            ALUR KERJA  : Upload & Proses, Verifikasi
            DATA        : Pasien, Kunjungan, Laboratorium, Obat, Tenaga Kesehatan
            SISTEM      : Log Aktivitas, Backup All Record DB, Tentang
    - Widget "Server Online"
    - User footer dengan tombol logout
    - Topbar dengan search, notifikasi, user dropdown

footer.php
    - Tutup <main> dan layout
    - Load JS libraries (jQuery, NProgress, SweetAlert2, dll)
    - Set window.BASE_URL, CSRF_TOKEN, SESSION_LIFETIME
    - Load theme-helper.js & main.js
    - Session timeout monitor (sinkron server + client)
    - Warning 60 detik sebelum logout
    - Auto-logout saat idle > SESSION_LIFETIME


================================================================================
15. PENJELASAN TIAP FILE — PAGES
================================================================================

login.php
    - Halaman login
    - Form NRK + password
    - Toggle show/hide password
    - Alert jika login gagal / timeout
    - Link kontak Tim IT via WhatsApp

dashboard.php
    - 4 kartu statistik: Total Pasien, Kunjungan, Obat, Lab
    - Chart tren kunjungan 7 hari terakhir
    - Chart distribusi jenis kelamin pasien
    - Log aktivitas terbaru (8 baris)

about.php
    - Info aplikasi (nama, versi, timezone, mode, BASE_URL)
    - Info server (PHP, MySQL, charset, waktu)
    - Statistik 13 tabel
    - Tombol "Reset Data" dengan konfirmasi password

pasien.php
    - Daftar pasien + pencarian (nama / no_rm / nik)
    - Pagination 10 per halaman
    - Statistik: total, L, P, PBI
    - Tombol Resume Medis & Kunjungan

kunjungan.php
    - Daftar kunjungan + filter (q, tanggal dari/sampai)
    - Pagination 10 per halaman
    - Statistik: total kunjungan, pasien unik, hari aktif

kunjungan_detail.php
    - Detail kunjungan lengkap:
            Identitas pasien
            Pemeriksaan (tanda vital)
            Diagnosis
            Tindakan
            Resep + detail
            Laboratorium
    - Tombol Resume Medis

resume_medis.php
    - Resume lengkap pasien lintas kunjungan (kronologis)
    - Timeline kunjungan dengan card per kunjungan
    - Statistik ringkas: total kunjungan, diagnosis terbanyak, obat tersering
    - Print preview profesional:
            Kop surat
            Identitas pasien (tabel)
            Riwayat kunjungan (tabel)
            Tanda tangan dokter

laboratorium.php
    - Daftar hasil lab + pencarian
    - Pagination 10 per halaman

obat.php
    - Daftar master obat + pencarian
    - Pagination 10 per halaman

tenaga_kesehatan.php
    - Daftar tenaga kesehatan + pencarian
    - Pagination 10 per halaman

upload.php
    - Form upload file SQL
    - Pilih format mode (replace / append)
    - Tombol Preview Data (jika ada data di staging)
    - Tombol Kosongkan Backup
    - Riwayat upload (10 terakhir) dengan status:
            BELUM DI-UPLOAD (kuning)
            SUDAH DI-UPLOAD (hijau)
            GAGAL / MISMATCH (merah)

generate.php
    - Preview data dari s_backup_raw
    - Tampilkan statistik + sample 10 baris per tabel
    - Tombol Simpan ke Database
    - Fallback: jika sessionStorage hilang, tombol "Muat Preview"

verifikasi.php
    - Daftar semua batch upload
    - Detail per batch: identitas, statistik, hash chain
    - Status:
            verified      (hijau)  — semua hash cocok
            superseded    (abu)    — batch sudah digantikan
            not_generated (biru)   — belum disimpan ke DB
            mismatch      (merah)  — hash tidak cocok
            failed        (merah)  — upload gagal
    - Tombol Tandai Terverifikasi

log.php
    - Daftar log aktivitas + filter (level, aksi, user, tanggal)
    - Pagination 10 per halaman
    - Statistik: total, info, warning, error
    - Tombol Bersihkan Log

backup_all.php
    - Halaman backup seluruh database
    - Info DB: nama, versi server, total tabel, total baris, ukuran
    - Pilih format: .sql (restore-ready) / .json (arsip terbaca)
    - Pilih mode: full / structure / data
    - Tombol Download Backup & Pratinjau
    - Daftar tabel (maks 10 baris + tombol "Lihat Semua")

403.php
    - Halaman akses ditolak

404.php
    - Halaman tidak ditemukan


================================================================================
16. PENJELASAN TIAP FILE — ACTIONS
================================================================================

proses_login.php
    - Rate limit per IP (10 percobaan / 5 menit)
    - Validasi input NRK + password
    - Panggil auth_attempt()
    - Handle hasil:
            ok -> login + redirect dashboard
            not_found  -> flash "NRK tidak terdaftar"
            inactive   -> flash "Akun tidak aktif"
            locked     -> flash "Akun terkunci X menit"
            wrong_pass -> flash "Password salah. Sisa N"
    - Reset rate limit saat sukses

proses_logout.php
    - Wajib POST + CSRF
    - Log aktivitas logout
    - Destroy session
    - Flash pesan + redirect login

proses_ping.php
    - Ping dari client untuk keep-alive
    - Cek auth (401 jika expired)
    - Update _last_activity di server
    - Refresh CSRF token

proses_upload_sql.php
    - Validasi file: ekstensi .sql, size, MIME
    - Hitung SHA256 file asli (file_hash)
    - Insert identitas batch ke s_upload_batch
    - Mode replace: DELETE semua baris s_backup_raw
    - Baca file line-by-line, insert ke s_backup_raw
    - Hitung raw_hash dari s_backup_raw
    - Update s_upload_batch: total_rows, imported_rows, skipped_rows, raw_hash
    - Log aktivitas upload

proses_preview_data.php
    - Baca semua baris dari s_backup_raw
    - Parse tiap baris dengan sql_parse_row()
    - Kelompokkan ke 10 entity:
            m_pasien, t_kunjungan, t_pemeriksaan,
            t_diagnosis, t_tindakan, m_tenaga_kesehatan,
            t_resep, d_resep_detail, m_obat, t_laboratorium
    - Return statistik + sample 15 baris per entity

proses_simpan_hasil.php
    - Parse s_backup_raw dengan sql_parse_row()
    - Insert ke 10 tabel dengan dedup check:
            m_pasien: cek by no_rm
            t_kunjungan: cek by no_transaksi
            t_pemeriksaan/diagnosis/tindakan/resep/lab: cek by kunjungan_id
            m_obat: cek by nama_obat
            m_tenaga_kesehatan: cek by nama
    - Commit transaksi
    - TRUNCATE s_backup_raw (auto-clear)
    - Hitung result_hash dari 10 tabel
    - Update s_upload_batch: result_hash, status='generated'
    - Log aktivitas simpan

proses_clear_backup.php
    - Kosongkan s_backup_raw (TRUNCATE)
    - Log aktivitas clear_backup

proses_clear_log.php
    - TRUNCATE l_activity
    - Kosongkan file logs/activity.log & logs/error.log
    - Log aktivitas clear_log (setelah truncate)

proses_reset_data.php
    - TRUNCATE 10 tabel normalisasi (master + transaksi)
    - Staging s_backup_raw TIDAK dihapus
    - Log aktivitas reset_data

proses_reset_all.php
    - WAJIB password user + ketik "RESET" (huruf besar)
    - Cek user adalah NRK 8813084 (opsional)
    - TRUNCATE 13 tabel:
            log & staging dulu (l_activity, s_upload_batch, s_backup_raw)
            transaksi (d_resep_detail, t_resep, dll)
            master (m_pasien, m_obat, m_tenaga_kesehatan)
    - m_pegawai TIDAK dihapus (agar tetap bisa login)
    - Kosongkan file logs/*.log
    - Log aktivitas reset_all (setelah selesai)

proses_mark_verified.php
    - Tandai batch sebagai VERIFIED
    - Hitung ulang raw_hash & result_hash
    - Bandingkan dengan yang tersimpan
    - Jika cocok: update status='verified', verify_result='MATCH'
    - Jika tidak: update status='mismatch', verify_result='MISMATCH'

proses_download_backup.php
    - Ambil daftar semua tabel dari information_schema
    - Set header download (.sql / .json)
    - Generate file streaming:
            SQL: DROP + CREATE + INSERT per tabel (batch 500 baris)
            JSON: struktur + data per tabel
    - Log aktivitas download_backup


================================================================================
17. ALUR LOGIN & SESSION
================================================================================

LOGIN:
    1. User buka /backuprm/ (redirect ke /login jika belum login)
    2. Form login muncul (NRK + password)
    3. Submit POST ke /actions/login
    4. proses_login.php:
            - Cek rate limit per IP
            - Validasi input
            - auth_attempt() -> verifikasi password + lockout
            - Jika sukses:
                    - session_regenerate_id()
                    - Set $_SESSION['user']
                    - Log aktivitas
                    - Redirect /dashboard
            - Jika gagal:
                    - Increment counter rate limit
                    - Flash pesan error
                    - Redirect /login

SESSION TIMEOUT:
    Server-side (session.php):
        1. Setiap request, cek _last_activity
        2. Jika time() - _last_activity > SESSION_LIFETIME:
                - Destroy session
                - Set _auto_logout = 1
        3. Update _last_activity = time()

    Client-side (footer.php):
        1. Track lastActivity di JS
        2. Setiap 1 detik cek idle
        3. Jika idle >= TTL:
                - Kirim ping ke server
                - Jika server expired -> doLogout('timeout')
                - Jika server masih hidup -> reset timer
        4. Jika idle >= TTL - 60s:
                - Tampilkan warning dengan countdown
        5. Setiap 5 menit (kalau user aktif):
                - Kirim ping ke server

    LOGOUT:
        Manual (tombol): doLogout('manual') -> POST ke /actions/logout
        Timeout: doLogout('timeout') -> POST ke /actions/logout


================================================================================
18. ALUR UPLOAD FILE SQL
================================================================================

    1. User buka /upload
    2. Pilih file .sql + mode (replace / append)
    3. Klik "Upload File"
    4. JS kirim FormData ke /actions/upload-sql
    5. proses_upload_sql.php:
            a. Validasi file (ekstensi, size, MIME)
            b. Hitung file_hash (SHA256 file asli)
            c. Insert s_upload_batch (status='uploaded')
            d. BEGIN TRANSACTION
            e. Mode replace -> DELETE FROM s_backup_raw
            f. Baca file line-by-line
            g. Tiap statement INSERT -> insert ke s_backup_raw
            h. Hitung raw_hash
            i. Update s_upload_batch (total_rows, raw_hash)
            j. COMMIT
            k. Log aktivitas upload
            l. Return JSON
    6. JS tampilkan notifikasi sukses + refresh halaman
    7. Halaman upload sekarang menampilkan:
            - Badge "Ada X baris belum disimpan"
            - Tombol Preview Data & Kosongkan
            - Riwayat upload dengan status BELUM DI-UPLOAD


================================================================================
19. ALUR PREVIEW DATA
================================================================================

    1. User klik "Preview Data" di /upload
    2. JS kirim POST ke /actions/preview-data
    3. proses_preview_data.php:
            a. Baca semua baris dari s_backup_raw
            b. Parse tiap baris dengan sql_parse_row()
            c. Kelompokkan ke 10 entity
            d. Skip baris tidak valid
            e. Return statistik + sample 15 baris
    4. JS simpan response ke sessionStorage
    5. Redirect ke /generate
    6. generate.php:
            a. Baca sessionStorage.preview_data
            b. Cek umur data (< 5 menit)
            c. Cek stats.raw_rows cocok dengan COUNT(*) s_backup_raw
            d. Render:
                    - 10 stat cards
                    - Tabel per entity (sample 10 baris)
                    - Tombol "Simpan ke Database"
            e. Jika sessionStorage hilang:
                    - Tampilkan tombol "Muat Preview"
                    - Fetch ulang ke /actions/preview-data


================================================================================
20. ALUR SIMPAN KE DATABASE
================================================================================

    1. User klik "Simpan ke Database" di /generate
    2. Konfirmasi SweetAlert
    3. JS kirim POST ke /actions/simpan-hasil
    4. proses_simpan_hasil.php:
            a. Baca semua baris dari s_backup_raw
            b. BEGIN TRANSACTION
            c. Loop tiap baris:
                    - Parse dengan sql_parse_row()
                    - Insert m_pasien (dedup by no_rm)
                    - Insert m_tenaga_kesehatan (dedup by nama)
                    - Insert t_kunjungan (dedup by no_transaksi)
                    - Insert t_pemeriksaan
                    - Insert t_diagnosis (multi row per kunjungan)
                    - Insert t_tindakan
                    - Insert t_resep + d_resep_detail
                    - Insert t_laboratorium
            d. COMMIT
            e. TRUNCATE s_backup_raw (auto-clear)
            f. Hitung result_hash dari 10 tabel
            g. Update s_upload_batch:
                    - result_hash = <hash>
                    - status = 'generated'
                    - verify_detail = JSON detail
            h. Log aktivitas simpan_hasil
            i. Return JSON stats
    5. JS tampilkan notifikasi sukses
    6. Auto-redirect ke /dashboard
    7. Halaman upload sekarang:
            - Badge "belum disimpan" hilang (s_backup_raw kosong)
            - Riwayat upload status: SUDAH DI-UPLOAD (hijau)


================================================================================
21. ALUR VERIFIKASI HASH CHAIN
================================================================================

    1. User buka /verifikasi
    2. Pilih batch dari daftar (klik tombol Verifikasi)
    3. verifikasi.php:
            a. Baca data batch dari s_upload_batch
            b. Cek apakah batch ini yang terakhir
            c. Cek jumlah baris s_backup_raw untuk batch ini
            d. Hitung raw_hash saat ini
            e. Hitung result_hash saat ini
            f. Tentukan status:
                    - status=failed -> overall='failed'
                    - bukan batch terakhir && raw=0 -> 'superseded'
                    - raw_hash kosong -> 'incomplete_upload'
                    - result_hash kosong -> 'not_generated'
                    - semua cocok -> 'verified'
                    - tidak cocok -> 'mismatch'
            g. Tampilkan hash chain detail
            h. Tombol Tandai Terverifikasi (jika status=verified)
    4. Klik "Tandai Terverifikasi":
            a. POST ke /actions/mark-verified
            b. Hitung ulang hash, bandingkan
            c. Jika cocok -> update status='verified', verify_result='MATCH'
            d. Jika tidak -> update status='mismatch', verify_result='MISMATCH'


================================================================================
22. ALUR BACKUP ALL RECORD DB
================================================================================

    1. User buka /backup-all
    2. Halaman menampilkan:
            - Info database (nama, versi, total tabel, total baris, ukuran)
            - Form download (format: sql / json, mode: full / structure / data)
            - Daftar tabel (maks 10 baris + tombol Lihat Semua)
    3. User klik "Download Backup"
    4. JS kirim POST ke /actions/download-backup
    5. proses_download_backup.php:
            a. Ambil daftar semua tabel
            b. Set header download
            c. Generate file streaming:
                    SQL:
                        - Header komentar
                        - SET SQL_MODE, time_zone, NAMES
                        - SET FOREIGN_KEY_CHECKS = 0
                        - Jika mode=full: DROP DATABASE + CREATE DATABASE
                        - Per tabel:
                                - Drop + Create Table
                                - LOCK TABLES
                                - INSERT batched (500 baris per statement)
                                - UNLOCK TABLES
                        - SET FOREIGN_KEY_CHECKS = 1
                        - Footer komentar
                    JSON:
                        - meta (database, file, mode, created_at, dll)
                        - tables:
                                - structure (CREATE TABLE SQL)
                                - row_count
                                - columns
                                - data (array of rows)
    6. Browser auto-download file
    7. Log aktivitas download_backup
    8. User simpan file di tempat aman


================================================================================
23. ALUR RESET DATA
================================================================================

RESET DATA (10 tabel normalisasi):
    1. User buka /about
    2. Klik "Reset Data"
    3. Konfirmasi SwalFire (ketik "RESET" + password)
    4. POST ke /actions/reset-all
    5. proses_reset_all.php:
            a. Verifikasi password user
            b. Cek user NRK 8813084
            c. TRUNCATE 13 tabel (urutan: log -> transaksi -> master)
            d. Kosongkan file logs/*.log
            e. Log aktivitas reset_all

CATATAN:
    - m_pegawai TIDAK dihapus (agar tetap bisa login)
    - Log reset_all muncul 1 baris (jejak audit)
    - s_backup_raw dikosongkan
    - s_upload_batch dikosongkan (semua riwayat upload hilang)


================================================================================
24. ALUR PRINT RESUME MEDIS
================================================================================

    1. User buka /resume-medis?id={pasien_id}
    2. Halaman menampilkan:
            - SCREEN: tombol Kembali + Cetak, identitas pasien (grid),
              statistik 4 kartu, timeline kunjungan
            - PRINT: kop surat, identitas pasien (tabel),
              riwayat kunjungan (tabel), tanda tangan
    3. User klik "Cetak" atau Ctrl+P
    4. JS panggil window.print() dengan delay 100ms
    5. Browser tampilkan dialog print
    6. CSS @media print:
            - Sembunyikan sidebar, header, footer, tombol
            - Sembunyikan statistik (screen-only)
            - Tampilkan kop surat (print-only)
            - Ubah layout ke tabel rapi
            - Font Arial 9-10pt
            - Page break control
    7. User pilih "Save as PDF" atau printer fisik


================================================================================
25. KEAMANAN
================================================================================

    [✓] CSRF token di semua form POST
    [✓] Password di-hash dengan bcrypt (password_hash)
    [✓] Login attempt dibatasi (5x salah -> lock 15 menit)
    [✓] Session timeout otomatis (default 30 menit)
    [✓] Session regenerate ID saat login
    [✓] .htaccess blokir akses ke:
            - .env
            - config/, core/, bootstrap/, pages/, layouts/
            - actions/*.php (harus via index.php)
            - File .sql, .log, .ini, .bak, .zip
    [✓] Security headers:
            - X-Content-Type-Options: nosniff
            - X-Frame-Options: SAMEORIGIN
            - X-XSS-Protection
            - Referrer-Policy
            - Permissions-Policy
    [✓] PDO prepared statement (anti SQL injection)
    [✓] htmlspecialchars() untuk output (anti XSS)
    [✓] Rate limit login per IP
    [✓] Log aktivitas lengkap (audit trail)


================================================================================
26. KONFIGURASI PENTING (.env)
================================================================================

File: .env
Path: C:\xampp\htdocs\backuprm\.env

+--------------------------+----------------------------------------+
| Variabel                 | Deskripsi                              |
+--------------------------+----------------------------------------+
| APP_NAME                 | Nama aplikasi                          |
| APP_ENV                  | development / production               |
| APP_DEBUG                | true (dev) / false (prod)              |
| APP_TIMEZONE             | Asia/Jakarta                           |
| DB_HOST                  | Host database                          |
| DB_PORT                  | Port database (3306)                   |
| DB_NAME                  | Nama database (db_backuprm)            |
| DB_USER                  | User database                          |
| DB_PASS                  | Password database                      |
| DB_CHARSET               | utf8mb4                                |
| SESSION_LIFETIME         | Timeout session (detik) — 1800 = 30 m  |
| SESSION_NAME             | Nama cookie session                    |
| UPLOAD_MAX_SIZE          | Maks upload (byte) — 52428800 = 50 MB  |
+--------------------------+----------------------------------------+

SESSION_LIFETIME rekomendasi:
    600    = 10 menit (pendek, banyak logout)
    1800   = 30 menit (rekomendasi)
    3600   = 1 jam
    7200   = 2 jam


================================================================================
27. TROUBLESHOOTING
================================================================================

MASALAH: Error 500 setelah upload .htaccess
SOLUSI : Cek syntax .htaccess, restart Apache

MASALAH: Halaman putih / blank
SOLUSI : Set APP_DEBUG=true di .env, lihat error di
         logs/error.log

MASALAH: Invalid parameter number (PDO)
SOLUSI : Placeholder :q dipakai > 1x dalam 1 query.
         Ganti ke :q1, :q2, :q3 (unik).

MASALAH: File upload gagal (413)
SOLUSI : Naikkan post_max_size & upload_max_filesize di php.ini

MASALAH: Verifikasi selalu MISMATCH
SOLUSI : Cek apakah s_backup_raw masih ada.
         Batch lama yang sudah dihapus = superseded (normal).

MASALAH: Logout mendadak
SOLUSI : Cek SESSION_LIFETIME di .env.
         Pastikan footer.php & proses_ping.php sudah direvisi.

MASALAH: Redirect loop saat login
SOLUSI : Cek BASE_URL di config_core.php
         Cek RewriteBase di .htaccess

MASALAH: Preview data tidak muncul di /generate
SOLUSI : Klik tombol "Muat Preview" (fallback otomatis).
         Cek Console browser, response /actions/preview-data.

MASALAH: sessionStorage hilang
SOLUSI : Buka tab baru -> langsung /generate tanpa klik Preview
         -> akan muncul fallback "Muat Preview"

MASALAH: File backup > 100MB tidak terdownload
SOLUSI : Gunakan format .sql (streaming)
         Jangan pakai .json untuk database besar


================================================================================
28. CATATAN PRODUCTION
================================================================================

Sebelum deploy ke server publik:

    [ ] Ubah APP_DEBUG=false di .env
    [ ] Ganti DB_USER (jangan root)
    [ ] Set DB_PASS yang kuat
    [ ] Aktifkan HTTPS
    [ ] Set SESSION_LIFETIME wajar (1800-3600)
    [ ] Hapus file .sql dari webroot (db_backuprm.sql, dll)
    [ ] Hapus file .zip dari webroot
    [ ] Pastikan .htaccess aktif (AllowOverride All)
    [ ] Backup rutin database + folder backups/
    [ ] Monitor logs/error.log berkala
    [ ] Ganti password default akun admin
    [ ] Batasi akses via IP whitelist (jika perlu)
    [ ] Disable PHP display_errors
    [ ] Set timezone server ke Asia/Jakarta


================================================================================
29. BACKUP RUTIN
================================================================================

YANG PERLU DI-BACKUP:
    - Database db_backuprm (via mysqldump atau
      halaman Backup All Record DB)
    - Folder backups/ (file SQL yang diarsipkan)
    - File logs/activity.log (audit trail)
    - File logs/error.log (error log)

TIDAK PERLU DI-BACKUP:
    - Folder uploads/ (temporary)
    - Folder logs/*.log (optional, sudah ada di DB)

JADWAL REKOMENDASI:
    - Backup harian: skrip otomatis via cron / Task Scheduler
    - Backup mingguan: full backup (semua tabel)
    - Backup bulanan: arsip jangka panjang

CONTOH COMMAND MYSQLDUMP:
    mysqldump -u root -p db_backuprm > backup_YYYY-MM-DD.sql


================================================================================
30. KONTAK & BANTUAN
================================================================================

Untuk pertanyaan teknis atau bug report:
    - Hubungi tim IT Puskesmas Setiabudi
    - WhatsApp: 0815-8845-543
    - Email: puskesmas.setiabudi@jakarta.go.id

Dikembangkan untuk:
    Puskesmas Setiabudi
    Jl. Setiabudi Raya No. 1, Jakarta Selatan 12910

Stack:
    PHP 8.2 + MySQL 10.4
    Tailwind CSS + Remixicon
    SweetAlert2, DataTables, Select2, Chart.js
    jQuery 3.7


================================================================================
                              [ END OF README ]
                           Backup RM — Version 1.0.0
================================================================================