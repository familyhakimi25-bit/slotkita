<?php
require __DIR__ . '/_boot.php';
$p = row("SELECT * FROM payments WHERE id = ? AND shop_id = ? AND status = 'paid'", [(int)($_GET['id'] ?? 0), $shop['id']]);
if (!$p) {
    http_response_code(404);
    exit('Resit tidak dijumpai');
}
$me = current_user();
?><!doctype html>
<html lang="ms"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Resit <?= e($p['order_id']) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/css/base.css')) ?>">
<style>body{background:#fff;padding:2rem 16px}.r{max-width:640px;margin:0 auto}.r table{width:100%;border-collapse:collapse;margin:1.5rem 0}.r td,.r th{padding:.6rem 0;border-bottom:1px solid var(--line);text-align:left}.r .num{text-align:right}@media print{.noprint{display:none}}</style>
</head><body>
<div class="r">
  <div class="flex between"><span class="logo-mark"><span class="dot"><?= icon('calendar') ?></span><?= e(cfg('app_name')) ?></span><button class="btn btn-sm noprint" onclick="window.print()"><?= icon('printer', 'ic-sm') ?> Cetak</button></div>
  <h1 style="margin-top:2rem;font-size:1.6rem">Resit bayaran</h1>
  <p class="muted">No. <?= e($p['order_id']) ?><?= $p['ref_no'] ? ' · Rujukan ' . e($p['ref_no']) : '' ?><br>Tarikh bayaran: <?= e(tarikh(substr((string)$p['paid_at'], 0, 10), false)) ?></p>
  <p><b>Kepada:</b> <?= e($shop['name']) ?><br><?= e($me['name']) ?> · <?= e($me['email']) ?></p>
  <table>
    <tr><th>Perkara</th><th class="num">Jumlah</th></tr>
    <tr><td>Langganan <?= e(cfg('app_name')) ?> pelan <?= e(PLANS[$p['plan']]['name']) ?>, <?= (int)$p['months'] ?> bulan<br><small class="muted"><?= e(tarikh($p['period_from'], false)) ?> – <?= e(tarikh($p['period_to'], false)) ?></small></td><td class="num"><?= e(rm((int)$p['amount_sen'])) ?></td></tr>
    <tr><th>Jumlah dibayar</th><th class="num"><?= e(rm((int)$p['amount_sen'])) ?></th></tr>
  </table>
  <p class="small muted">Kaedah: <?= $p['method'] === 'manual' ? 'Pindahan bank' : 'FPX (ToyyibPay)' ?>. Resit ini dijana oleh komputer dan tidak memerlukan tandatangan.</p>
</div>
</body></html>
