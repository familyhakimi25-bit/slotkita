<?php
/**
 * Layout panel. Pemboleh ubah: $title, $page (kunci menu aktif), $area ('app' | 'admin'), $shop (untuk app)
 */
$area = $area ?? 'app';
$me = current_user();

if ($area === 'app') {
    $pending = (int)val("SELECT COUNT(*) FROM bookings WHERE shop_id = ? AND status = 'menunggu' AND start_at >= NOW()", [$shop['id']]);
    $nav = [
        ['index', '', 'grid', 'Utama'],
        ['tempahan', 'tempahan.php', 'calendar', 'Tempahan', $pending],
        ['servis', 'servis.php', 'scissors', 'Servis & harga'],
        ['staf', 'staf.php', 'users', 'Staf'],
        ['waktu', 'waktu.php', 'clock', 'Waktu & cuti'],
        '-',
        ['kedai', 'kedai.php', 'store', 'Profil kedai'],
        ['kongsi', 'kongsi.php', 'qr', 'Kongsi & poster'],
        ['notifikasi', 'notifikasi.php', 'bell', 'Notifikasi'],
        ['langganan', 'langganan.php', 'card', 'Langganan'],
        ['akaun', 'akaun.php', 'user', 'Akaun'],
    ];
    $bottom = [['index', '', 'grid', 'Utama'], ['tempahan', 'tempahan.php', 'calendar', 'Tempahan'], ['kongsi', 'kongsi.php', 'qr', 'Kongsi']];
} else {
    $pendingPay = (int)val("SELECT COUNT(*) FROM payments WHERE method = 'manual' AND status = 'pending'");
    $nav = [
        ['index', '', 'chart', 'Ringkasan'],
        ['kedai', 'kedai.php', 'store', 'Kedai'],
        ['bayaran', 'bayaran.php', 'coins', 'Bayaran', $pendingPay],
        '-',
        ['akaun', 'akaun.php', 'user', 'Akaun'],
    ];
    $bottom = [['index', '', 'chart', 'Ringkasan'], ['kedai', 'kedai.php', 'store', 'Kedai'], ['bayaran', 'bayaran.php', 'coins', 'Bayaran']];
}
?><!doctype html>
<html lang="ms">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title) ?> — <?= e(cfg('app_name')) ?></title>
<meta name="robots" content="noindex">
<meta name="theme-color" content="#16130f">
<link rel="icon" href="<?= e(url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(url('assets/css/base.css?v=1')) ?>">
<link rel="stylesheet" href="<?= e(url('assets/css/app.css?v=1')) ?>">
<meta name="csrf" content="<?= e(csrf_token()) ?>">
</head>
<body>
<div class="app" id="app">
  <aside class="side" id="side">
    <a class="logo-mark" href="<?= e(url('')) ?>"><span class="dot"><?= icon('calendar') ?></span><?= e(cfg('app_name')) ?></a>
    <?php if ($area === 'app'): ?>
      <a class="side-shop" href="<?= e(url($shop['slug'])) ?>" target="_blank" rel="noopener" title="Buka halaman tempahan">
        <span class="sq"><?php if ($shop['logo']): ?><img src="<?= e(url('uploads/' . $shop['logo'])) ?>" alt=""><?php else: ?><?= e(mb_strtoupper(mb_substr($shop['name'], 0, 1))) ?><?php endif; ?></span>
        <span class="grow"><b><?= e($shop['name']) ?></b><small>/<?= e($shop['slug']) ?> <?= icon('external', 'ic-sm') ?></small></span>
      </a>
    <?php else: ?>
      <div class="side-shop"><span class="sq"><?= icon('shield', 'ic-sm') ?></span><span class="grow"><b>Admin platform</b><small><?= e($me['email']) ?></small></span></div>
    <?php endif; ?>
    <nav aria-label="Panel">
      <?php foreach ($nav as $n): if ($n === '-') { echo '<div class="sep"></div>'; continue; } ?>
        <a href="<?= e(url($area . '/' . $n[1])) ?>" class="<?= $page === $n[0] ? 'on' : '' ?>"<?= $page === $n[0] ? ' aria-current="page"' : '' ?>>
          <?= icon($n[2]) ?> <?= e($n[3]) ?><?php if (!empty($n[4])): ?><span class="pill"><?= (int)$n[4] ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="side-foot">
      <nav>
        <form method="post" action="<?= e(url('keluar.php')) ?>">
          <?= csrf_field() ?>
          <?php if ($area === 'app' && is_superadmin()): ?>
            <button type="submit" name="stop_as" value="1"><?= icon('back') ?> Kembali ke admin</button>
          <?php else: ?>
            <button type="submit"><?= icon('logout') ?> Log keluar</button>
          <?php endif; ?>
        </form>
      </nav>
    </div>
  </aside>

  <div class="main">
    <?php if ($area === 'app' && is_superadmin()): ?>
      <div class="banner banner-ink"><?= icon('eye', 'ic-sm') ?> <span>Anda sedang melihat panel <b><?= e($shop['name']) ?></b> sebagai admin.</span></div>
    <?php endif; ?>
    <?php if ($area === 'app'):
        $eff = shop_plan($shop);
        $trialLeft = on_trial($shop) ? days_left($shop['trial_until']) : -1;
        $paidLeft = $shop['plan'] !== 'percuma' && $shop['paid_until'] ? days_left($shop['paid_until']) : -1;
        if ($shop['status'] === 'digantung'): ?>
          <div class="banner banner-bad"><?= icon('lock', 'ic-sm') ?> Kedai ini digantung. Halaman tempahan ditutup.</div>
        <?php elseif ($trialLeft >= 0): ?>
          <div class="banner banner-brand"><?= icon('sparkle', 'ic-sm') ?> <span>Percubaan Pro: tinggal <b><?= $trialLeft === 0 ? 'hari terakhir' : $trialLeft . ' hari' ?></b>. Lepas itu kedai anda terus jalan dengan pelan Percuma.</span>
            <?php if ($page !== 'langganan'): ?><a class="btn btn-sm" href="<?= e(url('app/langganan.php')) ?>">Lihat pelan</a><?php endif; ?></div>
        <?php elseif ($paidLeft >= 0 && $paidLeft <= 7): ?>
          <div class="banner banner-warn"><?= icon('clock', 'ic-sm') ?> Pelan <?= e(PLANS[$shop['plan']]['name']) ?> tamat <?= $paidLeft === 0 ? 'hari ini' : 'dalam ' . $paidLeft . ' hari' ?> (<?= e(tarikh($shop['paid_until'], false)) ?>).
            <?php if ($page !== 'langganan'): ?><a class="btn btn-sm" href="<?= e(url('app/langganan.php')) ?>">Sambung sekarang</a><?php endif; ?></div>
        <?php elseif ($eff === 'percuma' && $shop['plan'] !== 'percuma' && $shop['paid_until']): ?>
          <div class="banner banner-bad"><?= icon('clock', 'ic-sm') ?> Pelan <?= e(PLANS[$shop['plan']]['name']) ?> tamat pada <?= e(tarikh($shop['paid_until'], false)) ?>. Kedai kini guna had pelan Percuma.
            <?php if ($page !== 'langganan'): ?><a class="btn btn-sm" href="<?= e(url('app/langganan.php')) ?>">Sambung</a><?php endif; ?></div>
        <?php endif; ?>
    <?php endif; ?>

    <header class="topbar">
      <button class="btn btn-ghost btn-icon menu-btn" type="button" aria-label="Menu" aria-controls="side" aria-expanded="false" id="menu-btn"><?= icon('menu') ?></button>
      <h1><?= e($title) ?></h1>
      <div class="actions"><?= $topActions ?? '' ?></div>
    </header>
    <div class="content">
      <?= flashes() ?>
