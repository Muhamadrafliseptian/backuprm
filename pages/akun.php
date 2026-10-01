<?php
/**
 * file   : akun.php
 * path   : pages/akun.php
 * fungsi : Manajemen akun pengguna (admin only)
 *          - daftar semua akun dari m_pegawai
 *          - tambah akun baru
 *          - reset password
 *          - ubah status aktif / kunci
 *          - ubah role (admin / user)
 *
 * Akses: hanya admin yang sudah login.
 */
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/internal_guard.php';

// Guard: hanya admin
if (!auth_check() || auth_role() !== ROLE_ADMIN) {
    http_response_code(403);
    require BASE_PATH . '/pages/403.php';
    exit;
}

$__flash = flash('success') ?? flash('error');
$__errors = [];

/* ============================================================
   1. Tambah akun baru
   ============================================================ */
if (is_post() && ($_POST['aksi'] ?? '') === 'tambah') {
    csrf_check_or_die();

    $nrk      = trim((string)($_POST['nrk'] ?? ''));
    $nama     = trim((string)($_POST['nama'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $role     = (string)($_POST['role'] ?? 'user');
    $tempat   = trim((string)($_POST['tempat'] ?? '')) ?: null;
    $jabatan  = trim((string)($_POST['jabatan'] ?? '')) ?: null;

    if ($nrk === '')                    $__errors[] = 'NRK wajib diisi.';
    if ($nama === '')                   $__errors[] = 'Nama wajib diisi.';
    if (mb_strlen($password) < 8)        $__errors[] = 'Password minimal 8 karakter.';
    if (!in_array($role, [ROLE_ADMIN, ROLE_USER], true))
                                        $__errors[] = 'Role tidak valid.';
    if ($nrk !== '' && !ctype_digit($nrk))
                                        $__errors[] = 'NRK hanya boleh angka.';

    if (!$__errors) {
        // Cek NRK duplikat
        $cek = db()->prepare("SELECT peg_id FROM m_pegawai WHERE peg_nrk = :nrk LIMIT 1");
        $cek->execute([':nrk' => $nrk]);
        if ($cek->fetch()) {
            $__errors[] = 'NRK ' . $nrk . ' sudah terdaftar.';
        }
    }

    if (!$__errors) {
        try {
            db()->prepare("
                INSERT INTO m_pegawai
                    (peg_nrk, peg_password, peg_nama, peg_tempattugas,
                     peg_jabatan, peg_status, peg_role)
                VALUES
                    (:nrk, :pass, :nama, :tempat,
                     :jabatan, 'aktif', :role)
            ")->execute([
                ':nrk'     => $nrk,
                ':pass'    => password_hash($password, PASSWORD_DEFAULT),
                ':nama'    => $nama,
                ':tempat'  => $tempat,
                ':jabatan' => $jabatan,
                ':role'    => $role,
            ]);
            log_activity('akun', 'Tambah akun: ' . $nrk . ' (' . $role . ')');
            flash('success', 'Akun ' . $nrk . ' (' . $nama . ') berhasil ditambahkan.');
            redirect('akun');
        } catch (Throwable $e) {
            $__errors[] = 'Gagal menyimpan: ' . $e->getMessage();
            log_error('TAMBAH AKUN GAGAL: ' . $e->getMessage());
        }
    } else {
        flash('error', implode(' ', $__errors));
    }
}

/* ============================================================
   2. Reset password
   ============================================================ */
if (is_post() && ($_POST['aksi'] ?? '') === 'reset-password') {
    csrf_check_or_die();

    $pegId    = (int)($_POST['peg_id'] ?? 0);
    $password = (string)($_POST['password_baru'] ?? '');

    if (mb_strlen($password) < 8) {
        flash('error', 'Password baru minimal 8 karakter.');
    } else {
        try {
            db()->prepare("
                UPDATE m_pegawai
                   SET peg_password    = :pass,
                       failed_attempts = 0,
                       locked_until    = NULL,
                       updated_at      = NOW()
                 WHERE peg_id = :id
            ")->execute([
                ':pass' => password_hash($password, PASSWORD_DEFAULT),
                ':id'   => $pegId,
            ]);
            log_activity('akun', 'Reset password akun id=' . $pegId);
            flash('success', 'Password berhasil direset. Aksa kunci ikut dibuka.');
            redirect('akun');
        } catch (Throwable $e) {
            flash('error', 'Gagal reset password: ' . $e->getMessage());
        }
    }
}

/* ============================================================
   3. Ubah status (aktif / nonaktif)
   ============================================================ */
if (is_post() && ($_POST['aksi'] ?? '') === 'toggle-status') {
    csrf_check_or_die();

    $pegId = (int)($_POST['peg_id'] ?? 0);
    $new   = ($_POST['status'] ?? '') === 'aktif' ? 'aktif' : 'nonaktif';

    // Jangan biarkan admin terakhir mematikan dirinya sendiri
    if ($pegId === (int)($_SESSION['user']['id'] ?? 0) && $new === 'nonaktif') {
        flash('error', 'Tidak bisa menonaktifkan akun Anda sendiri.');
    } else {
        db()->prepare("
            UPDATE m_pegawai SET peg_status = :st, updated_at = NOW()
             WHERE peg_id = :id
        ")->execute([':st' => $new, ':id' => $pegId]);
        log_activity('akun', 'Ubah status akun id=' . $pegId . ' -> ' . $new);
        flash('success', 'Status akun diubah menjadi ' . $new . '.');
    }
    redirect('akun');
}

/* ============================================================
   4. Ubah role
   ============================================================ */
if (is_post() && ($_POST['aksi'] ?? '') === 'toggle-role') {
    csrf_check_or_die();

    $pegId = (int)($_POST['peg_id'] ?? 0);
    $role  = (string)($_POST['role'] ?? ROLE_USER);
    if (!in_array($role, [ROLE_ADMIN, ROLE_USER], true)) $role = ROLE_USER;

    // Jangan biarkan admin terakhir menurunkan sendiri aksesnya
    if ($pegId === (int)($_SESSION['user']['id'] ?? 0) && $role !== ROLE_ADMIN) {
        flash('error', 'Tidak bisa menurunkan role akun Anda sendiri.');
    } else {
        db()->prepare("
            UPDATE m_pegawai SET peg_role = :r, updated_at = NOW()
             WHERE peg_id = :id
        ")->execute([':r' => $role, ':id' => $pegId]);
        log_activity('akun', 'Ubah role akun id=' . $pegId . ' -> ' . $role);
        flash('success', 'Role akun diubah menjadi ' . $role . '.');
    }
    redirect('akun');
}

/* ============================================================
   5. Buka kunci (failed attempts)
   ============================================================ */
if (is_post() && ($_POST['aksi'] ?? '') === 'buka-kunci') {
    csrf_check_or_die();

    $pegId = (int)($_POST['peg_id'] ?? 0);
    db()->prepare("
        UPDATE m_pegawai
           SET failed_attempts = 0, locked_until = NULL, updated_at = NOW()
         WHERE peg_id = :id
    ")->execute([':id' => $pegId]);
    log_activity('akun', 'Buka kunci akun id=' . $pegId);
    flash('success', 'Kunci akun dibuka.');
    redirect('akun');
}

/* ============================================================
   Data untuk ditampilkan
   ============================================================ */
$__daftar = db()->query("
    SELECT peg_id, peg_nrk, peg_nama, peg_tempattugas, peg_jabatan,
           peg_status, peg_role, failed_attempts, locked_until, last_login
      FROM m_pegawai
     ORDER BY (peg_role = 'admin') DESC, peg_nrk ASC
")->fetchAll();

$__sayaId = (int)($_SESSION['user']['id'] ?? 0);

require BASE_PATH . '/layouts/header.php';
require BASE_PATH . '/layouts/sidebar.php';
?>

<main class="flex-1 p-4 lg:p-6 overflow-y-auto">
    <div class="max-w-6xl mx-auto space-y-5">

        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-800">Manajemen Akun</h1>
                <p class="text-xs text-slate-400 mt-0.5">
                    Akun taken dari tabel <code class="text-mint-600">m_pegawai</code>.
                    Password disimpan sebagai hash bcrypt, tidak pernah ditampilkan.
                </p>
            </div>
            <a href="<?= base_url('dashboard') ?>" class="text-xs font-semibold text-slate-500 hover:text-slate-700">
                <i class="ri-arrow-left-line"></i> Dashboard
            </a>
        </div>

        <!-- Flash -->
        <?php if ($__flash): ?>
            <div class="rounded-xl px-4 py-3 text-sm font-medium border
                <?= str_contains($__flash, 'berhasil') || str_contains($__flash, 'diubah')
                    || str_contains($__flash, 'direset') || str_contains($__flash, 'ditambahkan')
                    || str_contains($__flash, 'dibuka')
                    ? 'bg-emerald-50 border-emerald-200 text-emerald-800'
                    : 'bg-rose-50 border-rose-200 text-rose-800' ?>">
                <?= e($__flash) ?>
            </div>
        <?php endif; ?>

        <!-- Tambah akun -->
        <section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <header class="px-5 py-3.5 border-b border-slate-100 flex items-center gap-2">
                <i class="ri-user-add-line text-mint-600 text-lg"></i>
                <h2 class="text-sm font-bold text-slate-800">Tambah Akun Baru</h2>
            </header>
            <form method="post" class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="tambah">

                <label class="block">
                    <span class="text-xs font-semibold text-slate-600">NRK</span>
                    <input type="text" name="nrk" required inputmode="numeric" pattern="[0-9]*" maxlength="30"
                           placeholder="Contoh: 8813084"
                           class="mt-1.5 w-full px-3 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:border-mint-500 focus:ring-4 focus:ring-mint-500/10">
                </label>

                <label class="block">
                    <span class="text-xs font-semibold text-slate-600">Nama Lengkap</span>
                    <input type="text" name="nama" required maxlength="150"
                           placeholder="Contoh: Novida Iskandar, S.Kom"
                           class="mt-1.5 w-full px-3 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:border-mint-500 focus:ring-4 focus:ring-mint-500/10">
                </label>

                <label class="block">
                    <span class="text-xs font-semibold text-slate-600">Password</span>
                    <input type="password" name="password" required minlength="8"
                           placeholder="Minimal 8 karakter"
                           class="mt-1.5 w-full px-3 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:border-mint-500 focus:ring-4 focus:ring-mint-500/10">
                </label>

                <label class="block">
                    <span class="text-xs font-semibold text-slate-600">Role</span>
                    <select name="role"
                            class="mt-1.5 w-full px-3 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:border-mint-500 focus:ring-4 focus:ring-mint-500/10">
                        <option value="<?= e(ROLE_USER) ?>">User</option>
                        <option value="<?= e(ROLE_ADMIN) ?>">Admin</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-xs font-semibold text-slate-600">Tempat Tugas</span>
                    <input type="text" name="tempat" maxlength="150" placeholder="Opsional"
                           class="mt-1.5 w-full px-3 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:border-mint-500 focus:ring-4 focus:ring-mint-500/10">
                </label>

                <label class="block">
                    <span class="text-xs font-semibold text-slate-600">Jabatan</span>
                    <input type="text" name="jabatan" maxlength="100" placeholder="Opsional"
                           class="mt-1.5 w-full px-3 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:border-mint-500 focus:ring-4 focus:ring-mint-500/10">
                </label>

                <div class="md:col-span-2">
                    <button type="submit"
                            class="px-5 py-2.5 bg-mint-500 hover:bg-mint-600 text-white text-xs font-bold rounded-xl transition-all shadow-sm">
                        <i class="ri-add-line"></i> Simpan Akun
                    </button>
                </div>
            </form>
        </section>

        <!-- Daftar akun -->
        <section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <header class="px-5 py-3.5 border-b border-slate-100 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="ri-team-line text-mint-600 text-lg"></i>
                    <h2 class="text-sm font-bold text-slate-800">Daftar Akun</h2>
                </div>
                <span class="text-xs text-slate-400"><?= count($__daftar) ?> akun</span>
            </header>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50 text-left text-[11px] uppercase tracking-wide text-slate-500">
                            <th class="px-4 py-3 font-semibold">NRK</th>
                            <th class="px-4 py-3 font-semibold">Nama</th>
                            <th class="px-4 py-3 font-semibold">Role</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                            <th class="px-4 py-3 font-semibold">Percobaan Gagal</th>
                            <th class="px-4 py-3 font-semibold text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($__daftar as $__a):
                            $__locked = !empty($__a['locked_until'])
                                && strtotime($__a['locked_until']) > time();
                            $__saya  = ((int)$__a['peg_id'] === $__sayaId);
                        ?>
                            <tr class="hover:bg-slate-50/60">
                                <td class="px-4 py-3 font-mono text-xs font-bold text-slate-700">
                                    <?= e($__a['peg_nrk']) ?>
                                    <?php if ($__saya): ?>
                                        <span class="ml-1 text-[10px] font-bold text-mint-600">(Anda)</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-slate-800 text-xs"><?= e($__a['peg_nama']) ?></div>
                                    <?php if (!empty($__a['peg_jabatan'])): ?>
                                        <div class="text-[11px] text-slate-400"><?= e($__a['peg_jabatan']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="text-[10px] font-bold uppercase px-2 py-1 rounded-lg
                                        <?= $__a['peg_role'] === ROLE_ADMIN
                                            ? 'bg-violet-50 text-violet-700' : 'bg-slate-100 text-slate-600' ?>">
                                        <?= e($__a['peg_role']) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <?php if ($__locked): ?>
                                        <span class="text-[10px] font-bold px-2 py-1 rounded-lg bg-rose-50 text-rose-700">
                                            TERKUNCI
                                        </span>
                                    <?php else: ?>
                                        <span class="text-[10px] font-bold px-2 py-1 rounded-lg
                                            <?= $__a['peg_status'] === 'aktif'
                                                ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' ?>">
                                            <?= e(strtoupper($__a['peg_status'])) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-xs font-mono <?= (int)$__a['failed_attempts'] > 0 ? 'text-rose-600 font-bold' : 'text-slate-400' ?>">
                                    <?= (int)$__a['failed_attempts'] ?><?= $__locked ? ' / kunci' : '' ?>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1.5 justify-end">

                                        <!-- Ubah status -->
                                        <form method="post" class="inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="aksi" value="toggle-status">
                                            <input type="hidden" name="peg_id" value="<?= (int)$__a['peg_id'] ?>">
                                            <input type="hidden" name="status" value="<?= $__a['peg_status'] === 'aktif' ? 'nonaktif' : 'aktif' ?>">
                                            <button type="submit" <?= $__saya ? 'disabled' : '' ?>
                                                    title="<?= $__a['peg_status'] === 'aktif' ? 'Nonaktifkan' : 'Aktifkan' ?>"
                                                    class="px-2.5 py-1.5 text-[10px] font-bold rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed">
                                                <?= $__a['peg_status'] === 'aktif' ? 'Nonaktif' : 'Aktif' ?>
                                            </button>
                                        </form>

                                        <!-- Buka kunci -->
                                        <?php if ($__locked || (int)$__a['failed_attempts'] > 0): ?>
                                            <form method="post" class="inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="aksi" value="buka-kunci">
                                                <input type="hidden" name="peg_id" value="<?= (int)$__a['peg_id'] ?>">
                                                <button type="submit" class="px-2.5 py-1.5 text-[10px] font-bold rounded-lg bg-amber-50 border border-amber-200 text-amber-700 hover:bg-amber-100">
                                                    <i class="ri-lock-unlock-line"></i> Buka
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <!-- Ubah role -->
                                        <?php if (!$__saya): ?>
                                            <form method="post" class="inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="aksi" value="toggle-role">
                                                <input type="hidden" name="peg_id" value="<?= (int)$__a['peg_id'] ?>">
                                                <input type="hidden" name="role" value="<?= $__a['peg_role'] === ROLE_ADMIN ? ROLE_USER : ROLE_ADMIN ?>">
                                                <button type="submit" title="Ubah role"
                                                        class="px-2.5 py-1.5 text-[10px] font-bold rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50">
                                                    <?= $__a['peg_role'] === ROLE_ADMIN ? 'Jadikan User' : 'Jadikan Admin' ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <!-- Reset password -->
                                        <button type="button"
                                                onclick="resetPw(<?= (int)$__a['peg_id'] ?>, '<?= e($__a['peg_nrk']) ?>')"
                                                class="px-2.5 py-1.5 text-[10px] font-bold rounded-lg bg-mint-50 border border-mint-200 text-mint-700 hover:bg-mint-100">
                                            <i class="ri-key-line"></i> Password
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($__daftar)): ?>
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-xs text-slate-400">
                                    Belum ada akun di database.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</main>

<!-- Modal reset password -->
<div id="resetPwModal" class="hidden fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm p-4">
    <div class="max-w-md mx-auto mt-16 bg-white rounded-2xl shadow-xl overflow-hidden">
        <header class="px-5 py-3.5 border-b border-slate-100 flex items-center gap-2">
            <i class="ri-key-line text-mint-600 text-lg"></i>
            <h3 class="text-sm font-bold text-slate-800">Reset Password</h3>
        </header>
        <form method="post" class="p-5 space-y-3">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="reset-password">
            <input type="hidden" name="peg_id" id="rpPegId">

            <p class="text-xs text-slate-500">
                Password baru untuk akun <span id="rpNrk" class="font-bold text-slate-700"></span>.
                Minimal 8 karakter. Kunci akun akan ikut dibuka.
            </p>

            <input type="password" name="password_baru" id="rpPass" required minlength="8"
                   placeholder="Password baru"
                   class="w-full px-3 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:border-mint-500 focus:ring-4 focus:ring-mint-500/10">

            <div class="flex gap-2 pt-1">
                <button type="submit"
                        class="flex-1 px-4 py-2.5 bg-mint-500 hover:bg-mint-600 text-white text-xs font-bold rounded-xl transition-all">
                    <i class="ri-save-line"></i> Simpan Password
                </button>
                <button type="button" onclick="closeResetPw()"
                        class="px-4 py-2.5 border border-slate-200 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-50">
                    Batal
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function resetPw(pegId, nrk) {
        document.getElementById('rpPegId').value = pegId;
        document.getElementById('rpNrk').textContent = nrk;
        document.getElementById('rpPass').value = '';
        document.getElementById('resetPwModal').classList.remove('hidden');
        document.getElementById('rpPass').focus();
    }

    function closeResetPw() {
        document.getElementById('resetPwModal').classList.add('hidden');
    }

    document.getElementById('resetPwModal').addEventListener('click', function (e) {
        if (e.target === this) closeResetPw();
    });
</script>

<?php require BASE_PATH . '/layouts/footer.php'; ?>