<?php
/**
 * file   : header.php
 * path   : C:\xampp\htdocs\backuprm\layouts\header.php
 * fungsi : Head + Tailwind + Remixicon + Chart.js
 */
declare(strict_types=1);
$__user = auth_user();
$__page = $_SERVER['ROUTE_PATH'] ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(ucfirst(str_replace('-', ' ', $__page))) ?> - <?= e(APP_NAME) ?></title>

    <!-- ============================================================
         FAVICON (SVG data-URI, tidak butuh file .ico)
         ============================================================ -->
    <link rel="icon" type="image/svg+xml"
          href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='20' fill='%2314b8a6'/%3E%3Ctext x='50' y='68' font-size='56' text-anchor='middle' fill='white' font-family='sans-serif' font-weight='bold'%3ERM%3C/text%3E%3C/svg%3E">
    <link rel="apple-touch-icon"
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

    <!-- Chart.js (untuk dashboard) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Remixicon -->
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">

    <!-- Inter Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- DataTables (Tailwind theme) -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.tailwindcss.min.css">

    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <!-- NProgress -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/nprogress/0.2.0/nprogress.min.css">

    <!-- Select2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">

    <!-- Custom Style -->
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
</head>
<body class="h-full text-slate-800 font-sans antialiased bg-slate-50/60 selection:bg-mint-500 selection:text-white">
<div class="flex h-screen overflow-hidden">

    <!-- Mobile Sidebar Backdrop -->
    <div id="mobileBackdrop" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/30 z-40 hidden md:hidden backdrop-blur-sm transition-opacity"></div>