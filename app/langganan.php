<?php
require __DIR__ . '/_boot.php';
require ROOT . '/lib/payment.php';
$sid = (int)$shop['id'];
$me = current_user();

if (is_post()) {
    check_csrf();
    $plan = (string)($_POST['plan'] ?? '');
    $months = (int)($_POST['months'] ?? 0);
    $method = (string)($_POST['method'] ?? '');
    if (!isset(PLANS[$plan]) || $plan === 'percuma' || !isset(PERIODS[$months]) || !in_array($method, ['fpx', 'manual'], true)) {
        flash('Pilihan tidak sah.', 'bad');
        redirect('langganan.php');
    }
    // Elak bil bertimbun: had 5 bil belum bayar dalam sejam
    if ((int)val("SELECT COUNT(*) FROM payments WHERE shop_id = ? AND status = 'pending' AND created_at > ?", [$sid, date('Y-m-d H:i:s', time() - 3600)]) >= 5) {
        flash('Terlalu banyak bil belum dibayar. Cuba sebentar lagi.', 'bad');
        redirect('langganan.php');
    }
    $amount = plan_price($plan, $months);
    $orderId = 'SK' . $sid . '-' . date('ymdHis') . '-' . strtolower(random_code(4));

    if ($method === 'manual') {
        try {
            $file = save_upload($_FILES['receipt'] ?? [], 'resit-' . $sid, 5_000_000, 'storage/receipts'); // folder tertutup, bukan awam
        } catch (RuntimeException $ex) {
            flash('Resit: ' . $ex->getMessage(), 'bad');
            redirect('langganan.php?cara=manual');
        }
        q("INSERT INTO payments (shop_id, order_id, plan, months, amount_sen, method, receipt_file, note) VALUES (?,?,?,?,?, 'manual', ?, ?)",
            [$sid, $orderId, $plan, $months, $amount, $file, mb_substr(trim((string)($_POST['note'] ?? '')), 0, 200) ?: null]);
        require_once ROOT . '/lib/telegram.php';
        tg_admin("🧾 Resit baru perlu disemak: {$shop['name']} — " . rm($amount) . " (" . PLANS[$plan]['name'] . ", $months bln)\n" . url('admin/bayaran.php'));
        flash('Resit dihantar. Kami akan sahkan dalam masa 24 jam dan langganan anda akan aktif secara automatik.');
        redirect('langganan.php');
    }

    q("INSERT INTO payments (shop_id, order_id, plan, months, amount_sen, method) VALUES (?,?,?,?,?, ?)",
        [$sid, $orderId, $plan, $months, $amount, pay_driver()]);
    $pid = (int)db()->lastInsertId();
    try {
        $bill = pay_create_bill(row('SELECT * FROM payments WHERE id = ?', [$pid]), $shop, $me);
    } catch (RuntimeException $ex) {
        q("UPDATE payments SET status = 'failed', note = ? WHERE id = ?", [mb_substr($ex->getMessage(), 0, 200), $pid]);
        flash($ex->getMessage() . ' Anda juga boleh guna pindahan bank manual.', 'bad');
        redirect('langganan.php');
    }
    q('UPDATE payments SET bill_code = ? WHERE id = ?', [$bill['bill_code'], $pid]);
    redirect($bill['url']);
}

$eff = shop_plan($shop);
$trial = on_trial($shop);
$payments = rows('SELECT * FROM payments WHERE shop_id = ? ORDER BY id DESC LIMIT 30', [$sid]);
$pendingManual = array_filter($payments, fn($p) => $p['method'] === 'manual' && $p['status'] === 'pending');
$defaultPlan = $shop['plan'] !== 'percuma' ? $shop['plan'] : 'asas';

