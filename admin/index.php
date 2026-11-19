<?php
require __DIR__ . '/_boot.php';
require ROOT . '/lib/chart.php';

$today = date('Y-m-d');
$shops = rows('SELECT s.*, (SELECT u.phone FROM users u WHERE u.shop_id = s.id LIMIT 1) AS owner_phone, (SELECT u.name FROM users u WHERE u.shop_id = s.id LIMIT 1) AS owner_name FROM shops s');
$paid = $trial = $free = $susp = 0;
$mrr = 0;
$expiring = $trialEnding = [];
foreach ($shops as $s) {
    if ($s['slug'] === 'demo') {
        continue; // kedai demo bukan pelanggan sebenar
    }
    if ($s['status'] !== 'aktif') {
        $susp++;
        continue;
    }
    $eff = shop_plan($s);
    if ($eff !== 'percuma' && !on_trial($s)) {
        $paid++;
        $mrr += PLANS[$eff]['price'];
        if (days_left($s['paid_until']) <= 14) {
            $expiring[] = $s;
        }
    } elseif (on_trial($s)) {
        $trial++;
        if (days_left($s['trial_until']) <= 3) {
            $trialEnding[] = $s;
        }
    } else {
        $free++;
    }
}
// Kedai percuma yang hampir/capai had tempahan = calon naik taraf paling panas
$nearLimit = rows(
    "SELECT * FROM (
       SELECT s.*, (SELECT u.phone FROM users u WHERE u.shop_id = s.id LIMIT 1) AS owner_phone, (SELECT u.name FROM users u WHERE u.shop_id = s.id LIMIT 1) AS owner_name,
              (SELECT COUNT(*) FROM bookings b WHERE b.shop_id = s.id AND b.source = 'online' AND b.status <> 'batal' AND b.created_at >= ?) AS n
       FROM shops s WHERE s.status = 'aktif' AND s.slug <> 'demo'
     ) x WHERE n >= ? ORDER BY n DESC LIMIT 10",
    [date('Y-m-01'), (int)(PLANS['percuma']['bookings'] * 0.7)]
);
$nearLimit = array_values(array_filter($nearLimit, fn($s) => shop_plan($s) === 'percuma'));

$collectedMonth = (int)val("SELECT COALESCE(SUM(amount_sen),0) FROM payments WHERE status = 'paid' AND paid_at >= ?", [date('Y-m-01')]);
$collectedAll = (int)val("SELECT COALESCE(SUM(amount_sen),0) FROM payments WHERE status = 'paid'");
$new30 = (int)val("SELECT COUNT(*) FROM shops WHERE slug <> 'demo' AND created_at >= ?", [date('Y-m-d', strtotime('-30 days'))]);
$bookings30 = (int)val("SELECT COUNT(*) FROM bookings b JOIN shops s ON s.id = b.shop_id WHERE b.created_at >= ? AND b.source = 'online' AND s.slug <> 'demo'", [date('Y-m-d', strtotime('-30 days'))]);
$pendingPay = (int)val("SELECT COUNT(*) FROM payments WHERE method = 'manual' AND status = 'pending'");

$byMonth = [];
foreach (rows("SELECT DATE_FORMAT(paid_at, '%Y-%m') m, SUM(amount_sen) s FROM payments WHERE status = 'paid' AND paid_at >= ? GROUP BY DATE_FORMAT(paid_at, '%Y-%m')", [date('Y-m-01', strtotime('-11 months'))]) as $r) {
    $byMonth[$r['m']] = (int)$r['s'];
}
$points = [];
for ($i = 11; $i >= 0; $i--) {
    $t = strtotime(date('Y-m-01') . " -$i months");
    $points[] = ['label' => BULAN[(int)date('n', $t) - 1], 'tip' => BULAN_PENUH[(int)date('n', $t) - 1] . ' ' . date('Y', $t), 'value' => $byMonth[date('Y-m', $t)] ?? 0];
}

function follow_row(array $s, string $when, string $msg): string
{
    $wa = $s['owner_phone'] ? '<a class="btn btn-ghost btn-sm" href="' . e(wa_link($s['owner_phone'], $msg)) . '" target="_blank" rel="noopener">' . icon('chat', 'ic-sm') . ' WhatsApp</a>' : '';
    return '<tr><td><b>' . e($s['name']) . '</b><br><small class="muted">' . e($s['owner_name'] ?? '') . ' · ' . e(show_phone($s['owner_phone'] ?? '')) . '</small></td><td class="small">' . $when . '</td><td class="num">' . $wa . '</td></tr>';
}

