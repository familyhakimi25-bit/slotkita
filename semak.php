<?php
// Pelanggan semak / batal tempahan guna kod + nombor telefon (tiada akaun)
require __DIR__ . '/bootstrap.php';
require ROOT . '/lib/shop.php';
require ROOT . '/lib/booking.php';

$shop = shop_by_slug((string)($_GET['k'] ?? $_POST['k'] ?? ''));
if (!$shop) {
    http_response_code(404);
    require ROOT . '/404.php';
    exit;
}
$code = strtoupper(trim((string)($_POST['code'] ?? $_GET['c'] ?? '')));
$phone = trim((string)($_POST['phone'] ?? ''));
$b = null;
$error = '';
$done = '';

if (is_post()) {
    check_csrf();
    $since = date('Y-m-d H:i:s', time() - 900);
    if ((int)val("SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND email = '#semak' AND created_at > ?", [client_ip(), $since]) >= 20) {
        $error = 'Terlalu banyak cubaan. Tunggu 15 minit.';
    } else {
        $id = val('SELECT id FROM bookings WHERE shop_id = ? AND code = ? AND customer_phone = ?', [$shop['id'], $code, norm_phone($phone)]);
        if (!$id) {
            q("INSERT INTO login_attempts (ip, email) VALUES (?, '#semak')", [client_ip()]);
            $error = 'Tempahan tidak dijumpai. Semak kod dan nombor telefon.';
        } else {
            $b = booking_full((int)$id);
            if (($_POST['action'] ?? '') === 'cancel') {
                $deadline = strtotime($b['start_at']) - (int)$shop['cancel_hours'] * 3600;
                if (!in_array($b['status'], HOLDING, true)) {
                    $error = 'Tempahan ini tidak boleh dibatalkan lagi.';
                } elseif (time() > $deadline) {
                    $error = 'Sudah terlalu lewat untuk batal online. Sila hubungi kedai terus.';
                } else {
                    q("UPDATE bookings SET status = 'batal' WHERE id = ?", [$b['id']]);
                    $b['status'] = 'batal';
                    $done = 'Tempahan dibatalkan. Slot anda sudah dibuka untuk orang lain.';
                    if ($shop['tg_chat_id'] && plan_has($shop, 'telegram')) {
                        require_once ROOT . '/lib/telegram.php';
                        $t = strtotime($b['start_at']);
                        tg_send($shop['tg_chat_id'], "❌ Dibatalkan oleh pelanggan #{$b['code']}\n{$b['customer_name']} — " . tarikh(date('Y-m-d', $t)) . ', ' . masa(date('H:i:s', $t)));
                    }
                }
            }
        }
    }
}

$color = shop_color($shop);
?><!doctype html>
<html lang="ms">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Semak tempahan — <?= e($shop['name']) ?></title>
<meta name="robots" content="noindex">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(url('assets/css/base.css?v=1')) ?>">
<link rel="stylesheet" href="<?= e(url('assets/css/kedai.css?v=1')) ?>">
<style>:root{--sb:<?= e($color) ?>;--sb-ink:<?= e(ink_on($color)) ?>;--sb-deep:<?= e(readable_on_white($color)) ?>}.shop-page{padding-bottom:2rem}</style>
</head>
<body class="shop-page">
<header class="shop-hero" style="padding-bottom:2.4rem">
  <div class="shop-wrap">
    <a class="chip" href="<?= e(shop_url($shop)) ?>"><?= icon('back', 'ic-sm') ?> <?= e($shop['name']) ?></a>
    <h1 style="margin-top:1rem">Semak tempahan</h1>
  </div>
</header>
<main class="shop-wrap book">
  <?php if ($done): ?><div class="alert alert-ok"><?= e($done) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-bad"><?= e($error) ?></div><?php endif; ?>

  <?php if ($b): $t = strtotime($b['start_at']); ?>
    <div class="ticket" style="margin-top:0">
      <div class="t-code"><small>Kod tempahan</small><b><?= e($b['code']) ?></b></div>
      <dl>
        <dt>Status</dt><dd><?= e(STATUS[$b['status']] ?? $b['status']) ?></dd>
        <dt>Nama</dt><dd><?= e($b['customer_name']) ?></dd>
        <dt>Servis</dt><dd><?= e(booking_service_names($b)) ?></dd>
        <dt>Tarikh</dt><dd><?= e(tarikh(date('Y-m-d', $t))) ?></dd>
        <dt>Masa</dt><dd><?= e(masa(date('H:i:s', $t))) ?></dd>
        <?php if ($b['staff_name']): ?><dt>Staf</dt><dd><?= e($b['staff_name']) ?></dd><?php endif; ?>
        <dt>Jumlah</dt><dd><?= e(rm((int)$b['total_sen'])) ?></dd>
      </dl>
    </div>
    <?php if (in_array($b['status'], HOLDING, true)): ?>
      <?php if (time() <= $t - (int)$shop['cancel_hours'] * 3600): ?>
        <form method="post" onsubmit="return this.dataset.sure === '1' || (this.dataset.sure = '1', this.querySelector('button').textContent = 'Tekan sekali lagi untuk sahkan batal', false)">
          <?= csrf_field() ?>
          <input type="hidden" name="k" value="<?= e($shop['slug']) ?>"><input type="hidden" name="code" value="<?= e($b['code']) ?>"><input type="hidden" name="phone" value="<?= e($phone) ?>">
          <input type="hidden" name="action" value="cancel">
          <button class="btn btn-danger btn-block" type="submit"><?= icon('x', 'ic-sm') ?> Batalkan tempahan</button>
        </form>
        <p class="hint center" style="margin-top:.6rem">Pembatalan online dibenarkan sehingga <?= (int)$shop['cancel_hours'] ?> jam sebelum masa tempahan.</p>
      <?php else: ?>
        <p class="muted small center">Terlalu lewat untuk batal online. <?php if ($shop['phone']): ?><a class="sb-link" href="<?= e(wa_link($shop['phone'], 'Hai, saya nak batal/tukar tempahan #' . $b['code'])) ?>">WhatsApp kedai</a><?php endif; ?></p>
      <?php endif; ?>
    <?php endif; ?>
  <?php else: ?>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="k" value="<?= e($shop['slug']) ?>">
      <div class="field"><label for="code">Kod tempahan</label><input id="code" name="code" value="<?= e($code) ?>" maxlength="8" required autocapitalize="characters" style="text-transform:uppercase;letter-spacing:.15em;font-weight:800" placeholder="Cth: K7PX2A"></div>
      <div class="field"><label for="phone">No. telefon yang digunakan</label><input id="phone" name="phone" type="tel" value="<?= e($phone) ?>" maxlength="20" required autocomplete="tel"></div>
      <button class="btn sb-btn btn-block" type="submit">Semak</button>
    </form>
  <?php endif; ?>
</main>
</body>
</html>