$title = 'Langganan';
$page = 'langganan';
include ROOT . '/includes/app_head.php';
?>
<section class="card">
  <div class="flex between flex-wrap">
    <div>
      <p class="muted small" style="margin:0">Pelan sekarang</p>
      <h2 style="margin:.1rem 0 .2rem"><?= e(PLANS[$eff]['name']) ?> <?php if ($trial): ?><span class="badge badge-brand">Percubaan</span><?php endif; ?></h2>
      <p class="muted small" style="margin:0">
        <?php if ($trial): ?>Percubaan Pro tamat <?= e(tarikh($shop['trial_until'], false)) ?>. Selepas itu kedai anda terus jalan dengan pelan Percuma.<br>Bayar sekarang? Baki <?= days_left($shop['trial_until']) ?> hari percubaan akan ditambah ke tempoh anda.
        <?php elseif ($eff !== 'percuma'): ?>Aktif hingga <b><?= e(tarikh($shop['paid_until'], false)) ?></b> (<?= days_left($shop['paid_until']) ?> hari lagi). Bayaran baru untuk pelan yang sama akan disambung selepas tarikh ini.
        <?php else: ?><?= PLANS['percuma']['bookings'] ?> tempahan online sebulan · <?= PLANS['percuma']['staff'] ?> staf.<?php endif; ?>
      </p>
    </div>
    <?php if ($eff !== 'percuma'): $q = booking_quota_left($shop); ?>
      <div class="kpi" style="min-width:220px"><div class="k-label">Tempahan online bulan ini</div><div class="k-val"><?= month_online_bookings($sid) ?></div><div class="k-sub"><?= $q === null ? 'tanpa had' : $q . ' baki' ?></div></div>
    <?php endif; ?>
  </div>
</section>

<?php if ($pendingManual): ?>
  <div class="alert alert-info" style="margin-top:1rem"><?= icon('clock', 'ic-sm') ?> Resit pindahan anda sedang disemak. Langganan akan aktif sebaik sahaja disahkan.</div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="card" id="pay-form">
  <?= csrf_field() ?>
  <h2 style="font-size:1.15rem">Pilih pelan</h2>
  <div class="plan-cards">
    <?php foreach (PLANS as $key => $p): if ($key === 'percuma') continue; ?>
      <label class="plan-card">
        <input type="radio" name="plan" value="<?= e($key) ?>" data-price="<?= (int)$p['price'] ?>"<?= $key === $defaultPlan ? ' checked' : '' ?>>
        <?php if ($eff === $key && !$trial): ?><span class="badge badge-ok now">Pelan anda</span><?php endif; ?>
        <b style="font-size:1.1rem"><?= e($p['name']) ?></b>
        <span class="p-amt"><?= e(rm($p['price'])) ?><small class="muted" style="font-size:.85rem;font-weight:600">/bulan</small></span>
        <span class="small muted"><?= $p['bookings'] === null ? 'Tempahan online tanpa had' : $p['bookings'] . ' tempahan online sebulan' ?> · <?= $p['staff'] ?> staf</span>
        <span class="small muted">Telegram · warna & gambar<?= $p['white_label'] ? ' · tanpa label ' . e(cfg('app_name')) : '' ?></span>
      </label>
    <?php endforeach; ?>
  </div>

  <h3 style="margin-top:1.5rem">Tempoh</h3>
  <div class="period-opts">
    <?php foreach (PERIODS as $m => $charged): ?>
      <label><input type="radio" name="months" value="<?= $m ?>" data-charged="<?= $charged ?>"<?= $m === 1 ? ' checked' : '' ?>><?= $m ?> bulan<small><?= $m === 12 ? '2 bulan percuma' : ($m === 3 ? 'tiada diskaun' : 'fleksibel') ?></small></label>
    <?php endforeach; ?>
  </div>

  <div class="pay-total"><span class="muted">Jumlah</span><b id="pay-total">RM0</b></div>

  <h3>Cara bayar</h3>
  <div class="period-opts">
    <label><input type="radio" name="method" value="fpx" checked>FPX online banking<small>Aktif serta-merta</small></label>
    <label><input type="radio" name="method" value="manual"<?= isset($_GET['cara']) ? ' checked' : '' ?>>Pindahan bank<small>Disahkan dalam 24 jam</small></label>
  </div>

  <div id="manual-box" style="margin-top:1rem" hidden>
    <div class="alert alert-info" style="font-weight:500">
      Pindahkan <b id="manual-amt">RM0</b> ke:<br>
      <b><?= e(cfg('bank.name')) ?> · <?= e(cfg('bank.number')) ?></b><br>
      <?= e(cfg('bank.holder')) ?><br>
      Rujukan: <b><?= e($shop['slug']) ?></b>
    </div>
    <div class="field"><label for="receipt">Muat naik resit (gambar)</label><input id="receipt" type="file" name="receipt" accept="image/jpeg,image/png,image/webp"></div>
    <div class="field"><label for="note">Nota <span class="muted">(pilihan)</span></label><input id="note" name="note" maxlength="200" placeholder="Cth: dibayar dari akaun isteri"></div>
  </div>

  <button class="btn btn-brand btn-lg" type="submit" id="pay-btn" style="margin-top:.5rem"><?= icon('lock', 'ic-sm') ?> <span>Bayar dengan FPX</span></button>
  <?php if (pay_driver() === 'mock'): ?><p class="hint" style="margin-top:.6rem">Mod ujian: bayaran FPX adalah tiruan (tiada duit sebenar). Tukar <code>payment_driver</code> ke <code>toyyibpay</code> dalam config.php untuk bayaran sebenar.</p><?php endif; ?>
  <p class="hint" style="margin-top:.6rem">Tiada auto-debit. Anda akan diingatkan 7 hari sebelum pelan tamat.</p>
