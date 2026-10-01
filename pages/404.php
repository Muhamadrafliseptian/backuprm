<?php
/**
 * file   : 404.php
 * path   : C:\xampp\htdocs\backuprm\pages\404.php
 * fungsi : Halaman error 404 dengan konsep glass + big number + shortcut links
 */
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Halaman Tidak Ditemukan</title>
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
                <div class="absolute top-0 left-0 right-0 h-1.5 bg-slate-300"></div>

                <!-- Big 404 Number Graphic -->
                <div class="relative mb-6">
                    <span class="text-8xl font-black text-slate-100 select-none tracking-widest block">404</span>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <div class="w-16 h-16 rounded-2xl bg-mint-50 border border-mint-200 text-mint-600 flex items-center justify-center shadow-md">
                            <i class="ri-file-search-line text-3xl"></i>
                        </div>
                    </div>
                </div>

                <h2 class="text-2xl sm:text-3xl font-bold text-slate-900 tracking-tight">Halaman Tidak Ditemukan</h2>
                <p class="text-slate-500 text-sm mt-2 leading-relaxed max-w-md mx-auto">
                    Halaman atau file arsip backup Rekam Medis yang Anda cari mungkin telah dipindahkan, dihapus, atau tautan tidak valid.
                </p>

                <!-- Helpful Navigation Links -->
                <div class="mt-6 pt-6 border-t border-slate-100">
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-3">Tautan Pintas</p>
                    <div class="grid grid-cols-2 gap-2 text-left">
                        <a href="<?= base_url('dashboard') ?>"
                           class="p-3 bg-slate-50 hover:bg-mint-50/50 hover:border-mint-200 border border-slate-200/60 rounded-xl transition-all group">
                            <div class="text-xs font-semibold text-slate-700 group-hover:text-mint-700 flex items-center justify-between">
                                Dashboard
                                <i class="ri-arrow-right-s-line text-slate-400 group-hover:text-mint-600"></i>
                            </div>
                            <span class="text-[10px] text-slate-400">Ringkasan status backup</span>
                        </a>
                        <a href="<?= base_url('log') ?>"
                           class="p-3 bg-slate-50 hover:bg-mint-50/50 hover:border-mint-200 border border-slate-200/60 rounded-xl transition-all group">
                            <div class="text-xs font-semibold text-slate-700 group-hover:text-mint-700 flex items-center justify-between">
                                Log Riwayat
                                <i class="ri-arrow-right-s-line text-slate-400 group-hover:text-mint-600"></i>
                            </div>
                            <span class="text-[10px] text-slate-400">Daftar arsip tersimpan</span>
                        </a>
                    </div>
                </div>

                <!-- Action Button -->
                <div class="mt-8">
                    <a href="<?= base_url('dashboard') ?>"
                       class="w-full py-3.5 px-6 bg-mint-500 hover:bg-mint-600 text-white font-semibold rounded-xl text-sm shadow-lg shadow-mint-500/20 transition-all flex items-center justify-center gap-2">
                        <i class="ri-home-4-line"></i>
                        <span>Kembali ke Halaman Utama</span>
                    </a>
                </div>
            </div>
        </div>

    </main>

    <footer class="py-4 text-center text-xs text-slate-400">
        &copy; <?= date('Y') ?> <?= e(APP_NAME) ?> • Security Standard Level 4
    </footer>

</body>
</html>