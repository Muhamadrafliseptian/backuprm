<?php
/**
 * file   : login.php
 * path   : C:\xampp\htdocs\backuprm\pages\login.php
 * fungsi : Halaman login dengan konsep glass + Plus Jakarta Sans
 */
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/internal_guard.php';
$err = flash('error');
$suc = flash('success');

// Cek apakah logout karena timeout
$autoLogout = !empty($_SESSION['_auto_logout']) ? true : false;
if ($autoLogout) {
    unset($_SESSION['_auto_logout']);
}

?>
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= e(APP_NAME) ?></title>
    <link rel="icon" type="image/svg+xml"
          href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='20' fill='%2314b8a6'/%3E%3Ctext x='50' y='68' font-size='56' text-anchor='middle' fill='white' font-family='sans-serif' font-weight='bold'%3ERM%3C/text%3E%3C/svg%3E">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'] },
                    colors: {
                        mint: {
                            50:  '#f0fdf9',
                            100: '#ccfbf1',
                            200: '#99f6e4',
                            300: '#5eead4',
                            400: '#2dd4bf',
                            500: '#14b8a6',
                            600: '#0d9488',
                            700: '#0f766e',
                            800: '#115e59',
                            900: '#134e4a',
                        }
                    }
                }
            }
        }
    </script>

    <!-- Remixicon -->
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">

    <!-- Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <!-- NProgress -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/nprogress/0.2.0/nprogress.min.css">

    <style>
        .glass-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(226, 232, 240, 0.8);
        }
        .bg-mesh {
            background-color: #fafbfb;
            background-image:
                radial-gradient(at 10% 10%, rgba(20, 184, 166, 0.08) 0px, transparent 50%),
                radial-gradient(at 90% 90%, rgba(94, 234, 212, 0.12) 0px, transparent 50%);
        }
        #nprogress .bar { background: #14b8a6 !important; height: 3px !important; }
        #nprogress .peg { box-shadow: 0 0 10px #14b8a6, 0 0 5px #14b8a6 !important; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 9999px; }
    </style>
