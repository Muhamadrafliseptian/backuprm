<?php
/**
 * file   : proses_logout.php
 * path   : C:\xampp\htdocs\backuprm\actions\proses_logout.php
 * fungsi : Logout user (manual / auto-timeout) — WAJIB POST + CSRF
 */
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/internal_guard.php';

/* Hanya terima POST */
if (!is_post()) {
    if (is_ajax()) {
        json_response(['ok' => false, 'msg' => 'Method not allowed'], 405);
    }
    redirect('dashboard');
}

csrf_check_or_die();

$u = auth_user();
$reason = (string)($_POST['reason'] ?? 'manual');

if ($u) {
    log_activity('logout', 'User logout (' . $reason . '): ' . ($u['nrk'] ?? '-'));
}

auth_logout();

if ($reason === 'timeout') {
    flash('error', 'Sesi berakhir karena tidak ada aktivitas.');
} else {
    flash('success', 'Anda telah keluar dari sistem.');
}

redirect('login');