</form>

<section class="card">
  <h2 style="font-size:1.15rem">Sejarah bayaran</h2>
  <?php if ($payments): ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Tarikh</th><th>Pelan</th><th>Kaedah</th><th class="num">Jumlah</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($payments as $p): ?>
        <tr>
          <td class="nowrap"><?= e(tarikh(substr($p['created_at'], 0, 10), false)) ?></td>
          <td><?= e(PLANS[$p['plan']]['name'] ?? $p['plan']) ?> · <?= (int)$p['months'] ?> bln<?php if ($p['period_to']): ?><br><small class="muted">hingga <?= e(tarikh($p['period_to'], false)) ?></small><?php endif; ?></td>
          <td><?= $p['method'] === 'manual' ? 'Pindahan bank' : 'FPX' ?></td>
          <td class="num"><?= e(rm((int)$p['amount_sen'])) ?></td>
          <td><?= ['paid' => '<span class="badge badge-ok">Dibayar</span>', 'pending' => '<span class="badge badge-warn">' . ($p['method'] === 'manual' ? 'Disemak' : 'Belum bayar') . '</span>', 'failed' => '<span class="badge badge-bad">Gagal</span>', 'rejected' => '<span class="badge badge-bad">Ditolak</span>'][$p['status']] ?? e($p['status']) ?>
            <?php if ($p['status'] === 'rejected' && $p['note']): ?><br><small class="muted"><?= e($p['note']) ?></small><?php endif; ?></td>
          <td class="num"><?php if ($p['status'] === 'paid'): ?><a class="link small" href="resit.php?id=<?= (int)$p['id'] ?>" target="_blank">Resit</a><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php else: ?>
    <p class="muted small">Belum ada bayaran.</p>
  <?php endif; ?>
</section>
<?php
$footScripts = <<<'JS'
<script>
(function () {
  var f = document.getElementById('pay-form');
  function rm(sen) { return 'RM' + (sen / 100).toLocaleString('en-MY', { minimumFractionDigits: sen % 100 ? 2 : 0 }); }
  function sync() {
    var plan = f.querySelector('[name=plan]:checked'), m = f.querySelector('[name=months]:checked');
    var method = f.querySelector('[name=method]:checked').value;
    var total = (+plan.dataset.price) * (+m.dataset.charged);
    document.getElementById('pay-total').textContent = rm(total);
    document.getElementById('manual-amt').textContent = rm(total);
    document.getElementById('manual-box').hidden = method !== 'manual';
    document.getElementById('receipt').required = method === 'manual';
    document.querySelector('#pay-btn span').textContent = method === 'manual' ? 'Hantar resit' : 'Bayar ' + rm(total) + ' dengan FPX';
  }
  f.addEventListener('change', sync);
  sync();
})();
</script>
JS;
include ROOT . '/includes/app_foot.php';
