<?php
/**
 * file   : sidebar.php
 * path   : C:\xampp\htdocs\backuprm\layouts\sidebar.php
 * fungsi : Sidebar putih + brand gradient + nav + user footer
 *          Struktur: Utama → Alur Kerja → Data → Sistem
 */
declare(strict_types=1);
$__u = auth_user();
$__page = $_SERVER['ROUTE_PATH'] ?? 'dashboard';

/* Helper: cek menu aktif */
if (!function_exists('nav_active')) {
    function nav_active(string $route, string $current): string {
        $isActive = $route === $current;
        if ($isActive) {
            return 'flex items-center gap-3 px-3.5 py-2.5 text-sm font-semibold rounded-xl bg-mint-50 text-mint-700 border border-mint-100/60 transition-all';
        }
        return 'flex items-center gap-3 px-3.5 py-2.5 text-sm font-medium rounded-xl text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-colors';
    }
}
if (!function_exists('nav_icon')) {
    function nav_icon(string $route, string $current): string {
        return $route === $current
            ? 'text-lg text-mint-600'
            : 'text-lg text-slate-400';
    }
}
/* Helper: cek aktif untuk multi-route */
if (!function_exists('nav_active_multi')) {
    function nav_active_multi(array $routes, string $current): string {
        $isActive = in_array($current, $routes, true);
        if ($isActive) {
            return 'flex items-center gap-3 px-3.5 py-2.5 text-sm font-semibold rounded-xl bg-mint-50 text-mint-700 border border-mint-100/60 transition-all';
        }
        return 'flex items-center gap-3 px-3.5 py-2.5 text-sm font-medium rounded-xl text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-colors';
    }
}
if (!function_exists('nav_icon_multi')) {
    function nav_icon_multi(array $routes, string $current): string {
        return in_array($current, $routes, true)
            ? 'text-lg text-mint-600'
            : 'text-lg text-slate-400';
    }
}
?>

