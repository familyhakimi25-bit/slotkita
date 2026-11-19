<?php
// Halaman tempahan awam setiap kedai:  slotkita.my/<slug>
require_once __DIR__ . '/bootstrap.php';
require_once ROOT . '/lib/shop.php';
require_once ROOT . '/lib/booking.php';

$shop = shop_by_slug((string)($_GET['k'] ?? ''));
if (!$shop) {
    http_response_code(404);
    require ROOT . '/404.php';
    exit;
}

$color = shop_color($shop);
$ink = ink_on($color);
$deep = readable_on_white($color);
$services = active_services((int)$shop['id']);
$staff = bookable_staff($shop);
$days = booking_days($shop);
$hours = shop_hours((int)$shop['id']);
$status = open_now($shop);
$quota = booking_quota_left($shop);
$suspended = $shop['status'] !== 'aktif';
$canBook = !$suspended && $services && $staff && $quota !== 0;
$showBrand = !plan_has($shop, 'white_label');
$initials = mb_strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', trim($shop['name'])), 0, 2))));
$mapQuery = trim(($shop['address'] ?? '') . ' ' . ($shop['city'] ?? ''));
$cover = plan_has($shop, 'branding') ? upload_url($shop['cover']) : '';

$data = [
    'slug'     => $shop['slug'],
    'csrf'     => csrf_token(),
    'api'      => url('api/'),
    'services' => array_map(fn($s) => ['id' => (int)$s['id'], 'name' => $s['name'], 'desc' => (string)$s['description'], 'min' => (int)$s['duration_min'], 'sen' => (int)$s['price_sen']], $services),
    'staff'    => array_map(fn($s) => ['id' => (int)$s['id'], 'name' => $s['name'], 'title' => (string)$s['title'], 'off' => $s['off_days']], $staff),
    'days'     => array_map(fn($d) => ['d' => $d['date'], 'open' => $d['open'], 'note' => $d['note']], $days),
];
?><!doctype html>
<html lang="ms">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Tempah — <?= e($shop['name']) ?></title>
<meta name="description" content="<?= e($shop['tagline'] ?: 'Tempah slot di ' . $shop['name'] . ' secara online, 24 jam.') ?>">
<meta property="og:title" content="Tempah di <?= e($shop['name']) ?>">
<meta property="og:description" content="<?= e($shop['tagline'] ?: 'Pilih servis, staf dan masa. Tempah dalam 1 minit.') ?>">
<?php if ($shop['logo']): ?><meta property="og:image" content="<?= e(upload_url($shop['logo'])) ?>"><?php endif; ?>
<meta name="theme-color" content="<?= e($color) ?>">
<link rel="icon" href="<?= e($shop['logo'] ? upload_url($shop['logo']) : url('assets/img/favicon.svg')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(url('assets/css/base.css?v=1')) ?>">
<link rel="stylesheet" href="<?= e(url('assets/css/kedai.css?v=1')) ?>">
<style>:root{--sb:<?= e($color) ?>;--sb-ink:<?= e($ink) ?>;--sb-deep:<?= e($deep) ?>}</style>
</head>
<body class="shop-page<?= isset($_GET['embed']) ? ' is-embed' : '' ?>">

<header class="shop-hero<?= $cover ? ' has-cover' : '' ?>"<?= $cover ? ' style="--cover:url(\'' . e($cover) . '\')"' : '' ?>>
  <div class="shop-wrap">
    <div class="shop-id">
      <?php if ($shop['logo']): ?>
        <img class="shop-logo" src="<?= e(upload_url($shop['logo'])) ?>" alt="Logo <?= e($shop['name']) ?>" width="72" height="72">
      <?php else: ?>
        <span class="shop-logo initials" aria-hidden="true"><?= e($initials) ?></span>
      <?php endif; ?>
      <div class="grow">
        <h1><?= e($shop['name']) ?></h1>
        <p class="shop-meta"><?= e(KATEGORI[$shop['category']] ?? '') ?><?= $shop['city'] ? ' · ' . e($shop['city']) : '' ?></p>
      </div>
    </div>
    <span class="open-pill <?= $status['open'] ? 'is-open' : '' ?>"><i></i><?= e($status['text']) ?></span>
    <?php if ($shop['tagline']): ?><p class="shop-tag"><?= e($shop['tagline']) ?></p><?php endif; ?>
    <div class="shop-actions">
      <?php if ($shop['phone']): ?><a class="chip" href="<?= e(wa_link($shop['phone'], 'Hai ' . $shop['name'] . ', saya nak tanya')) ?>" target="_blank" rel="noopener"><?= icon('chat', 'ic-sm') ?> WhatsApp</a><?php endif; ?>
      <?php if ($mapQuery !== ''): ?><a class="chip" href="https://www.google.com/maps/search/?api=1&query=<?= e(rawurlencode($shop['name'] . ' ' . $mapQuery)) ?>" target="_blank" rel="noopener"><?= icon('pin', 'ic-sm') ?> Arah</a><?php endif; ?>
      <a class="chip" href="<?= e(url('semak.php?k=' . $shop['slug'])) ?>"><?= icon('receipt', 'ic-sm') ?> Semak tempahan</a>
    </div>
  </div>
