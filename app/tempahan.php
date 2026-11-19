<?php
require __DIR__ . '/_boot.php';
require ROOT . '/lib/booking_view.php';

$sid = (int)$shop['id'];

if (is_post()) {
    check_csrf();
    $back = (string)($_POST['back'] ?? '');
    $back = preg_match('/^(index|tempahan)\.php(\?[A-Za-z0-9_=&%.-]*)?$/', $back) ? $back : 'tempahan.php';

    if (($_POST['action'] ?? '') === 'walkin') {
        try {
            $b = create_booking(
                $shop, (array)($_POST['services'] ?? []), (int)($_POST['staff'] ?? 0),
                (string)($_POST['date'] ?? ''), substr((string)($_POST['time'] ?? ''), 0, 5),
                (string)($_POST['name'] ?? ''), (string)($_POST['phone'] ?? ''), (string)($_POST['note'] ?? ''),
                'walkin', 'disahkan'
            );
            flash('Walk-in ditambah: ' . $b['customer_name'] . ', ' . masa(date('H:i:s', strtotime($b['start_at']))) . ' (' . ($b['staff_name'] ?: '-') . ').');
            redirect('tempahan.php?d=' . substr($b['start_at'], 0, 10));
        } catch (BookingError $e) {
            flash($e->getMessage(), 'bad');
            redirect('tempahan.php?baru=1&d=' . rawurlencode((string)($_POST['date'] ?? '')));
        }
    }

    $to = (string)($_POST['to'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    if (isset(STATUS[$to])) {
        $b = row('SELECT * FROM bookings WHERE id = ? AND shop_id = ?', [$id, $sid]);
        if ($b) {
            // Kembali ke status yang memegang slot: pastikan slot masih kosong
            if (in_array($to, HOLDING, true) && !in_array($b['status'], HOLDING, true)) {
                $clash = val(
                    "SELECT 1 FROM bookings WHERE staff_id = ? AND id <> ? AND status IN ('menunggu','disahkan') AND start_at < ? AND end_at > ?",
                    [$b['staff_id'], $id, $b['end_at'], $b['start_at']]
                );
                if ($clash) {
                    flash('Tidak boleh: slot itu sudah diambil tempahan lain.', 'bad');
                    redirect($back);
                }
            }
            q('UPDATE bookings SET status = ? WHERE id = ? AND shop_id = ?', [$to, $id, $sid]);
            flash($b['customer_name'] . ': ' . STATUS[$to] . '.');
        }
    }
    redirect($back);
}

$view = (string)($_GET['v'] ?? 'hari');
$q = trim((string)($_GET['q'] ?? ''));
$statusF = (string)($_GET['status'] ?? '');
$d = (string)($_GET['d'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || !strtotime($d)) {
    $d = date('Y-m-d');
}
if ($statusF === 'menunggu') {
    $view = 'menunggu';
}

if ($q !== '') {
    $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
    $digits = preg_replace('/\D+/', '', $q);
    $list = shop_bookings($sid, 'b.customer_name LIKE ? OR b.code LIKE ?' . ($digits !== '' ? ' OR b.customer_phone LIKE ?' : ''),
        array_merge([$like, $like], $digits !== '' ? ['%' . ltrim($digits, '0') . '%'] : []), 'b.start_at DESC', 100);
    $heading = count($list) . ' hasil untuk "' . $q . '"';
    $view = 'cari';
} elseif ($view === 'akan') {
    $list = shop_bookings($sid, "b.start_at >= NOW() AND b.status IN ('menunggu','disahkan')", [], 'b.start_at', 200);
    $heading = 'Semua tempahan akan datang';
} elseif ($view === 'menunggu') {
    $list = shop_bookings($sid, "b.status = 'menunggu' AND b.start_at >= NOW()", [], 'b.start_at', 200);
    $heading = 'Menunggu pengesahan anda';
} else {
    $view = 'hari';
    $list = shop_bookings($sid, 'DATE(b.start_at) = ?', [$d]);
    $heading = tarikh($d);
}

$back = 'tempahan.php?' . http_build_query(array_filter(['v' => $view !== 'hari' && $view !== 'cari' ? $view : null, 'd' => $view === 'hari' ? $d : null, 'q' => $q ?: null]));
$services = active_services($sid);
$allStaff = rows('SELECT * FROM staff WHERE shop_id = ? AND active = 1 ORDER BY sort, id', [$sid]);
$dayTotal = array_sum(array_map(fn($b) => in_array($b['status'], ['disahkan', 'selesai'], true) ? (int)$b['total_sen'] : 0, $list));

$title = 'Tempahan';
$page = 'tempahan';
$topActions = '<button class="btn btn-sm" type="button" onclick="document.getElementById(\'walkin\').showModal()">' . icon('plus', 'ic-sm') . ' Walk-in</button>';
include ROOT . '/includes/app_head.php';
?>
<div class="toolbar">
  <nav class="seg" aria-label="Paparan">
    <a href="tempahan.php" class="<?= $view === 'hari' ? 'on' : '' ?>">Ikut hari</a>
    <a href="tempahan.php?v=akan" class="<?= $view === 'akan' ? 'on' : '' ?>">Akan datang</a>
    <a href="tempahan.php?v=menunggu" class="<?= $view === 'menunggu' ? 'on' : '' ?>">Menunggu<?= $pending ? ' (' . $pending . ')' : '' ?></a>
  </nav>
  <div class="grow"></div>
  <form method="get" class="flex" role="search">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari nama, telefon, kod" aria-label="Cari tempahan" style="min-width:210px;min-height:40px">
  </form>
</div>

<?php if ($view === 'hari'): ?>
  <div class="toolbar">
    <div class="day-nav">
      <a class="btn btn-ghost btn-icon" href="?d=<?= e(date('Y-m-d', strtotime("$d -1 day"))) ?>" aria-label="Hari sebelum"><?= icon('back', 'ic-sm') ?></a>
      <form method="get"><input type="date" name="d" value="<?= e($d) ?>" onchange="this.form.submit()" aria-label="Pilih tarikh"></form>
      <a class="btn btn-ghost btn-icon" href="?d=<?= e(date('Y-m-d', strtotime("$d +1 day"))) ?>" aria-label="Hari seterusnya"><?= icon('arrow', 'ic-sm') ?></a>
      <?php if ($d !== date('Y-m-d')): ?><a class="btn btn-ghost btn-sm" href="tempahan.php">Hari ini</a><?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<section class="card">
  <div class="card-head">
    <h2 style="font-size:1.15rem"><?= e($heading) ?></h2>
    <?php if ($view === 'hari' && $list): ?><span class="muted small"><?= count(array_filter($list, fn($b) => $b['status'] !== 'batal')) ?> tempahan · anggaran <?= e(rm($dayTotal)) ?></span><?php endif; ?>
  </div>
  <?php if ($list): ?>
    <div class="bk-list"><?php foreach ($list as $b) echo booking_row($shop, $b, $view !== 'hari', $back); ?></div>
  <?php else: ?>
    <div class="empty"><?= icon('calendar', 'ic-lg') ?><?= $view === 'menunggu' ? 'Tiada tempahan menunggu. Semua beres!' : 'Tiada tempahan.' ?></div>
  <?php endif; ?>
</section>

<dialog class="modal" id="walkin">
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="walkin">
    <div class="modal-head"><h3>Tambah walk-in / tempahan telefon</h3><button type="button" class="btn btn-ghost btn-icon" onclick="this.closest('dialog').close()" aria-label="Tutup"><?= icon('x', 'ic-sm') ?></button></div>
    <div class="modal-body">
      <div class="field">
        <span class="label">Servis</span>
        <div style="display:grid;gap:.4rem;max-height:180px;overflow:auto">
          <?php foreach ($services as $s): ?>
            <label class="check"><input type="checkbox" name="services[]" value="<?= (int)$s['id'] ?>"> <?= e($s['name']) ?> <span class="muted small">· <?= (int)$s['duration_min'] ?> min · <?= e(rm((int)$s['price_sen'])) ?></span></label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="row-2">
        <div class="field"><label for="w-date">Tarikh</label><input id="w-date" type="date" name="date" value="<?= e($d) ?>" required></div>
        <div class="field"><label for="w-time">Masa mula</label><input id="w-time" type="time" name="time" value="<?= e(date('H:i', (int)(ceil(time() / 300) * 300))) ?>" step="300" required></div>
      </div>
      <div class="field">
        <label for="w-staff">Staf</label>
        <select id="w-staff" name="staff"><option value="0">Sesiapa yang lapang</option>
          <?php foreach ($allStaff as $st): ?><option value="<?= (int)$st['id'] ?>"><?= e($st['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="row-2">
        <div class="field"><label for="w-name">Nama pelanggan</label><input id="w-name" name="name" required maxlength="60" value="Walk-in"></div>
        <div class="field"><label for="w-phone">Telefon <span class="muted">(pilihan)</span></label><input id="w-phone" name="phone" type="tel" maxlength="20"></div>
      </div>
      <div class="field"><label for="w-note">Nota</label><input id="w-note" name="note" maxlength="200"></div>
      <p class="hint" style="margin:0">Walk-in tidak dikira dalam had tempahan online pelan anda.</p>
    </div>
    <div class="modal-foot"><button type="button" class="btn btn-ghost" onclick="this.closest('dialog').close()">Batal</button><button class="btn" type="submit">Simpan</button></div>
  </form>
</dialog>
<?php if (isset($_GET['baru'])): ?>
<script>document.addEventListener('DOMContentLoaded', function () { document.getElementById('walkin').showModal(); });</script>
<?php endif; ?>
<?php include ROOT . '/includes/app_foot.php';
