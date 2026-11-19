<?php
/** @var string $title  @var string $desc  @var bool $bare (tanpa nav) */
$title = $title ?? cfg('app_name');
$desc = $desc ?? 'Sistem tempahan online untuk barber, salon, spa dan kedai servis di Malaysia. Pelanggan tempah sendiri 24 jam.';
?><!doctype html>
<html lang="ms">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:type" content="website">
<meta name="theme-color" content="#16130f">
<link rel="icon" href="<?= e(url('assets/')) ?>img/favicon.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(url('assets/')) ?>css/base.css?v=1">
<link rel="stylesheet" href="<?= e(url('assets/')) ?>css/site.css?v=1">
</head>
<body class="<?= e($bodyClass ?? '') ?>">
<?php if (empty($bare)): ?>
<header class="site-nav" id="atas">
  <div class="wrap nav-in">
    <a class="logo-mark" href="<?= e(url('')) ?>"><span class="dot"><?= icon('calendar') ?></span><?= e(cfg('app_name')) ?></a>
    <nav class="nav-links" aria-label="Utama">
      <a href="<?= e(url('#ciri')) ?>">Ciri</a>
      <a href="<?= e(url('#harga')) ?>">Harga</a>
      <a href="<?= e(url('#soalan')) ?>">Soalan</a>
    </nav>
    <div class="nav-cta">
      <?php if (current_user()): ?>
        <a class="btn btn-sm" href="<?= e(url(is_superadmin() ? 'admin/' : 'app/')) ?>">Panel saya</a>
      <?php else: ?>
        <a class="nav-login" href="<?= e(url('masuk.php')) ?>">Masuk</a>
        <a class="btn btn-sm" href="<?= e(url('daftar.php')) ?>">Cuba percuma</a>
      <?php endif; ?>
    </div>
  </div>
</header>
<?php endif; ?>
