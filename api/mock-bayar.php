<?php
// Halaman bayaran TIRUAN untuk ujian di komputer sendiri. Tiada kesan bila payment_driver = toyyibpay.
require __DIR__ . '/../bootstrap.php';
require ROOT . '/lib/payment.php';

if (pay_driver() !== 'mock') {
    http_response_code(404);
    exit('Tidak dijumpai');
}
$bill = (string)($_GET['bill'] ?? $_POST['bill'] ?? '');
$file = ROOT . '/storage/mock_bills/' . basename($bill) . '.json';
if (!preg_match('/^MK[a-z0-9]{8}$/', $bill) || !is_file($file)) {
    http_response_code(404);
    exit('Bil tidak dijumpai');
}
$m = json_decode((string)file_get_contents($file), true);

if (is_post()) {
    check_csrf();
    $ok = ($_POST['result'] ?? '') === 'pay';
    $m['status'] = $ok ? 'paid' : 'failed';
    $m['ref'] = $ok ? 'TP' . date('ymd') . random_int(100000, 999999) : '';
    file_put_contents($file, json_encode($m));
    // Pelayan dev PHP satu-thread: panggil pemproses terus (setara dengan callback ToyyibPay)
    process_bill($bill);
    redirect(url('api/bayar-kembali.php?' . http_build_query(['status_id' => $ok ? 1 : 3, 'billcode' => $bill, 'order_id' => $m['order_id']])));
}
?><!doctype html>
<html lang="ms"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Bayaran ujian</title>
<link rel="stylesheet" href="<?= e(url('assets/css/base.css')) ?>">
<style>body{display:grid;place-items:center;min-height:100vh;background:#eef1f5;padding:16px}.box{width:min(420px,100%);background:#fff;border-radius:18px;box-shadow:0 20px 50px -25px rgba(0,0,0,.35);overflow:hidden}.hd{background:#1f4fb5;color:#fff;padding:1rem 1.25rem;font-weight:800}.bd{padding:1.25rem}.amt{font-size:2rem;font-weight:800;margin:.2rem 0 1rem}</style>
</head><body>
<div class="box">
  <div class="hd">FPX · MOD UJIAN</div>
  <div class="bd">
    <div class="alert alert-warn">Ini halaman tiruan. Tiada duit sebenar berpindah.</div>
    <div class="muted small"><?= e($m['name']) ?> · <?= e($m['order_id']) ?></div>
    <div class="amt"><?= e(rm((int)$m['amount_sen'])) ?></div>
    <div class="field"><label for="bank">Bank</label><select id="bank"><option>Maybank2u</option><option>CIMB Clicks</option><option>Bank Islam</option><option>RHB Now</option></select></div>
    <?php if ($m['status'] !== 'pending'): ?><div class="alert alert-info">Bil ini sudah <?= e($m['status']) ?>.</div><?php endif; ?>
    <form method="post" class="flex">
      <?= csrf_field() ?><input type="hidden" name="bill" value="<?= e($bill) ?>">
      <button class="btn btn-ok grow" name="result" value="pay">Bayar (berjaya)</button>
      <button class="btn btn-ghost" name="result" value="fail">Gagal / batal</button>
    </form>
  </div>
</div>
</body></html>