</head>
<body class="bg-mesh font-sans text-slate-800 antialiased min-h-screen flex flex-col justify-between selection:bg-mint-500 selection:text-white">

    <!-- MAIN CONTAINER -->
    <main class="flex-grow flex items-center justify-center p-4 sm:p-6 md:p-8">

        <div class="w-full max-w-md">
            <div class="glass-card rounded-3xl p-8 shadow-xl shadow-mint-900/5 relative overflow-hidden">
                <!-- Top Accent Line -->
                <div class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-mint-300 via-mint-500 to-mint-600"></div>

                <!-- Logo & Title Header -->
                <div class="text-center mb-8 pt-2">
                    <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-mint-50 text-mint-600 mb-4 shadow-inner border border-mint-100">
                        <i class="ri-shield-keyhole-line text-2xl"></i>
                    </div>
                    <h1 class="text-2xl font-bold text-slate-900 tracking-tight"><?= e(APP_NAME) ?></h1>
                    <p class="text-slate-500 text-sm mt-1">Sistem Backup & Verifikasi Rekam Medis</p>
                </div>

                <!-- Alert Notice -->
                <div class="mb-6 p-3.5 bg-mint-50/80 border border-mint-200/60 rounded-xl flex items-start gap-3">
                    <i class="ri-information-fill text-mint-600 text-lg shrink-0 mt-0.5"></i>
                    <p class="text-xs text-mint-900 leading-relaxed">
                        Akses khusus petugas otorisasi. Seluruh aktivitas login dicatat dalam log keamanan.
                    </p>
                </div>

                <!-- Form Login -->
                <form method="post" action="<?= base_url('actions/login') ?>" id="loginForm" autocomplete="off" class="space-y-5">
                    <?= csrf_field() ?>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-2">NRK</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <i class="ri-user-3-line text-lg"></i>
                            </span>
                            <input type="text" name="nrk" required autofocus
                                   inputmode="numeric" pattern="[0-9]*" maxlength="30"
                                   placeholder="Masukkan NRK"
                                   class="w-full pl-10 pr-4 py-3 text-sm bg-white border border-slate-200 rounded-xl focus:outline-none focus:border-mint-500 focus:ring-4 focus:ring-mint-500/10 transition-all font-medium placeholder:font-normal placeholder:text-slate-400">
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600">Kata Sandi</label>
                            <a href="#" onclick="alert('Silakan hubungi Unit IT / System Administrator untuk reset kata sandi.')" class="text-xs text-mint-600 hover:text-mint-700 font-medium">Lupa sandi?</a>
                        </div>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <i class="ri-lock-line text-lg"></i>
                            </span>
                            <input type="password" name="password" id="passwordInput" required
                                   placeholder="••••••••"
                                   class="w-full pl-10 pr-11 py-3 text-sm bg-white border border-slate-200 rounded-xl focus:outline-none focus:border-mint-500 focus:ring-4 focus:ring-mint-500/10 transition-all font-medium placeholder:text-slate-400">
                            <button type="button" onclick="togglePassword()" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 transition-colors">
                                <i class="ri-eye-line text-lg" id="toggleIcon"></i>
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-1">
                        <label class="flex items-center cursor-pointer select-none">
                            <input type="checkbox" class="w-4 h-4 rounded border-slate-300 text-mint-600 focus:ring-mint-500 rounded-sm">
                            <span class="ml-2.5 text-xs font-medium text-slate-600">Ingat perangkat ini</span>
                        </label>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" id="btnSubmit"
                            class="w-full py-3.5 px-4 bg-mint-500 hover:bg-mint-600 text-white font-semibold rounded-xl shadow-lg shadow-mint-500/25 active:scale-[0.99] transition-all flex items-center justify-center gap-2">
                        <span>Masuk ke Dashboard</span>
                        <i class="ri-arrow-right-line"></i>
                    </button>
                </form>

                <!-- Footer Note -->
                <div class="mt-8 pt-6 border-t border-slate-100 text-center">
					<p class="text-xs text-slate-400">
						Membutuhkan bantuan teknis? 
						<a href="https://wa.me/628158845543?text=Halo%20Tim%20IT%2C%20saya%20butuh%20bantuan%20terkait%20aplikasi%20Backup%20RM." 
						   target="_blank" 
						   rel="noopener noreferrer"
						   class="text-mint-600 font-medium hover:underline inline-flex items-center gap-1">
							<i class="ri-whatsapp-line"></i> Kontak Tim IT
						</a>
					</p>
                </div>
            </div>
        </div>

    </main>

    <!-- FOOTER -->
    <footer class="py-4 text-center text-xs text-slate-400">
        &copy; <?= date('Y') ?> <?= e(APP_NAME) ?> • Security Standard Level 4
    </footer>

    <!-- JS -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/nprogress/0.2.0/nprogress.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        NProgress.configure({ showSpinner: false });

        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('loginForm');
            if (form) {
                form.addEventListener('submit', function () {
                    const btn = document.getElementById('btnSubmit');
                    btn.disabled = true;
                    btn.innerHTML = '<i class="ri-loader-4-line animate-spin text-lg"></i> <span>Memverifikasi...</span>';
                    NProgress.start();
                });
            }
        });

        function togglePassword() {
            const pwd = document.getElementById('passwordInput');
            const icon = document.getElementById('toggleIcon');
            if (pwd.type === 'password') {
                pwd.type = 'text';
                icon.className = 'ri-eye-off-line text-lg text-mint-600';
            } else {
                pwd.type = 'password';
                icon.className = 'ri-eye-line text-lg';
            }
        }
    </script>
<?php if ($autoLogout): ?>
<script>
    Swal.fire({
        icon: 'info',
        title: 'Sesi Berakhir',
        html: 'Anda otomatis logout karena tidak ada aktivitas selama <b><?= round(SESSION_LIFETIME / 60) ?> menit</b>.<br><br>Silakan login kembali untuk melanjutkan.',
        confirmButtonColor: '#14b8a6',
        confirmButtonText: 'OK'
    });
</script>
<?php endif; ?>
    <?php if ($err): ?>
    <script>
        Swal.fire({
            icon: 'error',
            title: 'Login Gagal',
            text: <?= json_encode($err) ?>,
            confirmButtonColor: '#14b8a6',
            confirmButtonText: 'Coba Lagi'
        });
    </script>
    <?php endif; ?>

    <?php if ($suc): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Berhasil',
            text: <?= json_encode($suc) ?>,
            confirmButtonColor: '#14b8a6',
            confirmButtonText: 'OK',
            timer: 2500,
            timerProgressBar: true
        });
    </script>
    <?php endif; ?>

</body>
</html>