</header>

<main class="shop-wrap book" id="tempah">
<?php if (!$canBook): ?>
  <div class="closed-card">
    <?= icon('calendar', 'ic-lg') ?>
    <h2><?= $suspended ? 'Tempahan online ditutup sementara' : ($quota === 0 ? 'Tempahan online penuh bulan ini' : 'Kedai belum buka tempahan online') ?></h2>
    <p class="muted">Sila hubungi kedai terus untuk buat tempahan.</p>
    <?php if ($shop['phone']): ?><a class="btn sb-btn" href="<?= e(wa_link($shop['phone'], 'Hai ' . $shop['name'] . ', saya nak buat tempahan')) ?>" target="_blank" rel="noopener"><?= icon('chat', 'ic-sm') ?> WhatsApp kedai</a><?php endif; ?>
  </div>
<?php else: ?>
  <ol class="stepper" aria-label="Langkah tempahan">
    <li class="on" data-s="1"><span>1</span>Servis</li>
    <li data-s="2"<?= count($staff) === 1 ? ' hidden' : '' ?>><span>2</span>Staf</li>
    <li data-s="3"><span><?= count($staff) === 1 ? 2 : 3 ?></span>Masa</li>
    <li data-s="4"><span><?= count($staff) === 1 ? 3 : 4 ?></span>Butiran</li>
  </ol>

  <!-- Langkah 1: servis -->
  <section class="step on" data-step="1" aria-labelledby="h-s1">
    <h2 id="h-s1">Pilih servis</h2>
    <p class="muted small">Boleh pilih lebih dari satu.</p>
    <div class="svc-list">
      <?php foreach ($services as $s): ?>
      <label class="svc">
        <input type="checkbox" value="<?= (int)$s['id'] ?>">
        <span class="svc-body">
          <span class="svc-name"><?= e($s['name']) ?></span>
          <?php if ($s['description']): ?><span class="svc-desc"><?= e($s['description']) ?></span><?php endif; ?>
          <span class="svc-min"><?= icon('clock', 'ic-sm') ?> <?= (int)$s['duration_min'] ?> min</span>
        </span>
        <span class="svc-price"><?= e(rm((int)$s['price_sen'])) ?></span>
        <span class="svc-tick"><?= icon('check', 'ic-sm') ?></span>
      </label>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- Langkah 2: staf -->
  <section class="step" data-step="2" aria-labelledby="h-s2">
    <h2 id="h-s2">Pilih staf</h2>
    <div class="staff-list">
      <button type="button" class="staff on" data-id="0">
        <span class="av any"><?= icon('zap', 'ic-sm') ?></span>
        <span><b>Sesiapa</b><small>Slot paling banyak</small></span>
      </button>
      <?php foreach ($staff as $st): ?>
      <button type="button" class="staff" data-id="<?= (int)$st['id'] ?>">
        <span class="av"><?= e(mb_strtoupper(mb_substr($st['name'], 0, 1))) ?></span>
        <span><b><?= e($st['name']) ?></b><small><?= e($st['title'] ?: 'Staf') ?></small></span>
      </button>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- Langkah 3: tarikh & masa -->
  <section class="step" data-step="3" aria-labelledby="h-s3">
    <h2 id="h-s3">Pilih tarikh & masa</h2>
    <div class="date-strip" role="listbox" aria-label="Tarikh"></div>
    <div class="slots" aria-live="polite"></div>
  </section>

  <!-- Langkah 4: butiran -->
  <section class="step" data-step="4" aria-labelledby="h-s4">
    <h2 id="h-s4">Butiran anda</h2>
    <div class="summary" id="summary"></div>
    <form id="book-form" novalidate>
      <div class="field">
        <label for="f-name">Nama</label>
        <input id="f-name" name="name" type="text" autocomplete="name" maxlength="60" required placeholder="Nama anda">
      </div>
      <div class="field">
        <label for="f-phone">No. telefon (WhatsApp)</label>
        <input id="f-phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" maxlength="20" required placeholder="012-345 6789">
        <span class="hint">Kedai akan hubungi nombor ini jika ada perubahan.</span>
      </div>
      <div class="field">
        <label for="f-note">Nota untuk kedai <span class="muted">(pilihan)</span></label>
        <textarea id="f-note" name="note" maxlength="200" rows="2" placeholder="Cth: nak potong pendek sikit"></textarea>
      </div>
      <div class="hp" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
      <div class="form-err alert alert-bad" hidden></div>
    </form>
  </section>

  <!-- Berjaya -->
  <section class="step" data-step="5" aria-live="polite">
    <div class="done">
      <div class="done-ic"><?= icon('check', 'ic-lg') ?></div>
      <h2>Tempahan diterima!</h2>
      <p class="muted done-status"></p>
      <div class="ticket">
        <div class="t-code"><small>Kod tempahan</small><b id="d-code"></b></div>
        <dl id="d-list"></dl>
      </div>
      <div class="done-actions">
        <a class="btn sb-btn btn-block" id="d-gcal" target="_blank" rel="noopener"><?= icon('calendar', 'ic-sm') ?> Simpan dalam kalendar</a>
        <?php if ($shop['phone']): ?><a class="btn btn-ghost btn-block" id="d-wa" target="_blank" rel="noopener"><?= icon('chat', 'ic-sm') ?> Beritahu kedai di WhatsApp</a><?php endif; ?>
        <button type="button" class="link" id="d-again">Buat tempahan lain</button>
      </div>
      <p class="small muted">Ambil tangkapan skrin halaman ini. Untuk batal, guna <a href="<?= e(url('semak.php?k=' . $shop['slug'])) ?>" class="sb-link">Semak tempahan</a>.</p>
    </div>
  </section>

  <div class="book-bar" id="bar">
    <div class="bar-sum"><b id="bar-total">Pilih servis</b><small id="bar-meta"></small></div>
    <button type="button" class="btn btn-ghost btn-icon" id="btn-back" aria-label="Kembali" hidden><?= icon('back', 'ic-sm') ?></button>
    <button type="button" class="btn sb-btn" id="btn-next" disabled>Seterusnya <?= icon('arrow', 'ic-sm') ?></button>
  </div>
