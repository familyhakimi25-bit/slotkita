<?php
require __DIR__ . '/bootstrap.php';
require ROOT . '/lib/shop.php';
capture_referral();

if (current_user()) {
    redirect(url(is_superadmin() ? 'admin/' : 'app/'));
}

$referrer = !empty($_COOKIE['sk_ref']) ? row('SELECT id, name FROM shops WHERE slug = ?', [(string)$_COOKIE['sk_ref']]) : null;
$v = ['shop_name' => '', 'slug' => '', 'category' => 'barber', 'city' => '', 'owner_name' => '', 'email' => '', 'phone' => ''];
$errors = [];

if (is_post()) {
    check_csrf();
    foreach ($v as $k => $_) {
        $v[$k] = trim((string)($_POST[$k] ?? ''));
    }
    $v['slug'] = strtolower($v['slug']);
    $v['email'] = strtolower($v['email']);
    $pass = (string)($_POST['password'] ?? '');

    if (!empty($_POST['website'])) {
        $errors['form'] = 'Pendaftaran ditolak.';
    }
    if (mb_strlen($v['shop_name']) < 2 || mb_strlen($v['shop_name']) > 80) {
        $errors['shop_name'] = 'Isi nama kedai (2-80 aksara).';
    }
    if ($p = slug_problem($v['slug'])) {
        $errors['slug'] = $p;
    }
    if (!isset(KATEGORI[$v['category']])) {
        $v['category'] = 'lain';
    }
    if (mb_strlen($v['owner_name']) < 2) {
        $errors['owner_name'] = 'Isi nama anda.';
    }
    if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Emel tidak sah.';
    } elseif (val('SELECT 1 FROM users WHERE email = ?', [$v['email']])) {
        $errors['email'] = 'Emel ini sudah didaftarkan. Cuba log masuk.';
    }
    $phone = norm_phone($v['phone']);
    if ($phone === '') {
        $errors['phone'] = 'Nombor telefon tidak sah. Contoh: 012-345 6789';
    }
    if (strlen($pass) < 8) {
        $errors['password'] = 'Kata laluan sekurang-kurangnya 8 aksara.';
    }
    if (empty($_POST['terms'])) {
        $errors['terms'] = 'Sila setuju dengan terma perkhidmatan.';
    }
    // Had 5 pendaftaran sehari bagi setiap IP (elak spam)
    if ((int)val("SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND email = '#signup' AND created_at > ?", [client_ip(), date('Y-m-d H:i:s', time() - 86400)]) >= 5) {
        $errors['form'] = 'Terlalu banyak pendaftaran dari rangkaian ini. Cuba esok atau hubungi kami.';
    }

    if (!$errors) {
        try {
            [$shopId, $userId] = create_shop([
                'slug' => $v['slug'], 'shop_name' => $v['shop_name'], 'category' => $v['category'],
                'city' => $v['city'] ?: null, 'owner_name' => $v['owner_name'], 'email' => $v['email'],
                'phone' => $phone, 'password' => $pass,
                'referred_by' => $referrer ? (int)$referrer['id'] : null,
            ]);
        } catch (PDOException $ex) {
            // Perlumbaan: slug/emel diambil serentak
            if ($ex->getCode() === '23000') {
                $errors['slug'] = 'Pautan atau emel baru sahaja diambil. Cuba lagi.';
            } else {
                throw $ex;
            }
        }
        if (!$errors) {
            q("INSERT INTO login_attempts (ip, email) VALUES (?, '#signup')", [client_ip()]);
            login_as(row('SELECT * FROM users WHERE id = ?', [$userId]));
            require_once ROOT . '/lib/telegram.php';
            tg_admin("🆕 Kedai baru: {$v['shop_name']} ({$v['category']}, {$v['city']})\n" . url($v['slug']));
            redirect(url('app/?baru=1'));
        }
    }
}

