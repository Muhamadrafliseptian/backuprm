<?php
/**
 * file   : auth.php
 * path   : C:\xampp\htdocs\backuprm\core\auth.php
 * fungsi : Autentikasi login pakai tabel m_pegawai + lockout + failed attempt
 */
declare(strict_types=1);

const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_LOCK_MINUTES = 15;

function auth_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function auth_check(): bool
{
    return !empty($_SESSION['user']);
}

function auth_role(): ?string
{
    return $_SESSION['user']['role'] ?? null;
}

function auth_login(array $pegawai): void
{
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'      => (int) $pegawai['peg_id'],
        'nrk'     => $pegawai['peg_nrk'],
        'nama'    => $pegawai['peg_nama'] ?? $pegawai['peg_nrk'],
        'jabatan' => $pegawai['peg_jabatan'] ?? null,
        'tempat'  => $pegawai['peg_tempattugas'] ?? null,
        'role'    => $pegawai['peg_role'] ?? 'user',
    ];
    $_SESSION['_init']  = time();
    $_SESSION['_login'] = time();
}

function auth_logout(): void
{
    unset($_SESSION['user']);
    session_regenerate_id(true);
}

function auth_required(): void
{
    if (!auth_check()) {
        if (is_ajax()) json_response(['ok' => false, 'msg' => 'Unauthorized'], 401);
        redirect('login');
    }
}

function auth_admin(): void
{
    auth_required();
    if (auth_role() !== ROLE_ADMIN) {
        http_response_code(403);
        require BASE_PATH . '/pages/403.php';
        exit;
    }
}

function auth_find_by_nrk(string $nrk): ?array
{
    $stmt = db()->prepare("SELECT * FROM m_pegawai WHERE peg_nrk = :nrk LIMIT 1");
    $stmt->execute([':nrk' => $nrk]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function auth_is_locked(array $pegawai): bool
{
    if (empty($pegawai['locked_until'])) return false;
    return strtotime($pegawai['locked_until']) > time();
}

function auth_lock_remaining(array $pegawai): int
{
    if (empty($pegawai['locked_until'])) return 0;
    $remain = strtotime($pegawai['locked_until']) - time();
    return $remain > 0 ? $remain : 0;
}

function auth_register_failed(array $pegawai): void
{
    $attempts  = (int) $pegawai['failed_attempts'] + 1;
    $lockUntil = null;

    if ($attempts >= LOGIN_MAX_ATTEMPTS) {
        $lockUntil = date('Y-m-d H:i:s', time() + (LOGIN_LOCK_MINUTES * 60));
        $attempts  = LOGIN_MAX_ATTEMPTS;
    }

    $stmt = db()->prepare("
        UPDATE m_pegawai
           SET failed_attempts = :fa,
               locked_until    = :lu,
               updated_at      = NOW()
         WHERE peg_id = :id
    ");
    $stmt->execute([
        ':fa' => $attempts,
        ':lu' => $lockUntil,
        ':id' => $pegawai['peg_id'],
    ]);

    log_error(sprintf(
        'LOGIN FAIL nrk=%s ip=%s attempts=%d locked_until=%s',
        $pegawai['peg_nrk'],
        $_SERVER['REMOTE_ADDR'] ?? '-',
        $attempts,
        $lockUntil ?? '-'
    ));
}

function auth_reset_attempts(int $pegId): void
{
    $stmt = db()->prepare("
        UPDATE m_pegawai
           SET failed_attempts = 0,
               locked_until    = NULL,
               last_login      = NOW(),
               updated_at      = NOW()
         WHERE peg_id = :id
    ");
    $stmt->execute([':id' => $pegId]);
}

function auth_attempt(string $nrk, string $password): array
{
    $pegawai = auth_find_by_nrk($nrk);
    if (!$pegawai) {
        log_error(sprintf('LOGIN NOTFOUND nrk=%s ip=%s', $nrk, $_SERVER['REMOTE_ADDR'] ?? '-'));
        return ['ok' => false, 'reason' => 'not_found'];
    }

    if (($pegawai['peg_status'] ?? '') !== 'aktif') {
        log_error(sprintf('LOGIN INACTIVE nrk=%s ip=%s', $nrk, $_SERVER['REMOTE_ADDR'] ?? '-'));
        return ['ok' => false, 'reason' => 'inactive'];
    }

    if (auth_is_locked($pegawai)) {
        return [
            'ok'        => false,
            'reason'    => 'locked',
            'remaining' => auth_lock_remaining($pegawai),
        ];
    }

    if (!password_verify($password, $pegawai['peg_password'])) {
        auth_register_failed($pegawai);
        $sisa = LOGIN_MAX_ATTEMPTS - ((int)$pegawai['failed_attempts'] + 1);
        return [
            'ok'     => false,
            'reason' => 'wrong_password',
            'sisa'   => max(0, $sisa),
        ];
    }

    auth_reset_attempts((int) $pegawai['peg_id']);
    return ['ok' => true, 'user' => $pegawai];
}