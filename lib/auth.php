<?php
declare(strict_types=1);

function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = empty($_SESSION['uid']) ? null : row('SELECT * FROM users WHERE id = ?', [$_SESSION['uid']]);
    }
    return $user;
}

function is_superadmin(): bool
{
    $u = current_user();
    return $u !== null && $u['role'] === 'superadmin';
}

/** Kedai yang sedang diurus: milik pemilik, atau kedai yang admin "masuk sebagai". */
function current_shop(): ?array
{
    static $shop = false;
    if ($shop === false) {
        $u = current_user();
        $id = null;
        if ($u && $u['role'] === 'owner') {
            $id = $u['shop_id'];
        } elseif ($u && $u['role'] === 'superadmin' && !empty($_SESSION['as_shop'])) {
            $id = $_SESSION['as_shop'];
        }
        $shop = $id ? row('SELECT * FROM shops WHERE id = ?', [$id]) : null;
    }
    return $shop;
}

function require_owner(): array
{
    $u = current_user();
    if (!$u) {
        redirect(base() . 'masuk.php?next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? ''));
    }
    $shop = current_shop();
    if (!$shop) {
        redirect(base() . ($u['role'] === 'superadmin' ? 'admin/' : 'masuk.php'));
    }
    if ($u['must_change'] && basename($_SERVER['SCRIPT_NAME']) !== 'akaun.php' && empty($_SESSION['as_shop'])) {
        flash('Sila tukar kata laluan sementara anda dahulu.', 'warn');
        redirect('akaun.php');
    }
    if ($shop['status'] === 'digantung' && !is_superadmin()) {
        http_response_code(403);
        include ROOT . '/includes/digantung.php';
        exit;
    }
    return $shop;
}

function require_superadmin(): array
{
    $u = current_user();
    if (!$u) {
        redirect(base() . 'masuk.php?next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? ''));
    }
    if ($u['role'] !== 'superadmin') {
        http_response_code(403);
        exit('Akses ditolak.');
    }
    return $u;
}

function login_blocked(string $email): bool
{
    $since = date('Y-m-d H:i:s', time() - 15 * 60);
    $byEmail = (int)val('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND created_at > ?', [$email, $since]);
    $byIp = (int)val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND created_at > ?', [client_ip(), $since]);
    return $byEmail >= 5 || $byIp >= 20;
}

/** Pulangkan user jika berjaya, null jika gagal. Kegagalan direkod untuk had cubaan. */
function attempt_login(string $email, string $password): ?array
{
    $u = row('SELECT * FROM users WHERE email = ?', [$email]);
    if (!$u || !password_verify($password, $u['password_hash'])) {
        q('INSERT INTO login_attempts (ip, email) VALUES (?, ?)', [client_ip(), $email]);
        return null;
    }
    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $u['id']]);
    }
    login_as($u);
    return $u;
}

function login_as(array $u): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    unset($_SESSION['as_shop']);
    q('UPDATE users SET last_login = NOW() WHERE id = ?', [$u['id']]);
    q('DELETE FROM login_attempts WHERE email = ?', [$u['email']]);
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
    }
    session_destroy();
}
