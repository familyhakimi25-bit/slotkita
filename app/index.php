<?php
require __DIR__ . '/_boot.php';
require ROOT . '/lib/booking_view.php';
require ROOT . '/lib/chart.php';
require_once ROOT . '/lib/telegram.php';

$sid = (int)$shop['id'];
$today = date('Y-m-d');
$monday = date('Y-m-d', strtotime('monday this week'));
$live = "b.status IN ('menunggu','disahkan','selesai')";

$cToday = (int)val("SELECT COUNT(*) FROM bookings b WHERE shop_id = ? AND DATE(start_at) = ? AND $live", [$sid, $today]);
$cWeek = (int)val("SELECT COUNT(*) FROM bookings b WHERE shop_id = ? AND start_at >= ? AND start_at < ? AND $live", [$sid, $monday, date('Y-m-d', strtotime("$monday +7 days"))]);
$salesMonth = (int)val("SELECT COALESCE(SUM(total_sen),0) FROM bookings WHERE shop_id = ? AND start_at >= ? AND start_at < ? AND status IN ('disahkan','selesai')",
    [$sid, date('Y-m-01'), date('Y-m-01', strtotime('first day of next month'))]);
$onlineMonth = month_online_bookings($sid);
$limit = PLANS[shop_plan($shop)]['bookings'];
$pending = (int)val("SELECT COUNT(*) FROM bookings WHERE shop_id = ? AND status = 'menunggu' AND start_at >= NOW()", [$sid]);

$todayList = shop_bookings($sid, "DATE(b.start_at) = ? AND b.status <> 'batal'", [$today]);
$upcoming = $todayList ? [] : shop_bookings($sid, "b.start_at > NOW() AND b.status IN ('menunggu','disahkan')", [], 'b.start_at', 5);

// Carta 14 hari
$counts = [];
foreach (rows("SELECT DATE(start_at) d, COUNT(*) n FROM bookings b WHERE shop_id = ? AND start_at >= ? AND start_at < ? AND $live GROUP BY DATE(start_at)",
    [$sid, date('Y-m-d', strtotime('-13 days')), date('Y-m-d', strtotime('+1 day'))]) as $r) {
    $counts[$r['d']] = (int)$r['n'];
}
$points = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $w = (int)date('w', strtotime($d));
    $points[] = ['label' => $i === 0 ? 'Hari ini' : HARI_PENDEK[$w] . ' ' . date('j', strtotime($d)), 'tip' => tarikh($d), 'value' => $counts[$d] ?? 0];
}

$topServices = rows(
    "SELECT bs.name, COUNT(*) n, SUM(bs.price_sen) sen FROM booking_services bs JOIN bookings b ON b.id = bs.booking_id
     WHERE b.shop_id = ? AND b.start_at >= ? AND b.status IN ('disahkan','selesai') GROUP BY bs.name ORDER BY n DESC LIMIT 5",
    [$sid, date('Y-m-01')]
);

// Senarai semak permulaan
$samples = array_map(fn($s) => $s[0] . '|' . $s[2], SAMPLE_SERVICES[$shop['category']] ?? []);
$svcNow = array_map(fn($s) => $s['name'] . '|' . $s['price_sen'], rows('SELECT name, price_sen FROM services WHERE shop_id = ?', [$sid]));
$hoursChanged = (bool)val("SELECT 1 FROM hours WHERE shop_id = ? AND NOT ((weekday = 0 AND open_time = '11:00:00' AND close_time = '18:00:00') OR (weekday > 0 AND open_time = '10:00:00' AND close_time = '21:00:00')) LIMIT 1", [$sid])
    || (bool)val('SELECT 1 FROM closures WHERE shop_id = ? LIMIT 1', [$sid]);
$steps = [
    ['Semak servis & harga', 'servis.php', $svcNow && array_diff($svcNow, $samples) !== []],
    ['Tetapkan waktu operasi & hari cuti', 'waktu.php', $hoursChanged],
    ['Muat naik logo kedai', 'kedai.php', (bool)$shop['logo']],
];
if (tg_enabled()) {
    $steps[] = ['Sambung notifikasi Telegram', 'notifikasi.php', (bool)$shop['tg_chat_id']];
}
$steps[] = ['Kongsi pautan & dapat tempahan online pertama', 'kongsi.php', (bool)val("SELECT 1 FROM bookings WHERE shop_id = ? AND source = 'online' LIMIT 1", [$sid])];
$doneSteps = count(array_filter(array_column($steps, 2)));

$link = shop_url($shop);
$title = 'Utama';
$page = 'index';
$topActions = '<a class="btn btn-sm" href="tempahan.php?baru=1">' . icon('plus', 'ic-sm') . ' Walk-in</a>';
include ROOT . '/includes/app_head.php';
?>

<?php if (isset($_GET['baru'])): ?>
  <div class="alert alert-ok">🎉 Kedai <?= e($shop['name']) ?> dah siap! Halaman tempahan anda sudah hidup di <b><?= e(preg_replace('#^https?://#', '', $link)) ?></b>. Ikut senarai semak di bawah untuk mula.</div>
<?php endif; ?>
<?php if ($pending): ?>
  <div class="alert alert-warn flex between flex-wrap"><span><?= icon('clock', 'ic-sm') ?> <?= $pending ?> tempahan menunggu pengesahan anda.</span><a class="btn btn-sm" href="tempahan.php?status=menunggu">Semak sekarang</a></div>
<?php endif; ?>