$title = 'Daftar kedai — ' . cfg('app_name');
$bare = true;
$sideTitle = 'Kedai anda boleh terima tempahan online dalam 5 minit.';
include ROOT . '/includes/site_head.php';
$err = fn($k) => isset($errors[$k]) ? '<span class="hint" style="color:var(--bad);font-weight:600">' . e($errors[$k]) . '</span>' : '';
?>
<div class="auth">
  <?php include ROOT . '/includes/auth_side.php'; ?>
  <main class="auth-main">
    <div class="auth-box">
      <a class="logo-mark auth-mobile-logo" href="<?= e(url('')) ?>"><span class="dot"><?= icon('calendar') ?></span><?= e(cfg('app_name')) ?></a>
      <h1>Daftar kedai</h1>
      <p class="sub">Percuma. <?= (int)cfg('trial_days', 14) ?> hari pertama dapat semua ciri Pro.</p>
      <?php if ($referrer): ?><div class="alert alert-info">Dijemput oleh <b><?= e($referrer['name']) ?></b> 👋</div><?php endif; ?>
      <?php if (isset($errors['form'])): ?><div class="alert alert-bad"><?= e($errors['form']) ?></div><?php endif; ?>
      <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="field">
          <label for="shop_name">Nama kedai</label>
          <input id="shop_name" name="shop_name" type="text" value="<?= e($v['shop_name']) ?>" maxlength="80" required placeholder="Cth: Tokdin Barbershop" autocomplete="organization">
          <?= $err('shop_name') ?>
        </div>
        <div class="field">
          <label for="slug">Pautan tempahan</label>
          <div class="input-group">
            <span class="addon"><?= e(preg_replace('#^https?://#', '', url(''))) ?></span>
            <input id="slug" name="slug" type="text" value="<?= e($v['slug']) ?>" maxlength="40" required placeholder="tokdin" autocapitalize="off" spellcheck="false">
          </div>
          <span class="slug-status" id="slug-status" aria-live="polite"></span>
          <?= $err('slug') ?>
        </div>
        <div class="row-2">
          <div class="field">
            <label for="category">Jenis perniagaan</label>
            <select id="category" name="category">
              <?php foreach (KATEGORI as $k => $label): ?><option value="<?= e($k) ?>"<?= $v['category'] === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="city">Bandar</label>
            <input id="city" name="city" type="text" value="<?= e($v['city']) ?>" maxlength="60" placeholder="Cth: Shah Alam">
          </div>
        </div>
        <div class="field">
          <label for="owner_name">Nama anda</label>
          <input id="owner_name" name="owner_name" type="text" value="<?= e($v['owner_name']) ?>" maxlength="80" required autocomplete="name">
          <?= $err('owner_name') ?>
        </div>
        <div class="row-2">
          <div class="field">
            <label for="email">Emel</label>
            <input id="email" name="email" type="email" value="<?= e($v['email']) ?>" maxlength="120" required autocomplete="email">
            <?= $err('email') ?>
          </div>
          <div class="field">
            <label for="phone">No. WhatsApp kedai</label>
            <input id="phone" name="phone" type="tel" value="<?= e($v['phone']) ?>" maxlength="20" required placeholder="012-345 6789" autocomplete="tel">
            <?= $err('phone') ?>
          </div>
        </div>
        <div class="field">
          <label for="password">Kata laluan</label>
          <div class="pw-wrap">
            <input id="password" name="password" type="password" minlength="8" required autocomplete="new-password" placeholder="Sekurang-kurangnya 8 aksara">
            <button type="button" class="pw-eye" aria-label="Tunjuk kata laluan" data-eye="password"><?= icon('eye', 'ic-sm') ?></button>
          </div>
          <?= $err('password') ?>
        </div>
        <div class="hp" aria-hidden="true" style="position:absolute;left:-9999px"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
        <label class="check small" style="margin-bottom:.4rem">
          <input type="checkbox" name="terms" value="1"<?= !empty($_POST['terms']) ? ' checked' : '' ?>>
          <span>Saya setuju dengan <a class="link" href="<?= e(url('terma.php')) ?>" target="_blank">terma & dasar privasi</a></span>
        </label>
        <?= $err('terms') ?>
        <button class="btn btn-brand btn-lg btn-block" style="margin-top:1rem" type="submit">Cipta kedai saya</button>
      </form>
      <p class="center small muted" style="margin-top:1.2rem">Sudah ada akaun? <a class="link" href="<?= e(url('masuk.php')) ?>">Log masuk</a></p>
    </div>
  </main>
</div>
<?php
$slugApi = e(url('api/slug.php'));
$footScripts = <<<JS
<script>
(function () {
  var name = document.getElementById('shop_name'), slug = document.getElementById('slug'), out = document.getElementById('slug-status');
  var touched = slug.value !== '', t;
  function mk(s) { return s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 40); }
  function check() {
    clearTimeout(t);
    var v = slug.value;
    if (!v) { out.textContent = ''; return; }
    t = setTimeout(function () {
      fetch('$slugApi?s=' + encodeURIComponent(v)).then(function (r) { return r.json(); }).then(function (j) {
        if (slug.value !== v) return;
        out.className = 'slug-status ' + (j.ok ? 'ok' : 'bad');
        out.textContent = j.ok ? '✓ Pautan ini boleh digunakan' : j.error;
      });
    }, 300);
  }
  name.addEventListener('input', function () { if (!touched) { slug.value = mk(name.value); check(); } });
  slug.addEventListener('input', function () {
    touched = true;
    var c = slug.value.toLowerCase().replace(/[^a-z0-9-]+/g, '-').replace(/-{2,}/g, '-').replace(/^-+/, '').slice(0, 40);
    if (c !== slug.value) slug.value = c;
    check();
  });
  if (slug.value) check();
  document.querySelectorAll('[data-eye]').forEach(function (b) {
    b.addEventListener('click', function () { var i = document.getElementById(b.dataset.eye); i.type = i.type === 'password' ? 'text' : 'password'; });
  });
})();
</script>
JS;
include ROOT . '/includes/site_foot.php';
