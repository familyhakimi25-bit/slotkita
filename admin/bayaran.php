<?php
require __DIR__ . '/_boot.php';

if (is_post()) {
    check_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $p = row("SELECT p.*, s.name AS shop_name FROM payments p JOIN shops s ON s.id = p.shop_id WHERE p.id = ? AND p.method = 'manual' AND p.status = 'pending'", [$id]);
    if ($p && ($_POST['action'] ?? '') === 'approve') {
        apply_payment($id, 'MANUAL-' . date('ymd'));
        $to = val('SELECT period_to FROM payments WHERE id = ?', [$id]);
        flash("Diluluskan: {$p['shop_name']} aktif hingga " . tarikh((string)$to, false) . '.');
    } elseif ($p && ($_POST['action'] ?? '') === 'reject') {
        $reason = mb_substr(trim((string)($_POST['reason'] ?? '')), 0, 200) ?: 'Resit tidak sah atau jumlah tidak sepadan.';
        q("UPDATE payments SET status = 'rejected', note = ? WHERE id = ?", [$reason, $id]);
        flash("Ditolak: {$p['shop_name']}.", 'warn');
    }
    redirect('bayaran.php');
}

$pending = rows("SELECT p.*, s.name AS shop_name, s.slug FROM payments p JOIN shops s ON s.id = p.shop_id WHERE p.method = 'manual' AND p.status = 'pending' ORDER BY p.id");
$all = rows("SELECT p.*, s.name AS shop_name FROM payments p JOIN shops s ON s.id = p.shop_id ORDER BY p.id DESC LIMIT 200");

$title = 'Bayaran';
$page = 'bayaran';
include ROOT . '/includes/app_head.php';
?>
<section class="card">
  <h2 style="font-size:1.15rem">Pindahan bank menunggu pengesahan</h2>
  <?php if ($pending): ?>
    <p class="muted small">Semak dalam aplikasi bank anda bahawa duit sudah masuk <b>sebelum</b> meluluskan.</p>
    <div class="item-list">
    <?php foreach ($pending as $p): ?>
      <div class="item" style="align-items:flex-start;flex-wrap:wrap">
        <a href="resit-gambar.php?id=<?= (int)$p['id'] ?>" target="_blank" rel="noopener" title="Buka resit penuh">
          <img src="resit-gambar.php?id=<?= (int)$p['id'] ?>" alt="Resit dari <?= e($p['shop_name']) ?>" style="width:84px;height:110px;object-fit:cover;border-radius:10px;border:1px solid var(--line)">
        </a>
        <div class="grow">
          <b><?= e($p['shop_name']) ?></b>
          <small><?= e(PLANS[$p['plan']]['name']) ?> · <?= (int)$p['months'] ?> bulan · rujukan <b><?= e($p['slug']) ?></b> · <?= e(tarikh(substr($p['created_at'], 0, 10), false)) ?> <?= e(substr($p['created_at'], 11, 5)) ?></small>
          <?php if ($p['note']): ?><small>Nota: <?= e($p['note']) ?></small><?php endif; ?>
          <div class="p-amt" style="font-size:1.4rem;font-weight:800;margin-top:.3rem"><?= e(rm((int)$p['amount_sen'])) ?></div>
        </div>
        <div style="display:grid;gap:.4rem;min-width:220px">
          <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn btn-ok btn-sm btn-block" name="action" value="approve"><?= icon('check', 'ic-sm') ?> Luluskan & aktifkan</button></form>
          <form method="post" class="flex" data-confirm="Tolak bayaran ini?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input name="reason" placeholder="Sebab tolak" style="min-height:36px;font-size:14px"><button class="btn btn-ghost btn-sm" name="action" value="reject">Tolak</button></form>
        </div>
      </div>
    <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p class="muted small" style="margin:0">Tiada. Semua beres.</p>
  <?php endif; ?>
</section>

<section class="card">
  <h2 style="font-size:1.15rem">Semua bayaran</h2>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Tarikh</th><th>Kedai</th><th>Pelan</th><th>Kaedah</th><th class="num">Jumlah</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($all as $p): ?>
      <tr>
        <td class="nowrap small"><?= e(tarikh(substr($p['created_at'], 0, 10), false)) ?></td>
        <td><?= e($p['shop_name']) ?><br><small class="muted"><?= e($p['order_id']) ?><?= $p['ref_no'] ? ' · ' . e($p['ref_no']) : '' ?></small></td>
        <td class="small"><?= e(PLANS[$p['plan']]['name'] ?? $p['plan']) ?> · <?= (int)$p['months'] ?> bln</td>
        <td class="small"><?= e(['manual' => 'Pindahan', 'toyyibpay' => 'FPX', 'mock' => 'FPX (ujian)'][$p['method']] ?? $p['method']) ?></td>
        <td class="num"><?= e(rm((int)$p['amount_sen'])) ?></td>
        <td><?= ['paid' => '<span class="badge badge-ok">Dibayar</span>', 'pending' => '<span class="badge badge-warn">Belum</span>', 'failed' => '<span class="badge badge-bad">Gagal</span>', 'rejected' => '<span class="badge badge-bad">Ditolak</span>'][$p['status']] ?? e($p['status']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$all): ?><tr><td colspan="6" class="muted">Belum ada bayaran.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</section>
<?php include ROOT . '/includes/app_foot.php';
