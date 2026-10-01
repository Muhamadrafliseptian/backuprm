<?php
/**
 * file   : generate.php
 * path   : C:\xampp\htdocs\backuprm\pages\generate.php
 * fungsi : Preview + tombol Simpan (Tailwind) — max 10 baris per tabel
 */
declare(strict_types=1);

require BASE_PATH . '/layouts/header.php';
require BASE_PATH . '/layouts/sidebar.php';

$countRaw = (int)db()->query("SELECT COUNT(*) FROM s_backup_raw")->fetchColumn();
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 fade-in-up">
    <div>
        <h2 class="text-xl md:text-2xl font-bold text-slate-900 tracking-tight">Preview Data</h2>
        <p class="text-xs md:text-sm text-slate-500">Periksa data. Klik <b>Simpan ke Database</b> jika sudah sesuai.</p>
    </div>
    <a href="<?= base_url('upload') ?>"
       class="flex items-center gap-1.5 px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-colors shadow-sm">
        <i class="ri-arrow-left-line"></i> Kembali
    </a>
</div>

<?php if ($countRaw === 0): ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm">
        <div class="p-10 text-center">
            <i class="ri-inbox-archive-line text-5xl text-slate-300"></i>
            <h3 class="text-lg font-bold text-slate-800 mt-3">Tidak Ada Data Preview</h3>
            <p class="text-xs text-slate-500 mt-1">
                Tabel <code class="text-mint-600">s_backup_raw</code> kosong. Upload file SQL dulu.
            </p>
            <a href="<?= base_url('upload') ?>"
               class="inline-flex items-center gap-1.5 bg-mint-500 hover:bg-mint-600 text-white text-xs font-bold px-5 py-2.5 rounded-xl mt-4 transition-all shadow-md shadow-mint-500/20">
                <i class="ri-upload-cloud-line"></i> Ke Halaman Upload
            </a>
        </div>
    </div>
    <script>sessionStorage.removeItem('preview_data');</script>
