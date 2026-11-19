<?php
require __DIR__ . '/bootstrap.php';

/** Hanya benarkan lencongan ke laluan dalaman (elak open redirect) */
function safe_next(string $n): string
{
    return preg_match('#^/(app|admin)/[A-Za-z0-9_./?=&%-]*$#', $n) && !str_contains($n, '//') ? $n : '';
}

$next = safe_next((string)($_GET['next'] ?? $_POST['next'] ?? ''));
if (current_user()) {
    redirect($next ?: url(is_superadmin() ? 'admin/' : 'app/'));
}

$email = '';
$error = '';
if (is_post()) {
    check_csrf();
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $pass = (string)($_POST['password'] ?? '');
    if (login_blocked($email)) {
        $error = 'Terlalu banyak cubaan. Tunggu 15 minit dan cuba lagi.';
    } elseif ($u = attempt_login($email, $pass)) {
        redirect($next ?: url($u['role'] === 'superadmin' ? 'admin/' : 'app/'));
    } else {
        $error = 'Emel atau kata laluan salah.';
    }
}

$title = 'Log masuk — ' . cfg('app_name');
$bare = true;
$sideTitle = 'Selamat kembali. Tempahan baru sedang menunggu.';
include ROOT . '/includes/site_head.php';
?>
<div class="auth">
  <?php include ROOT . '/includes/auth_side.php'; ?>
  <main class="auth-main">
    <div class="auth-box">
      <a class="logo-mark auth-mobile-logo" href="<?= e(url('')) ?>"><span class="dot"><?= icon('calendar') ?></span><?= e(cfg('app_name')) ?></a>
      <h1>Log masuk</h1>
      <p class="sub">Urus tempahan kedai anda.</p>
      <?php if ($error): ?><div class="alert alert-bad" role="alert"><?= e($error) ?></div><?php endif; ?>
      <?php if (isset($_GET['keluar'])): ?><div class="alert alert-ok">Anda telah log keluar.</div><?php endif; ?>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <div class="field">
          <label for="email">Emel</label>
          <input id="email" name="email" type="email" value="<?= e($email) ?>" required autocomplete="email" autofocus>
        </div>
        <div class="field">
          <div class="flex between"><label for="password" class="label">Kata laluan</label><a class="link small" href="<?= e(url('lupa.php')) ?>">Lupa?</a></div>
          <div class="pw-wrap">
            <input id="password" name="password" type="password" required autocomplete="current-password">
            <button type="button" class="pw-eye" aria-label="Tunjuk kata laluan" onclick="var i=document.getElementById('password');i.type=i.type==='password'?'text':'password'"><?= icon('eye', 'ic-sm') ?></button>
          </div>
        </div>
        <button class="btn btn-lg btn-block" type="submit">Log masuk</button>
      </form>
      <p class="center small muted" style="margin-top:1.2rem">Belum ada akaun? <a class="link" href="<?= e(url('daftar.php')) ?>">Daftar kedai percuma</a></p>
    </div>
  </main>
</div>
<?php include ROOT . '/includes/site_foot.php';