$title = 'Ringkasan platform';
$page = 'index';
include ROOT . '/includes/app_head.php';
?>
<?php if ($pendingPay): ?>
  <div class="alert alert-warn flex between flex-wrap"><span><?= $pendingPay ?> resit pindahan bank menunggu pengesahan anda.</span><a class="btn btn-sm" href="bayaran.php">Semak</a></div>
<?php endif; ?>

<div class="kpis">
  <div class="kpi"><div class="k-label"><?= icon('coins', 'ic-sm') ?> MRR (anggaran)</div><div class="k-val"><?= e(rm($mrr)) ?></div><div class="k-sub">pendapatan berulang sebulan</div></div>
  <div class="kpi"><div class="k-label"><?= icon('card', 'ic-sm') ?> Kutipan <?= e(BULAN_PENUH[(int)date('n') - 1]) ?></div><div class="k-val"><?= e(rm($collectedMonth)) ?></div><div class="k-sub">jumlah keseluruhan <?= e(rm($collectedAll)) ?></div></div>
  <div class="kpi"><div class="k-label"><?= icon('store', 'ic-sm') ?> Kedai berbayar</div><div class="k-val"><?= $paid ?></div><div class="k-sub"><?= $trial ?> percubaan · <?= $free ?> percuma<?= $susp ? ' · ' . $susp . ' digantung' : '' ?></div></div>
  <div class="kpi"><div class="k-label"><?= icon('zap', 'ic-sm') ?> 30 hari lepas</div><div class="k-val"><?= $new30 ?></div><div class="k-sub">kedai baru · <?= $bookings30 ?> tempahan online</div></div>
</div>

<div class="grid-2">
  <section class="card">
    <div class="card-head"><h2 style="font-size:1.15rem">Kutipan 12 bulan</h2></div>
    <?= column_chart($points, fn($v) => rm((int)$v), 'Kutipan bayaran sebulan', fn($v) => $v >= 100000 ? 'RM' . round($v / 100000, 1) . 'k' : rm((int)$v)) ?>
  </section>

  <div class="col-stack">
    <section class="card">
      <h2 style="font-size:1.15rem;margin-bottom:.2rem">Susulan: duit di depan mata</h2>
      <p class="muted small">Hubungi pemilik ini. Mesej WhatsApp sudah siap ditulis.</p>
      <?php if (!$trialEnding && !$expiring && !$nearLimit): ?>
        <p class="muted small" style="margin:0">Tiada susulan buat masa ini.</p>
      <?php else: ?>
        <table class="table"><tbody>
        <?php foreach ($trialEnding as $s) echo follow_row($s, 'Percubaan tamat ' . (days_left($s['trial_until']) === 0 ? 'hari ini' : 'dalam ' . days_left($s['trial_until']) . ' hari'),
            "Salam {$s['owner_name']}, saya dari " . cfg('app_name') . ". Percubaan Pro untuk {$s['name']} tamat tidak lama lagi. Kalau nak kekalkan Telegram & tempahan lebih, boleh naik taraf di sini: " . url('app/langganan.php') . ' 😊'); ?>
        <?php foreach ($expiring as $s) echo follow_row($s, 'Pelan ' . PLANS[$s['plan']]['name'] . ' tamat ' . e(tarikh($s['paid_until'], false)),
            "Salam {$s['owner_name']}, pelan " . PLANS[$s['plan']]['name'] . " {$s['name']} akan tamat pada " . tarikh($s['paid_until'], false) . '. Sambung di sini supaya tempahan tak terganggu: ' . url('app/langganan.php')); ?>
        <?php foreach ($nearLimit as $s) echo follow_row($s, '<span class="badge badge-warn">' . (int)$s['n'] . '/' . PLANS['percuma']['bookings'] . ' tempahan</span>',
            "Salam {$s['owner_name']}, tahniah! {$s['name']} dah dapat {$s['n']} tempahan online bulan ini. Pelan Percuma had " . PLANS['percuma']['bookings'] . ' sebulan, jadi naik taraf ke Asas (' . rm(PLANS['asas']['price']) . '/bln) supaya pelanggan tak tertolak: ' . url('app/langganan.php')); ?>
        </tbody></table>
      <?php endif; ?>
    </section>
  </div>
</div>
<?php
$footScripts = chart_script();
include ROOT . '/includes/app_foot.php';