<!-- Sidebar -->
<aside id="sidebar" class="fixed md:static inset-y-0 left-0 w-64 bg-white border-r border-slate-100 z-50 transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out flex flex-col justify-between shrink-0">
    <div class="flex-1 overflow-y-auto">
        <!-- Brand Logo -->
        <div class="h-16 flex items-center justify-between px-6 border-b border-slate-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-mint-400 to-mint-600 flex items-center justify-center text-white shadow-md shadow-mint-500/20">
                    <i class="ri-hospital-line text-xl"></i>
                </div>
                <div>
                    <h1 class="font-bold text-slate-900 text-sm leading-tight tracking-tight"><?= e(APP_NAME) ?></h1>
                    <p class="text-[11px] text-mint-600 font-medium">Puskesmas Setiabudi</p>
                </div>
            </div>
            <button onclick="toggleSidebar()" class="md:hidden text-slate-400 hover:text-slate-600">
                <i class="ri-close-line text-xl"></i>
            </button>
        </div>

        <!-- Server Status Widget -->
        <div class="px-4 mt-5">
            <div class="bg-mint-50/80 rounded-2xl p-3.5 border border-mint-100/80 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <span class="relative flex h-2.5 w-2.5">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-mint-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-mint-500"></span>
                    </span>
                    <div>
                        <p class="text-[11px] font-bold text-slate-800">Server Online</p>
                        <p class="text-[10px] text-mint-700 font-medium">Database Terhubung</p>
                    </div>
                </div>
                <i class="ri-shield-check-line text-mint-600 text-lg"></i>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="px-3 mt-6 space-y-1">

            <!-- ============================================================
                 1. UTAMA
                 ============================================================ -->
            <div class="px-3.5 pb-2 text-[10.5px] uppercase tracking-wider text-slate-400 font-bold">
                Utama
            </div>
            <a href="<?= base_url('dashboard') ?>" class="<?= nav_active('dashboard', $__page) ?>">
                <i class="ri-dashboard-3-line <?= nav_icon('dashboard', $__page) ?>"></i>
                <span>Dashboard</span>
            </a>

            <!-- ============================================================
                 2. ALUR KERJA (Upload → Preview → Verifikasi)
                 ============================================================ -->
            <div class="px-3.5 pt-4 pb-2 text-[10.5px] uppercase tracking-wider text-slate-400 font-bold">
                Alur Kerja
            </div>
			<a href="<?= base_url('upload') ?>" class="<?= nav_active_multi(['upload', 'generate'], $__page) ?>">
				<i class="ri-cloud-line <?= nav_icon_multi(['upload', 'generate'], $__page) ?>"></i>
				<span>Upload &amp; Proses</span>
			</a>
            <a href="<?= base_url('verifikasi') ?>" class="<?= nav_active('verifikasi', $__page) ?>">
                <i class="ri-shield-check-line <?= nav_icon('verifikasi', $__page) ?>"></i>
                <span>Verifikasi</span>
            </a>

            <!-- ============================================================
                 3. DATA (hasil simpan)
                 ============================================================ -->
            <div class="px-3.5 pt-4 pb-2 text-[10.5px] uppercase tracking-wider text-slate-400 font-bold">
                Data
            </div>
            <a href="<?= base_url('pasien') ?>" class="<?= nav_active('pasien', $__page) ?>">
                <i class="ri-group-line <?= nav_icon('pasien', $__page) ?>"></i>
                <span>Pasien</span>
            </a>
            <a href="<?= base_url('kunjungan') ?>" class="<?= nav_active('kunjungan', $__page) ?>">
                <i class="ri-calendar-check-line <?= nav_icon('kunjungan', $__page) ?>"></i>
                <span>Kunjungan</span>
            </a>
            <a href="<?= base_url('laboratorium') ?>" class="<?= nav_active('laboratorium', $__page) ?>">
                <i class="ri-flask-line <?= nav_icon('laboratorium', $__page) ?>"></i>
                <span>Laboratorium</span>
            </a>
            <a href="<?= base_url('obat') ?>" class="<?= nav_active('obat', $__page) ?>">
                <i class="ri-capsule-line <?= nav_icon('obat', $__page) ?>"></i>
                <span>Obat</span>
            </a>
            <a href="<?= base_url('tenaga-kesehatan') ?>" class="<?= nav_active('tenaga-kesehatan', $__page) ?>">
                <i class="ri-user-heart-line <?= nav_icon('tenaga-kesehatan', $__page) ?>"></i>
                <span>Tenaga Kesehatan</span>
            </a>

            <!-- ============================================================
                 4. SISTEM
                 ============================================================ -->
            <div class="px-3.5 pt-4 pb-2 text-[10.5px] uppercase tracking-wider text-slate-400 font-bold">
                Sistem
            </div>
				<a href="<?= base_url('log') ?>" class="<?= nav_active('log', $__page) ?>">
					<i class="ri-file-list-line <?= nav_icon('log', $__page) ?>"></i>
					<span>Log Aktivitas</span>
				</a>
				<a href="<?= base_url('backup-all') ?>" class="<?= nav_active('backup-all', $__page) ?>">
					<i class="ri-download-cloud-2-line <?= nav_icon('backup-all', $__page) ?>"></i>
					<span>Backup All Record DB</span>
				</a>
				<a href="<?= base_url('about') ?>" class="<?= nav_active('about', $__page) ?>">
					<i class="ri-information-line <?= nav_icon('about', $__page) ?>"></i>
					<span>Tentang</span>
				</a>
        </nav>
    </div>

    <!-- User Footer -->
    <div class="p-4 border-t border-slate-100 bg-white shrink-0">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-9 h-9 rounded-full bg-gradient-to-br from-mint-400 to-mint-600 flex items-center justify-center text-white font-bold text-xs border-2 border-mint-200">
                    <?= e(strtoupper(substr($__u['nama'] ?? 'U', 0, 1))) ?>
                </div>
                <div class="overflow-hidden">
                    <p class="text-xs font-bold text-slate-800 truncate"><?= e($__u['nama'] ?? 'User') ?></p>
                    <p class="text-[10px] text-slate-400 truncate">NRK: <?= e($__u['nrk'] ?? '-') ?></p>
                </div>
            </div>
            <button onclick="confirmLogout(event)" class="text-slate-400 hover:text-rose-500 transition-colors p-1" title="Keluar">
                <i class="ri-logout-box-r-line text-lg"></i>
            </button>
        </div>
    </div>