<?php endif; ?>
</main>

<section class="shop-wrap shop-info">
  <?php if ($shop['about']): ?>
  <div class="info-card">
    <h3>Tentang kami</h3>
    <p><?= nl2br(e($shop['about'])) ?></p>
  </div>
  <?php endif; ?>
  <div class="info-card">
    <h3>Waktu operasi</h3>
    <table class="hours">
      <?php foreach ([1, 2, 3, 4, 5, 6, 0] as $w): $h = $hours[$w]; ?>
      <tr class="<?= (int)date('w') === $w ? 'today' : '' ?>">
        <th><?= HARI[$w] ?></th>
        <td><?= $h ? e(masa($h['open']) . ' – ' . masa($h['close'])) : '<span class="muted">Tutup</span>' ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php if ($mapQuery !== '' || $shop['phone']): ?>
  <div class="info-card">
    <h3>Lokasi & hubungi</h3>
    <?php if ($mapQuery !== ''): ?><p class="flex"><?= icon('pin', 'ic-sm') ?> <?= e($mapQuery) ?></p><?php endif; ?>
    <?php if ($shop['phone']): ?><p class="flex"><?= icon('phone', 'ic-sm') ?> <a class="sb-link" href="tel:+<?= e($shop['phone']) ?>"><?= e(show_phone($shop['phone'])) ?></a></p><?php endif; ?>
  </div>
  <?php endif; ?>
</section>

<?php if ($showBrand): ?>
<footer class="powered">
  <a href="<?= e(url('?ref=' . $shop['slug'])) ?>" target="_blank" rel="noopener">Dikuasakan oleh <b><?= e(cfg('app_name')) ?></b> · Buat halaman tempahan kedai anda sendiri, percuma</a>
</footer>
<?php endif; ?>

<?php if ($canBook): ?>
<script id="shop-data" type="application/json"><?= json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= e(url('assets/js/kedai.js?v=1')) ?>" defer></script>
<?php endif; ?>
</body>
</html>