<?php else: ?>
    <div id="previewArea">
        <div class="text-center py-12">
            <div class="inline-block w-10 h-10 border-4 border-mint-500 border-t-transparent rounded-full animate-spin"></div>
            <p class="text-xs text-slate-500 mt-3">Memuat preview...</p>
        </div>
    </div>

    <!-- Fallback: kalau sessionStorage hilang, tampilkan tombol generate manual -->
    <div id="fallbackArea" class="hidden bg-amber-50 border border-amber-200 rounded-2xl p-6">
        <div class="flex items-start gap-3">
            <i class="ri-alert-line text-amber-500 text-2xl"></i>
            <div class="flex-1">
                <h3 class="font-bold text-amber-900">Preview Belum Dimuat</h3>
                <p class="text-xs text-amber-700 mt-1">
                    Silakan klik tombol di bawah untuk memuat preview dari <code>s_backup_raw</code>.
                </p>
                <button type="button" id="btnLoadPreview"
                        class="mt-3 inline-flex items-center gap-1.5 bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold px-5 py-2.5 rounded-xl transition-all shadow-md shadow-amber-500/20">
                    <i class="ri-refresh-line"></i> Muat Preview
                </button>
            </div>
        </div>
    </div>

    <div id="actionBar" class="hidden bg-white rounded-2xl border-l-4 border-mint-500 border border-slate-100 shadow-sm mt-6">
        <div class="p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <div class="font-semibold text-slate-800 flex items-center gap-2">
                    <i class="ri-information-line text-mint-600"></i> Data siap disimpan
                </div>
                <div class="text-xs text-slate-500 mt-0.5" id="actionSummary">—</div>
            </div>
            <div class="flex gap-2">
                <a href="<?= base_url('upload') ?>"
                   class="flex items-center gap-1.5 px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition-colors">
                    <i class="ri-close-line"></i> Batal
                </a>
                <button type="button" id="btnSimpan"
                        class="flex items-center gap-1.5 bg-mint-500 hover:bg-mint-600 text-white text-xs font-bold px-5 py-2.5 rounded-xl transition-all shadow-md shadow-mint-500/20">
                    <i class="ri-database-2-line"></i> Simpan ke Database
                </button>
            </div>
        </div>
    </div>

    <script>
    /* ============================================================
       KONFIGURASI
       ============================================================ */
    const PREVIEW_MAX_AGE_MS = 5 * 60 * 1000;   // 5 menit
    const MAX_ROWS_PER_TABLE = 10;
    const BACKUP_COUNT       = <?= (int)$countRaw ?>;

    /* ============================================================
       KOLOM PRIORITAS
       ============================================================ */
    const PRIORITY_COLS = {
        m_pasien:           ['no_rm','nama','nik','jenis_kelamin','tanggal_lahir','no_telepon','status_kepesertaan','tempat_lahir','alamat'],
        t_kunjungan:        ['no_transaksi','no_rm','tanggal_kunjungan','jam_kunjungan','sumber_data','petugas_input'],
        t_pemeriksaan:      ['no_transaksi','kesadaran','nadi','respiratory_rate','tekanan_darah','suhu','tinggi_badan','berat_badan'],
        t_diagnosis:        ['no_transaksi','kode_icd','nama_diagnosis','jenis_diagnosis','urutan'],
        t_tindakan:         ['no_transaksi','nama_tindakan','nama_pelaksana','tanggal_tindakan','jam_tindakan'],
        m_tenaga_kesehatan: ['nama','jenis_tenaga','no_str','no_sip','nip','status'],
        t_resep:            ['no_transaksi','nama_dokter','tanggal_resep','jam_resep'],
        d_resep_detail:     ['no_transaksi','nama_obat','nama_obat_snapshot','jumlah','aturan_pakai'],
        m_obat:             ['nama_obat','kode_obat','bentuk_sediaan','kekuatan','satuan','status'],
        t_laboratorium:     ['no_transaksi','tanggal','jam','hasil']
    };

    /* ============================================================
       ENTITY META
       ============================================================ */
    const ENTITY_LABELS = {
        m_pasien:           { label: 'Pasien',           icon: 'ri-group-line' },
        t_kunjungan:        { label: 'Kunjungan',        icon: 'ri-notes-medical-line' },
        t_pemeriksaan:      { label: 'Pemeriksaan',      icon: 'ri-stethoscope-line' },
        t_diagnosis:        { label: 'Diagnosis',        icon: 'ri-diagnoses-line' },
        t_tindakan:         { label: 'Tindakan',         icon: 'ri-syringe-line' },
        m_tenaga_kesehatan: { label: 'Tenaga Kesehatan', icon: 'ri-user-heart-line' },
        t_resep:            { label: 'Resep',            icon: 'ri-prescription-line' },
        d_resep_detail:     { label: 'Detail Resep',     icon: 'ri-capsule-line' },
        m_obat:             { label: 'Obat',             icon: 'ri-capsule-line' },
        t_laboratorium:     { label: 'Laboratorium',     icon: 'ri-flask-line' }
    };

    /* ============================================================
       INIT
       ============================================================ */
    document.addEventListener('DOMContentLoaded', function () {
        const raw     = sessionStorage.getItem('preview_data');
        const rawTime = sessionStorage.getItem('preview_data_ts');

        // Cek sessionStorage
        if (!raw || !rawTime || (Date.now() - parseInt(rawTime, 10)) > PREVIEW_MAX_AGE_MS) {
            showFallback();
            return;
        }

        let data;
        try { data = JSON.parse(raw); }
        catch (e) { showFallback(); return; }

        if ((data.stats?.raw_rows || 0) !== BACKUP_COUNT) {
            showFallback();
            return;
        }

        renderPreview(data);
    });

    /* ============================================================
       FALLBACK: sessionStorage hilang
       ============================================================ */
    function showFallback() {
        sessionStorage.removeItem('preview_data');
        sessionStorage.removeItem('preview_data_ts');
        document.getElementById('previewArea').innerHTML = '';
        document.getElementById('fallbackArea').classList.remove('hidden');

        // Bind tombol muat preview
        document.getElementById('btnLoadPreview').addEventListener('click', function () {
            loadPreviewFromServer();
        });
    }

    function loadPreviewFromServer() {
        const btn = document.getElementById('btnLoadPreview');
        btn.disabled = true;
        btn.innerHTML = '<i class="ri-loader-4-line animate-spin"></i> Memuat...';

        NProgress.start();
        fetch(window.BASE_URL + '/actions/preview-data', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': window.CSRF_TOKEN,
                'Content-Type': 'application/x-www-form-urlencoded'
            }
        })
        .then(r => r.text().then(text => {
            try { return { ok: true, json: JSON.parse(text) }; }
            catch (e) { return { ok: false, text }; }
        }))
        .then(res => {
            NProgress.done();
            if (!res.ok) {
                btn.disabled = false;
                btn.innerHTML = '<i class="ri-refresh-line"></i> Muat Preview';
                Swal.fire({
                    icon: 'error',
                    title: 'Response bukan JSON',
                    html: '<pre style="text-align:left;font-size:11px;max-height:300px;overflow:auto">' +
                          escapeHtml(String(res.text).substring(0, 2000)) + '</pre>',
                    width: 700
                });
                return;
            }

            const json = res.json;
            if (!json.ok) {
                btn.disabled = false;
                btn.innerHTML = '<i class="ri-refresh-line"></i> Muat Preview';
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal Memuat',
                    text: json.msg || 'Terjadi kesalahan.'
                });
                return;
            }

            // Simpan ke sessionStorage
            sessionStorage.setItem('preview_data', JSON.stringify(json));
            sessionStorage.setItem('preview_data_ts', Date.now().toString());

            // Render
            document.getElementById('fallbackArea').classList.add('hidden');
            renderPreview(json);
        })
        .catch(err => {
            NProgress.done();
            btn.disabled = false;
            btn.innerHTML = '<i class="ri-refresh-line"></i> Muat Preview';
            Swal.fire({
                icon: 'error',
                title: 'Kesalahan Jaringan',
                text: err.toString()
            });
        });
    }

    /* ============================================================
       RENDER PREVIEW
       ============================================================ */
    function renderPreview(data) {
        const stats  = data.stats  || {};
        const sample = data.sample || {};
        const keyOrder = [
            'm_pasien','t_kunjungan','t_pemeriksaan','t_diagnosis','t_tindakan',
            'm_tenaga_kesehatan','t_resep','d_resep_detail','m_obat','t_laboratorium'
        ];

        /* ---------- Stat Cards ---------- */
        let html = '<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3 md:gap-4 mb-6">';
        keyOrder.forEach(k => {
            const meta  = ENTITY_LABELS[k] || { label: k, icon: 'ri-database-2-line' };
            const total = stats[k] || 0;
            html +=
                '<div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">' +
                    '<div class="flex items-center gap-2 mb-2">' +
                        '<div class="p-2 bg-mint-50 text-mint-600 rounded-lg">' +
                            '<i class="' + meta.icon + ' text-base"></i>' +
                        '</div>' +
                        '<div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">' +
                            meta.label +
                        '</div>' +
                    '</div>' +
                    '<div class="text-xl font-bold text-slate-900">' +
                        total.toLocaleString('id-ID') +
                    '</div>' +
                '</div>';
        });
        html += '</div>';

        /* ---------- Skipped Warning ---------- */
        if (stats.skipped > 0) {
            html +=
                '<div class="bg-amber-50 border border-amber-200 text-amber-700 text-xs rounded-xl p-3 mb-4 flex items-start gap-2">' +
                    '<i class="ri-alert-line mt-0.5"></i>' +
                    '<span><b>' + stats.skipped + '</b> baris dilewati (data tidak lengkap).</span>' +
                '</div>';
        }

        /* ---------- Tabel per Entity ---------- */
        keyOrder.forEach(k => {
            const s = sample[k];
            if (!s || !s.total) return;

            const meta = ENTITY_LABELS[k] || { label: k, icon: 'ri-database-2-line' };
            const rows = (s.sample || []).slice(0, MAX_ROWS_PER_TABLE);

            if (rows.length === 0) return;

            const allCols = Object.keys(rows[0]);
            const priority = PRIORITY_COLS[k] || [];
            const orderedCols = [
                ...priority.filter(c => allCols.includes(c)),
                ...allCols.filter(c => !priority.includes(c))
            ];

            /* ---------- Build Table ---------- */
            let table = '<div class="overflow-x-auto max-h-[420px] overflow-y-auto">';
            table += '<table class="w-full text-left text-xs min-w-max">';
            table += '<thead class="bg-slate-50/95 backdrop-blur-sm text-slate-500 uppercase tracking-wider font-semibold border-b border-slate-100 sticky top-0 z-10">';
            table += '<tr>';
            table += '<th class="py-3 px-3 text-center" style="width:40px">#</th>';
            orderedCols.forEach(c => {
                const isPriority = priority.includes(c);
                const cls = isPriority ? 'text-mint-700' : '';
                table += '<th class="py-3 px-3 whitespace-nowrap ' + cls + '">' + escapeHtml(c) + '</th>';
            });
            table += '</tr></thead>';

            table += '<tbody class="divide-y divide-slate-100 font-medium text-slate-600">';
            rows.forEach((r, idx) => {
                table += '<tr class="hover:bg-slate-50/80">';
                table += '<td class="py-2.5 px-3 text-center text-slate-400">' + (idx + 1) + '</td>';
                orderedCols.forEach(c => {
                    let v = r[c];
                    if (v === null || v === undefined || v === '') {
                        v = '<span class="text-slate-300">-</span>';
                    } else {
                        const str = String(v);
                        const display = str.length > 80 ? str.substring(0, 80) + '…' : str;
                        v = '<span title="' + escapeHtml(str) + '">' + escapeHtml(display) + '</span>';
                    }
                    table += '<td class="py-2.5 px-3 align-top">' + v + '</td>';
                });
                table += '</tr>';
            });
            table += '</tbody></table></div>';

            const shown      = rows.length;
            const totalCount = s.total;
            const isTruncated = totalCount > shown;

            const note =
                '<div class="text-[11px] text-slate-400 px-4 py-3 border-t border-slate-100 flex items-center justify-between flex-wrap gap-2">' +
                    '<span>Menampilkan <b class="text-slate-600">' + shown + '</b> dari <b class="text-slate-600">' + totalCount.toLocaleString('id-ID') + '</b> baris</span>' +
                    (isTruncated
                        ? '<span class="inline-flex items-center gap-1 text-amber-600 bg-amber-50 px-2 py-0.5 rounded-full font-semibold"><i class="ri-information-line"></i> Preview dibatasi ' + MAX_ROWS_PER_TABLE + ' baris</span>'
                        : '<span class="text-mint-600 font-semibold"><i class="ri-check-line"></i> Semua baris ditampilkan</span>'
                    ) +
                '</div>';

            html +=
                '<div class="bg-white rounded-2xl border border-slate-100 shadow-sm mb-4 overflow-hidden">' +
                    '<div class="p-4 border-b border-slate-100 flex items-center justify-between flex-wrap gap-2">' +
                        '<div class="flex items-center gap-2">' +
                            '<i class="' + meta.icon + ' text-mint-600 text-lg"></i>' +
                            '<b class="text-sm text-slate-800">' + meta.label + '</b>' +
                            '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-mint-50 text-mint-700 border border-mint-200/60">' + totalCount.toLocaleString('id-ID') + '</span>' +
                        '</div>' +
                        '<span class="text-[11px] text-slate-400">' + orderedCols.length + ' kolom</span>' +
                    '</div>' +
                    table +
                    note +
                '</div>';
        });

        document.getElementById('previewArea').innerHTML = html;

        /* ---------- Action Bar ---------- */
        const totalAll = keyOrder.reduce((sum, k) => sum + (stats[k] || 0), 0);
        document.getElementById('actionSummary').textContent =
            'Total ' + totalAll.toLocaleString('id-ID') + ' baris akan disimpan ke database.';
        document.getElementById('actionBar').classList.remove('hidden');

        /* ---------- Bind Simpan ---------- */
        document.getElementById('btnSimpan').addEventListener('click', function () {
            handleSimpan(totalAll);
        });
    }

    /* ============================================================
       HANDLE SIMPAN
       ============================================================ */
    function handleSimpan(totalAll) {
        Swal.fire({
            icon: 'question',
            title: 'Simpan ke Database?',
            html: 'Anda akan menyimpan <b>' + totalAll.toLocaleString('id-ID') + '</b> baris ke database.<br><br>' +
                  '<small class="text-muted">Data duplikat akan dilewati otomatis.</small>',
            showCancelButton: true,
            confirmButtonText: 'Ya, Simpan',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#7A8793',
            reverseButtons: true
        }).then(r => {
            if (!r.isConfirmed) return;

            Swal.fire({
                title: 'Menyimpan...',
                html: 'Mohon tunggu, jangan tutup halaman ini.',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => Swal.showLoading()
            });

            fetch(window.BASE_URL + '/actions/simpan-hasil', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': window.CSRF_TOKEN,
                    'Content-Type': 'application/x-www-form-urlencoded'
                }
            })
            .then(async (res) => {
                const text = await res.text();
                let json = null;
                try { json = JSON.parse(text); }
                catch (e) {
                    return Swal.fire({
                        icon: 'error',
                        title: 'Response bukan JSON',
                        html: '<pre style="text-align:left;font-size:11px;max-height:300px;overflow:auto">' +
                              escapeHtml(text.substring(0, 2000)) + '</pre>',
                        width: 700
                    });
                }

                if (json.ok) {
                    sessionStorage.removeItem('preview_data');
                    sessionStorage.removeItem('preview_data_ts');
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        html: 'Data berhasil disimpan.<br><br>' +
                              '<small class="text-muted">Pasien: ' + (json.stats.m_pasien || 0) +
                              ', Kunjungan: ' + (json.stats.t_kunjungan || 0) + '</small>',
                        confirmButtonColor: '#10b981',
                        confirmButtonText: 'Lihat Dashboard'
                    }).then(() => {
                        window.location.href = window.BASE_URL + '/dashboard';
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal',
                        text: json.msg || 'Terjadi kesalahan.',
                        confirmButtonColor: '#10b981'
                    });
                }
            })
            .catch(() => {
                Swal.fire({
                    icon: 'error',
                    title: 'Kesalahan Jaringan',
                    text: 'Tidak dapat menghubungi server.',
                    confirmButtonColor: '#10b981'
                });
            });
        });
    }

    /* ============================================================
       ESCAPE HTML
       ============================================================ */
    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, m => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[m]));
    }
    </script>
<?php endif; ?>

<?php require BASE_PATH . '/layouts/footer.php'; ?>