<div class="kpis">
  <div class="kpi"><div class="k-label"><?= icon('calendar', 'ic-sm') ?> Hari ini</div><div class="k-val"><?= $cToday ?></div><div class="k-sub">tempahan</div></div>
  <div class="kpi"><div class="k-label"><?= icon('list', 'ic-sm') ?> Minggu ini</div><div class="k-val"><?= $cWeek ?></div><div class="k-sub">Isnin – Ahad</div></div>
  <div class="kpi"><div class="k-label"><?= icon('coins', 'ic-sm') ?> Jualan <?= e(BULAN_PENUH[(int)date('n') - 1]) ?></div><div class="k-val"><?= e(rm($salesMonth)) ?></div><div class="k-sub">anggaran, tempahan disahkan & selesai</div></div>
  <div class="kpi"><div class="k-label"><?= icon('zap', 'ic-sm') ?> Tempahan online bulan ini</div>
    <div class="k-val"><?= $onlineMonth ?><?php if ($limit !== null): ?><span class="muted" style="font-size:1rem;font-weight:700"> / <?= $limit ?></span><?php endif; ?></div>
    <?php if ($limit !== null): $pct = min(100, (int)round($onlineMonth / $limit * 100)); ?>
      <div class="meter<?= $pct >= 80 ? ' hot' : '' ?>"><i style="width:<?= $pct ?>%"></i></div>
      <?php if ($pct >= 80): ?><div class="k-sub"><a class="link" href="langganan.php">Naik taraf untuk lebih</a></div><?php endif; ?>
    <?php else: ?><div class="k-sub">tanpa had (pelan <?= e(PLANS[shop_plan($shop)]['name']) ?>)</div><?php endif; ?>
  </div>
</div>

<div class="grid-2">
  <div class="col-stack">
    <section class="card">
      <div class="card-head"><h2 style="font-size:1.15rem">Jadual hari ini</h2><a class="link small" href="tempahan.php">Semua tempahan</a></div>
      <?php if ($todayList): ?>
        <div class="bk-list"><?php foreach ($todayList as $b) echo booking_row($shop, $b, false, 'index.php'); ?></div>
      <?php else: ?>
        <div class="empty" style="padding:1.5rem 1rem"><?= icon('calendar', 'ic-lg') ?>Tiada tempahan hari ini.</div>
        <?php if ($upcoming): ?>
          <h3 style="font-size:.95rem;margin:.5rem 0 .75rem">Akan datang</h3>
          <div class="bk-list"><?php foreach ($upcoming as $b) echo booking_row($shop, $b, true, 'index.php'); ?></div>
        <?php endif; ?>
      <?php endif; ?>
    </section>

    <section class="card">
      <div class="card-head"><h2 style="font-size:1.15rem">Tempahan 14 hari lepas</h2></div>
      <?= column_chart($points, fn($v) => (string)(int)$v, 'Bilangan tempahan sehari', fn($v) => (string)(int)$v) ?>
    </section>
  </div>

  <div class="col-stack">
    <?php if ($doneSteps < count($steps)): ?>
    <section class="card">
      <h2 style="font-size:1.15rem;margin-bottom:.2rem">Mula di sini</h2>
      <p class="muted small" style="margin:0"><?= $doneSteps ?> daripada <?= count($steps) ?> selesai</p>
      <div class="progress-line"><i style="width:<?= (int)round($doneSteps / count($steps) * 100) ?>%"></i></div>
      <ul class="checklist">
        <?php foreach ($steps as [$label, $href, $done]): ?>
          <li class="<?= $done ? 'done' : '' ?>"><a href="<?= e($href) ?>"><span class="tick"><?= icon('check', 'ic-sm') ?></span><?= e($label) ?><span class="arrow"><?= icon('arrow', 'ic-sm') ?></span></a></li>
        <?php endforeach; ?>
      </ul>
    </section>
    <?php endif; ?>

    <section class="card">
      <h2 style="font-size:1.15rem">Pautan tempahan anda</h2>
      <p class="muted small">Letak di bio Instagram, TikTok, Google Maps & status WhatsApp.</p>
      <div class="share-box">
        <code><?= e(preg_replace('#^https?://#', '', $link)) ?></code>
        <button type="button" class="btn btn-sm" data-copy="<?= e($link) ?>"><?= icon('copy', 'ic-sm') ?> Salin</button>
      </div>
      <div class="flex flex-wrap" style="margin-top:.75rem">
        <a class="btn btn-ghost btn-sm" href="<?= e($link) ?>" target="_blank" rel="noopener"><?= icon('external', 'ic-sm') ?> Buka</a>
        <a class="btn btn-ghost btn-sm" href="<?= e(wa_link('', 'Tempah slot di ' . $shop['name'] . ' secara online: ' . $link)) ?>" target="_blank" rel="noopener"><?= icon('chat', 'ic-sm') ?> Kongsi WhatsApp</a>
        <a class="btn btn-ghost btn-sm" href="kongsi.php"><?= icon('qr', 'ic-sm') ?> Poster QR</a>
      </div>
    </section>

    <section class="card">
      <h2 style="font-size:1.15rem">Servis paling laku bulan ini</h2>
      <?php if ($topServices): ?>
        <table class="table">
          <tbody>
          <?php foreach ($topServices as $i => $s): ?>
            <tr><td><b><?= $i + 1 ?>.</b> <?= e($s['name']) ?></td><td class="num muted"><?= (int)$s['n'] ?>×</td><td class="num"><b><?= e(rm((int)$s['sen'])) ?></b></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="muted small">Belum ada data bulan ini.</p>
      <?php endif; ?>
    </section>
  </div>
</div>
<?php
$footScripts = chart_script();
include ROOT . '/includes/app_foot.php';
