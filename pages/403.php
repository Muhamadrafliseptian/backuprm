<?php
/**
 * file   : 403.php
 * path   : C:\xampp\htdocs\backuprm\pages\403.php
 * fungsi : Halaman error 403 dengan konsep glass + ilustrasi + info box
 */
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/internal_guard.php';

$currentRole = auth_role() ?: 'guest';
$requiredRole = ROLE_ADMIN;
?>
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Akses Ditolak</title>
    <link rel="icon" type="image/svg+xml"
          href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='20' fill='%2314b8a6'/%3E%3Ctext x='50' y='68' font-size='56' text-anchor='middle' fill='white' font-family='sans-serif' font-weight='bold'%3ERM%3C/text%3E%3C/svg%3E">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: {
                fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'] },
                colors: { mint: {
                    50:'#f0fdf9',100:'#ccfbf1',200:'#99f6e4',300:'#5eead4',400:'#2dd4bf',
                    500:'#14b8a6',600:'#0d9488',700:'#0f766e',800:'#115e59',900:'#134e4a'
                } }
            } }
        }
    </script>

    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

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
    </style>
</head>
<body class="bg-mesh font-sans text-slate-800 antialiased min-h-screen flex flex-col justify-between selection:bg-mint-500 selection:text-white">

    <main class="flex-grow flex items-center justify-center p-4 sm:p-6 md:p-8">

        <div class="w-full max-w-lg">
            <div class="glass-card rounded-3xl p-8 sm:p-10 shadow-xl shadow-mint-900/5 text-center relative overflow-hidden">
                <div class="absolute top-0 left-0 right-0 h-1.5 bg-amber-400"></div>

                <!-- Illustration Icon -->
                <div class="relative inline-flex items-center justify-center w-24 h-24 rounded-3xl bg-amber-50 text-amber-500 mb-6 border border-amber-100 shadow-inner">
                    <i class="ri-lock-2-line text-5xl"></i>
                    <span class="absolute -bottom-2 -right-2 bg-amber-500 text-white text-[10px] font-extrabold px-2 py-0.5 rounded-full uppercase tracking-wider">403</span>
                </div>

                <h2 class="text-2xl sm:text-3xl font-bold text-slate-900 tracking-tight">Akses Ditolak</h2>
                <p class="text-slate-500 text-sm mt-2 leading-relaxed max-w-md mx-auto">
                    Maaf, akun Anda tidak memiliki hak akses yang cukup untuk membuka halaman atau memproses cadangan data Rekam Medis ini.
                </p>

                <!-- Detail Info Box -->
                <div class="mt-6 p-4 bg-slate-50 border border-slate-200/80 rounded-2xl text-left text-xs text-slate-600 space-y-1.5 font-mono">
                    <div class="flex justify-between">
                        <span class="text-slate-400">Error Code:</span>
                        <span class="font-semibold text-slate-700">HTTP 403 FORBIDDEN</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Your Role:</span>
                        <span class="font-semibold text-slate-700"><?= e(strtoupper($currentRole)) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Required Role:</span>
                        <span class="font-semibold text-amber-600"><?= e(strtoupper($requiredRole)) ?></span>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-3">
					<button type="button" onclick="doLogout('manual')"
							class="w-full sm:w-auto px-6 py-3 bg-slate-900 hover:bg-slate-800 text-white font-semibold rounded-xl text-sm transition-all flex items-center justify-center gap-2">
						<i class="ri-user-shared-line"></i>
						<span>Ganti Akun</span>
					</button>
                    <a href="<?= base_url('dashboard') ?>"
                       class="w-full sm:w-auto px-6 py-3 bg-mint-50 hover:bg-mint-100 text-mint-700 font-semibold rounded-xl text-sm border border-mint-200 transition-all flex items-center justify-center gap-2">
                        <i class="ri-arrow-left-line"></i>
                        <span>Kembali ke Utama</span>
                    </a>
                </div>
            </div>
        </div>

    </main>

    <footer class="py-4 text-center text-xs text-slate-400">
        &copy; <?= date('Y') ?> <?= e(APP_NAME) ?> • Security Standard Level 4
    </footer>
<script>
    window.BASE_URL = "<?= BASE_URL ?>";
    window.CSRF_TOKEN = "<?= csrf_token() ?>";
</script>
<script src="<?= asset('js/theme-helper.js') ?>"></script>
</body>
</html>