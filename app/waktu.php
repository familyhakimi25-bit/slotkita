<?php
require __DIR__ . '/_boot.php';
$sid = (int)$shop['id'];

if (is_post()) {
    check_csrf();
    $act = (string)($_POST['action'] ?? '');
    if ($act === 'hours') {
        $ok = true;
        foreach (range(0, 6) as $w) {
            $open = !empty($_POST['open'][$w]);
            $o = (string)($_POST['from'][$w] ?? '');
            $c = (string)($_POST['to'][$w] ?? '');
            if ($open && (!preg_match('/^\d{2}:\d{2}$/', $o) || !preg_match('/^\d{2}:\d{2}$/', $c) || $o >= $c)) {
                flash(HARI[$w] . ': masa tutup mesti selepas masa buka.', 'bad');
                $ok = false;
                continue;
            }
            q('REPLACE INTO hours (shop_id, weekday, open_time, close_time) VALUES (?,?,?,?)', [$sid, $w, $open ? "$o:00" : null, $open ? "$c:00" : null]);
        }
        $step = (int)($_POST['slot_step'] ?? 30);
        $lead = (int)($_POST['lead_minutes'] ?? 60);
        $maxd = (int)($_POST['max_days'] ?? 21);
        $cancel = (int)($_POST['cancel_hours'] ?? 3);
        q('UPDATE shops SET slot_step = ?, lead_minutes = ?, max_days = ?, cancel_hours = ?, auto_confirm = ? WHERE id = ?', [
            in_array($step, [10, 15, 20, 30, 45, 60], true) ? $step : 30,
            max(0, min(2880, $lead)),
            max(1, min(90, $maxd)),
            max(0, min(72, $cancel)),
            empty($_POST['auto_confirm']) ? 0 : 1,
            $sid,
        ]);
        if ($ok) {
            flash('Waktu & tetapan tempahan disimpan.');
        }
    } elseif ($act === 'add_closure') {
        $from = (string)($_POST['from_day'] ?? '');
        $to = (string)($_POST['to_day'] ?? '') ?: $from;
        $note = mb_substr(trim((string)($_POST['note'] ?? '')), 0, 80);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) || $to < $from) {
            flash('Tarikh cuti tidak sah.', 'bad');
        } elseif ((strtotime($to) - strtotime($from)) / 86400 > 60) {
            flash('Maksimum 60 hari sekali tambah.', 'bad');
        } else {
            $n = 0;
            for ($d = $from; $d <= $to; $d = date('Y-m-d', strtotime("$d +1 day"))) {
                $n += q('INSERT IGNORE INTO closures (shop_id, day, note) VALUES (?,?,?)', [$sid, $d, $note ?: null])->rowCount();
            }
            $clash = (int)val("SELECT COUNT(*) FROM bookings WHERE shop_id = ? AND DATE(start_at) BETWEEN ? AND ? AND status IN ('menunggu','disahkan')", [$sid, $from, $to]);
            flash("$n hari cuti ditambah." . ($clash ? " Perhatian: ada $clash tempahan pada tarikh itu. Hubungi pelanggan dan batalkan di halaman Tempahan." : ''), $clash ? 'warn' : 'ok');
        }
    } elseif ($act === 'del_closure') {
        q('DELETE FROM closures WHERE id = ? AND shop_id = ?', [(int)($_POST['id'] ?? 0), $sid]);
        flash('Hari cuti dibuang.');
    }
    redirect('waktu.php');
}

