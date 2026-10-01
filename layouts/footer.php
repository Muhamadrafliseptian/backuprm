<?php
/**
 * file   : footer.php
 * path   : C:\xampp\htdocs\backuprm\layouts\footer.php
 * fungsi : Penutup layout + JS library + helper + session timeout sinkron
 */
declare(strict_types=1);
?>
    </main>
</div><!-- /main-area -->
</div><!-- /flex h-screen -->

<!-- ============================================================
     JS LIBRARIES
     ============================================================ -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/nprogress/0.2.0/nprogress.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
    window.BASE_URL         = "<?= BASE_URL ?>";
    window.CSRF_TOKEN       = "<?= csrf_token() ?>";
    window.APP_NAME         = "<?= e(APP_NAME) ?>";
    window.SESSION_LIFETIME = <?= (int) SESSION_LIFETIME ?>;
</script>

<!-- Theme Helper (Modal, Toast, Confirm, Logout) -->
<script src="<?= asset('js/theme-helper.js') ?>"></script>

<!-- Main Helper -->
<script src="<?= asset('js/main.js') ?>"></script>

<!-- ============================================================
     SESSION TIMEOUT — SINKRON SERVER & CLIENT
     ============================================================ -->
<script>
(function () {
    'use strict';
    if (!window.Swal) return;

    var TTL_MS          = (window.SESSION_LIFETIME || 1800) * 1000;  // total idle yang diizinkan
    var WARN_BEFORE_MS  = 60 * 1000;   // warning muncul 60 detik sebelum TTL
    var PING_EVERY_MS   = 5 * 60 * 1000; // kirim ping ke server tiap 5 menit (kalau user aktif)
    var CHECK_EVERY_MS  = 1000;        // cek idle tiap 1 detik
    var ACTIVE_THRESHOLD_MS = 60 * 1000; // dianggap "aktif" kalau idle < 60 detik

    var lastActivity   = Date.now();
    var warningShown   = false;
    var countdownTimer = null;
    var logoutInProgress = false;

    /* ============================================================
       RESET ACTIVITY (dipanggil tiap interaksi user)
       ============================================================ */
    function resetActivity() {
        lastActivity = Date.now();
        if (warningShown) {
            warningShown = false;
            if (countdownTimer) {
                clearInterval(countdownTimer);
                countdownTimer = null;
            }
        }
    }

    ['click', 'keydown', 'mousemove', 'scroll', 'touchstart'].forEach(function (ev) {
        document.addEventListener(ev, resetActivity, { passive: true });
    });

    /* ============================================================
       PING SERVER — sinkron timer server
       ============================================================ */
    function pingServer() {
        return fetch(window.BASE_URL + '/actions/ping', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': window.CSRF_TOKEN
            }
        })
        .then(function (r) {
            if (r.status === 401) {
                // Server sudah expired
                return { ok: false, expired: true };
            }
            return r.json();
        })
        .then(function (res) {
            if (res && res.csrf_token) {
                window.CSRF_TOKEN = res.csrf_token;
            }
            return res;
        })
        .catch(function () {
            return { ok: false };
        });
    }

    /* ============================================================
       PING BERKALA — hanya kalau user masih aktif
       ============================================================ */
    setInterval(function () {
        var idle = Date.now() - lastActivity;
        // Ping hanya kalau user aktif dalam 60 detik terakhir
        if (idle < ACTIVE_THRESHOLD_MS) {
            pingServer();
        }
    }, PING_EVERY_MS);

    /* ============================================================
       CEK IDLE SETIAP DETIK
       ============================================================ */
    setInterval(function () {
        if (logoutInProgress) return;

        var idle = Date.now() - lastActivity;

        /* ---------- Sudah lewat TTL: cek server dulu ---------- */
        if (idle >= TTL_MS) {
            logoutInProgress = true;

            // Tanya server: apakah saya masih login?
            pingServer().then(function (res) {
                if (!res || !res.ok || res.expired) {
                    // Server sudah logout → redirect ke login
                    window.doLogout('timeout');
                } else {
                    // Server masih hidup → reset JS timer
                    logoutInProgress = false;
                    resetActivity();
                }
            });
            return;
        }

        /* ---------- Warning 60 detik sebelum TTL ---------- */
        if (!warningShown && idle >= (TTL_MS - WARN_BEFORE_MS)) {
            warningShown = true;
            var sisa = Math.ceil((TTL_MS - idle) / 1000);

            Swal.fire({
                icon: 'warning',
                title: 'Sesi akan berakhir',
                html:
                    '<div style="font-size:13px;line-height:1.6">' +
                    'Tidak ada aktivitas terdeteksi.<br>' +
                    'Anda akan logout otomatis dalam ' +
                    '<b><span id="__cd">' + sisa + '</span></b> detik.' +
                    '</div>',
                showCancelButton: true,
                confirmButtonText: '<i class="ri-refresh-line"></i> Tetap di sini',
                cancelButtonText: 'Logout sekarang',
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#E63946',
                reverseButtons: true,
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: function () {
                    // Fokus ke tombol "Tetap di sini"
                    var btn = Swal.getConfirmButton();
                    if (btn) btn.focus();
                }
            }).then(function (r) {
                if (r.isConfirmed) {
                    // User klik "Tetap di sini" → ping server + reset
                    pingServer().then(function () {
                        resetActivity();
                    });
                } else {
                    // User klik "Logout sekarang" → langsung logout
                    logoutInProgress = true;
                    window.doLogout('manual');
                }
            });

            /* ---------- Countdown visual ---------- */
            countdownTimer = setInterval(function () {
                var el = document.getElementById('__cd');
                if (!el) return;
                var val = parseInt(el.textContent, 10) - 1;
                if (val <= 0) {
                    clearInterval(countdownTimer);
                    countdownTimer = null;
                    if (!logoutInProgress) {
                        logoutInProgress = true;
                        window.doLogout('timeout');
                    }
                    return;
                }
                el.textContent = val;
            }, 1000);
        }
    }, CHECK_EVERY_MS);

    /* ============================================================
       KIRIM PING SAAT TAB DITUTUP / REFRESH (best-effort)
       ============================================================ */
    window.addEventListener('beforeunload', function () {
        // Kirim ping terakhir — pakai sendBeacon (tidak blocking)
        try {
            var data = new Blob(
                ['_token=' + encodeURIComponent(window.CSRF_TOKEN)],
                { type: 'application/x-www-form-urlencoded' }
            );
            navigator.sendBeacon(window.BASE_URL + '/actions/ping', data);
        } catch (e) {
            // ignore
        }
    });

})();
</script>
</body>
</html>