</aside>

<!-- Main Area -->
<div class="flex-1 flex flex-col h-full overflow-hidden bg-white">

    <!-- Topbar -->
    <header class="h-16 border-b border-slate-100 bg-white/90 backdrop-blur-md px-4 md:px-8 flex items-center justify-between shrink-0 z-30">
        <div class="flex items-center gap-4 flex-1 max-w-lg">
            <button onclick="toggleSidebar()" class="md:hidden p-2 rounded-xl text-slate-500 hover:bg-slate-100">
                <i class="ri-menu-line text-xl"></i>
            </button>
            <div class="relative w-full">
                <i class="ri-search-2-line absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                <input type="text" id="globalSearch" placeholder="Cari..." class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200/80 rounded-xl text-xs sm:text-sm text-slate-800 focus:bg-white focus:border-mint-500 focus:ring-2 focus:ring-mint-100 focus:outline-none transition-all placeholder:text-slate-400">
            </div>
        </div>

        <div class="flex items-center gap-3 md:gap-5">
            <!-- Page Title -->
            <div class="hidden lg:block text-right border-r border-slate-100 pr-5">
                <p class="text-[11px] text-slate-400 font-medium"><?= e(date('d M Y')) ?></p>
                <p class="text-xs font-bold text-slate-800"><?= e(ucfirst(str_replace('-', ' ', $__page))) ?></p>
            </div>

            <!-- Notification Bell -->
            <button class="relative p-2 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-50 transition-colors">
                <i class="ri-notification-3-line text-xl"></i>
                <span class="absolute top-2 right-2 w-2 h-2 bg-mint-500 rounded-full ring-2 ring-white"></span>
            </button>

            <!-- User Dropdown -->
            <div class="relative" id="userDropdown">
                <button onclick="toggleUserMenu()" class="flex items-center gap-2 px-3 py-1.5 rounded-xl border border-slate-200 hover:bg-slate-50 transition-colors text-slate-700">
                    <i class="ri-user-circle-line text-lg text-mint-600"></i>
                    <span class="hidden sm:inline text-xs font-semibold"><?= e($__u['nama'] ?? 'User') ?></span>
                    <i class="ri-arrow-down-s-line text-sm text-slate-400"></i>
                </button>
                <div id="userMenu" class="hidden absolute right-0 mt-2 w-56 bg-white rounded-xl shadow-lg border border-slate-100 py-2 z-50">
                    <div class="px-4 py-2 border-b border-slate-100">
                        <p class="text-xs font-bold text-slate-800 truncate"><?= e($__u['nama'] ?? 'User') ?></p>
                        <p class="text-[11px] text-slate-400 truncate">NRK: <?= e($__u['nrk'] ?? '-') ?></p>
                    </div>
                    <a href="<?= base_url('about') ?>" class="flex items-center gap-2 px-4 py-2 text-xs text-slate-600 hover:bg-slate-50">
                        <i class="ri-information-line"></i> Tentang Aplikasi
                    </a>
                    <hr class="my-1 border-slate-100">
                    <button onclick="confirmLogout(event)" class="w-full flex items-center gap-2 px-4 py-2 text-xs text-rose-600 hover:bg-rose-50">
                        <i class="ri-logout-box-r-line"></i> Logout
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 overflow-y-auto p-4 md:p-8 space-y-6 bg-slate-50/50">