$hours = shop_hours($sid);
$closures = rows('SELECT * FROM closures WHERE shop_id = ? AND day >= CURDATE() ORDER BY day LIMIT 100', [$sid]);
$title = 'Waktu & cuti';
$page = 'waktu';
include ROOT . '/includes/app_head.php';
?>
<div class="grid-2">
  <form method="post" class="card">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="hours">
    <h2 style="font-size:1.15rem">Waktu operasi</h2>
    <table class="table hours-table">
      <tbody>
      <?php foreach ([1, 2, 3, 4, 5, 6, 0] as $w): $h = $hours[$w]; ?>
        <tr>
          <td style="width:130px"><label class="check"><input type="checkbox" name="open[<?= $w ?>]" value="1"<?= $h ? ' checked' : '' ?> data-day="<?= $w ?>"> <?= HARI[$w] ?></label></td>
          <td><input type="time" name="from[<?= $w ?>]" value="<?= e(substr($h['open'] ?? '10:00:00', 0, 5)) ?>" aria-label="<?= HARI[$w] ?> buka" step="900"></td>
          <td class="muted">–</td>
          <td><input type="time" name="to[<?= $w ?>]" value="<?= e(substr($h['close'] ?? '21:00:00', 0, 5)) ?>" aria-label="<?= HARI[$w] ?> tutup" step="900"></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <h3 style="margin-top:1.5rem">Tetapan tempahan</h3>
    <div class="row-2">
      <div class="field">
        <label for="slot_step">Selang slot</label>
        <select id="slot_step" name="slot_step">
          <?php foreach ([10, 15, 20, 30, 45, 60] as $m): ?><option value="<?= $m ?>"<?= (int)$shop['slot_step'] === $m ? ' selected' : '' ?>>Setiap <?= $m ?> minit</option><?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="lead_minutes">Notis minimum</label>
        <select id="lead_minutes" name="lead_minutes">
          <?php foreach ([0 => 'Tiada', 30 => '30 minit', 60 => '1 jam', 120 => '2 jam', 180 => '3 jam', 720 => '12 jam', 1440 => '1 hari'] as $m => $l): ?><option value="<?= $m ?>"<?= (int)$shop['lead_minutes'] === $m ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="max_days">Boleh tempah sehingga</label>
        <select id="max_days" name="max_days">
          <?php foreach ([7, 14, 21, 30, 60, 90] as $m): ?><option value="<?= $m ?>"<?= (int)$shop['max_days'] === $m ? ' selected' : '' ?>><?= $m ?> hari ke depan</option><?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="cancel_hours">Pelanggan boleh batal sehingga</label>
        <select id="cancel_hours" name="cancel_hours">
          <?php foreach ([0 => 'Bila-bila masa', 1 => '1 jam sebelum', 3 => '3 jam sebelum', 6 => '6 jam sebelum', 24 => '1 hari sebelum', 48 => '2 hari sebelum'] as $m => $l): ?><option value="<?= $m ?>"<?= (int)$shop['cancel_hours'] === $m ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
        </select>
      </div>
    </div>
    <label class="check"><input type="checkbox" name="auto_confirm" value="1"<?= $shop['auto_confirm'] ? ' checked' : '' ?>> Sahkan tempahan online secara automatik</label>
    <p class="hint">Jika tidak ditanda, tempahan masuk sebagai "Menunggu" dan anda perlu sahkan sendiri (slot tetap dikunci).</p>
    <button class="btn" type="submit" style="margin-top:.5rem">Simpan</button>
  </form>

  <div class="col-stack">
    <form method="post" class="card">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_closure">
      <h2 style="font-size:1.15rem">Tambah cuti / hari tutup</h2>
      <p class="muted small">Cuti umum, cuti raya, kursus, dan sebagainya. Tiada slot dibuka pada hari ini.</p>
      <div class="row-2">
        <div class="field"><label for="from_day">Dari</label><input id="from_day" type="date" name="from_day" min="<?= date('Y-m-d') ?>" required></div>
        <div class="field"><label for="to_day">Hingga <span class="muted">(pilihan)</span></label><input id="to_day" type="date" name="to_day" min="<?= date('Y-m-d') ?>"></div>
      </div>
      <div class="field"><label for="note">Sebab</label><input id="note" name="note" maxlength="80" placeholder="Cth: Cuti Hari Raya"></div>
      <button class="btn" type="submit"><?= icon('plus', 'ic-sm') ?> Tambah cuti</button>
    </form>

    <section class="card">
      <h2 style="font-size:1.15rem">Cuti akan datang</h2>
      <?php if ($closures): ?>
        <table class="table"><tbody>
        <?php foreach ($closures as $c): ?>
          <tr>
            <td><b><?= e(tarikh($c['day'])) ?></b><br><small class="muted"><?= e($c['note'] ?: 'Cuti') ?></small></td>
            <td class="num"><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="del_closure"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-ghost btn-sm btn-icon" aria-label="Buang"><?= icon('trash', 'ic-sm') ?></button></form></td>
          </tr>
        <?php endforeach; ?>
        </tbody></table>
      <?php else: ?>
        <p class="muted small">Tiada cuti ditetapkan.</p>
      <?php endif; ?>
    </section>
  </div>
</div>
<?php
$footScripts = <<<'JS'
<script>
// Kelabukan input masa bagi hari tutup
document.querySelectorAll('[data-day]').forEach(function (c) {
  function sync() { c.closest('tr').querySelectorAll('input[type=time]').forEach(function (i) { i.disabled = !c.checked; }); }
  c.addEventListener('change', sync); sync();
});
</script>
JS;
include ROOT . '/includes/app_foot.php';
