<?php
// Borang akaun dikongsi oleh app/akaun.php dan admin/akaun.php. Perlu: $me
if (is_post()) {
    check_csrf();
    $act = (string)($_POST['action'] ?? '');
    if ($act === 'profile') {
        $name = trim((string)($_POST['name'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('Nama dan emel yang sah diperlukan.', 'bad');
        } elseif (val('SELECT 1 FROM users WHERE email = ? AND id <> ?', [$email, $me['id']])) {
            flash('Emel ini sudah digunakan akaun lain.', 'bad');
        } else {
            q('UPDATE users SET name = ?, email = ? WHERE id = ?', [$name, $email, $me['id']]);
            flash('Profil disimpan.');
        }
    } elseif ($act === 'password') {
        $cur = (string)($_POST['current'] ?? '');
        $new = (string)($_POST['new'] ?? '');
        if (!password_verify($cur, $me['password_hash'])) {
            flash('Kata laluan semasa salah.', 'bad');
        } elseif (strlen($new) < 8) {
            flash('Kata laluan baru sekurang-kurangnya 8 aksara.', 'bad');
        } elseif ($new !== (string)($_POST['confirm'] ?? '')) {
            flash('Pengesahan kata laluan tidak sama.', 'bad');
        } else {
            q('UPDATE users SET password_hash = ?, must_change = 0 WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $me['id']]);
            session_regenerate_id(true);
            flash('Kata laluan ditukar.');
        }
    }
    redirect('akaun.php');
}
$title = 'Akaun';
$page = 'akaun';
include ROOT . '/includes/app_head.php';
?>
<div class="grid-2">
  <form method="post" class="card">
    <?= csrf_field() ?><input type="hidden" name="action" value="profile">
    <h2 style="font-size:1.15rem">Profil</h2>
    <div class="field"><label for="a-name">Nama</label><input id="a-name" name="name" value="<?= e($me['name']) ?>" maxlength="80" required autocomplete="name"></div>
    <div class="field"><label for="a-email">Emel (untuk log masuk)</label><input id="a-email" name="email" type="email" value="<?= e($me['email']) ?>" maxlength="120" required autocomplete="email"></div>
    <button class="btn" type="submit">Simpan</button>
  </form>
  <form method="post" class="card">
    <?= csrf_field() ?><input type="hidden" name="action" value="password">
    <h2 style="font-size:1.15rem">Tukar kata laluan</h2>
    <?php if ($me['must_change']): ?><div class="alert alert-warn">Anda sedang guna kata laluan sementara. Sila tukar sekarang.</div><?php endif; ?>
    <div class="field"><label for="a-cur">Kata laluan semasa</label><input id="a-cur" name="current" type="password" required autocomplete="current-password"></div>
    <div class="field"><label for="a-new">Kata laluan baru</label><input id="a-new" name="new" type="password" minlength="8" required autocomplete="new-password"></div>
    <div class="field"><label for="a-con">Ulang kata laluan baru</label><input id="a-con" name="confirm" type="password" minlength="8" required autocomplete="new-password"></div>
    <button class="btn" type="submit">Tukar kata laluan</button>
  </form>
</div>
<?php include ROOT . '/includes/app_foot.php';
