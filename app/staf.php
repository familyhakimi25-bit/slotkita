<?php
require __DIR__ . '/_boot.php';
$sid = (int)$shop['id'];

if (is_post()) {
    check_csrf();
    $act = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    if ($act === 'save') {
        $name = trim((string)($_POST['name'] ?? ''));
        $titleIn = trim((string)($_POST['title'] ?? ''));
        $off = array_values(array_intersect(array_map('strval', (array)($_POST['off'] ?? [])), ['0', '1', '2', '3', '4', '5', '6']));
        $sort = (int)($_POST['sort'] ?? 0);
        if ($name === '' || mb_strlen($name) > 60) {
            flash('Nama staf wajib (maksimum 60 aksara).', 'bad');
        } elseif ($id) {
            q('UPDATE staff SET name = ?, title = ?, off_days = ?, sort = ? WHERE id = ? AND shop_id = ?',
                [$name, mb_substr($titleIn, 0, 60) ?: null, implode(',', $off), $sort, $id, $sid]);
            flash('Staf dikemas kini.');
        } elseif ((int)val('SELECT COUNT(*) FROM staff WHERE shop_id = ?', [$sid]) >= 30) {
            flash('Had 30 staf bagi setiap kedai.', 'bad');
        } else {
            q('INSERT INTO staff (shop_id, name, title, off_days, sort) VALUES (?,?,?,?,?)', [$sid, $name, mb_substr($titleIn, 0, 60) ?: null, implode(',', $off), $sort]);
            flash('Staf ditambah.');
        }
    } elseif ($act === 'toggle') {
        q('UPDATE staff SET active = 1 - active WHERE id = ? AND shop_id = ?', [$id, $sid]);
        flash('Status staf ditukar.');
    } elseif ($act === 'delete') {
        $future = (int)val("SELECT COUNT(*) FROM bookings WHERE staff_id = ? AND shop_id = ? AND status IN ('menunggu','disahkan') AND start_at > NOW()", [$id, $sid]);
        if ($future) {
            flash("Staf ini ada $future tempahan akan datang. Pindahkan atau batalkan dahulu, atau pilih \"Nyahaktif\".", 'bad');
        } elseif ((int)val('SELECT COUNT(*) FROM staff WHERE shop_id = ?', [$sid]) <= 1) {
            flash('Kedai perlu sekurang-kurangnya seorang staf.', 'bad');
        } else {
            q('DELETE FROM staff WHERE id = ? AND shop_id = ?', [$id, $sid]);
            flash('Staf dipadam.');
        }
    }
    redirect('staf.php');
}

$list = rows('SELECT * FROM staff WHERE shop_id = ? ORDER BY sort, id', [$sid]);
$limit = (int)PLANS[shop_plan($shop)]['staff'];
$bookableIds = array_map(fn($s) => (int)$s['id'], bookable_staff($shop));
$hidden = count(array_filter($list, fn($s) => $s['active'] && !in_array((int)$s['id'], $bookableIds, true)));

$title = 'Staf';
$page = 'staf';
$topActions = '<button class="btn btn-sm" type="button" data-edit="0">' . icon('plus', 'ic-sm') . ' Tambah staf</button>';
include ROOT . '/includes/app_head.php';
?>
<p class="muted" style="margin-top:0">Setiap staf ada jadual sendiri, jadi dua pelanggan boleh ditempah pada masa yang sama jika dengan staf berlainan. Pelan <?= e(PLANS[shop_plan($shop)]['name']) ?> benarkan <b><?= $limit ?></b> staf dipapar di halaman tempahan.</p>
<?php if ($hidden): ?>
  <div class="alert alert-warn flex between flex-wrap"><span><?= $hidden ?> staf aktif tidak dipapar kepada pelanggan kerana had pelan.</span><a class="btn btn-sm" href="langganan.php">Naik taraf</a></div>
<?php endif; ?>

<div class="item-list">
  <?php foreach ($list as $s):
      $off = $s['off_days'] === '' ? [] : explode(',', $s['off_days']);
      $shown = in_array((int)$s['id'], $bookableIds, true); ?>
  <div class="item<?= $s['active'] ? '' : ' off' ?>">
    <span class="av" style="width:44px;height:44px;border-radius:12px;background:var(--line-2);display:grid;place-items:center;font-weight:800"><?= e(mb_strtoupper(mb_substr($s['name'], 0, 1))) ?></span>
    <div class="grow">
      <b><?= e($s['name']) ?>
        <?php if (!$s['active']): ?><span class="badge">Tidak aktif</span><?php elseif (!$shown): ?><span class="badge badge-warn">Had pelan</span><?php endif; ?></b>
      <small><?= e($s['title'] ?: 'Staf') ?> · <?= $off ? 'Cuti: ' . e(implode(', ', array_map(fn($w) => HARI[(int)$w], $off))) : 'Bekerja setiap hari kedai buka' ?></small>
    </div>
    <button class="btn btn-ghost btn-sm" type="button" data-edit="<?= (int)$s['id'] ?>" data-name="<?= e($s['name']) ?>" data-title="<?= e((string)$s['title']) ?>" data-off="<?= e($s['off_days']) ?>" data-sort="<?= (int)$s['sort'] ?>"><?= icon('edit', 'ic-sm') ?> Ubah</button>
    <details class="dropdown">
      <summary class="btn btn-ghost btn-sm btn-icon" aria-label="Lagi">⋯</summary>
      <div class="menu">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button name="action" value="toggle"><?= $s['active'] ? 'Nyahaktif' : 'Aktifkan semula' ?></button></form>
        <form method="post" data-confirm="Padam staf <?= e($s['name']) ?>?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="danger" name="action" value="delete"><?= icon('trash', 'ic-sm') ?> Padam</button></form>
      </div>
    </details>
  </div>
  <?php endforeach; ?>
</div>

<dialog class="modal" id="edit-dlg">
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="0">
    <div class="modal-head"><h3 id="edit-title">Staf</h3><button type="button" class="btn btn-ghost btn-icon" onclick="this.closest('dialog').close()" aria-label="Tutup"><?= icon('x', 'ic-sm') ?></button></div>
    <div class="modal-body">
      <div class="row-2">
        <div class="field"><label for="st-name">Nama</label><input id="st-name" name="name" maxlength="60" required></div>
        <div class="field"><label for="st-title">Jawatan</label><input id="st-title" name="title" maxlength="60" placeholder="Cth: Barber senior"></div>
      </div>
      <div class="field">
        <span class="label">Hari cuti mingguan</span>
        <div class="day-toggle">
          <?php foreach ([1, 2, 3, 4, 5, 6, 0] as $w): ?>
            <label><input type="checkbox" name="off[]" value="<?= $w ?>"><span><?= HARI[$w] ?></span></label>
          <?php endforeach; ?>
        </div>
        <span class="hint">Hari yang ditanda: staf ini tidak boleh ditempah.</span>
      </div>
      <div class="field"><label for="st-sort">Susunan</label><input id="st-sort" name="sort" type="number" min="0" max="999" value="0"><span class="hint">Staf dengan nombor kecil dipapar dahulu (dan dikira dahulu untuk had pelan).</span></div>
    </div>
    <div class="modal-foot"><button type="button" class="btn btn-ghost" onclick="this.closest('dialog').close()">Batal</button><button class="btn" type="submit">Simpan</button></div>
  </form>
</dialog>
<?php
$footScripts = <<<'JS'
<script>
document.querySelectorAll('[data-edit]').forEach(function (b) {
  b.addEventListener('click', function () {
    var d = document.getElementById('edit-dlg'), f = d.querySelector('form');
    function el(n) { return f.elements.namedItem(n); }
    var isNew = b.dataset.edit === '0';
    el('id').value = b.dataset.edit;
    document.getElementById('edit-title').textContent = isNew ? 'Tambah staf' : 'Ubah staf';
    el('name').value = isNew ? '' : b.dataset.name;
    el('title').value = isNew ? '' : b.dataset.title;
    el('sort').value = isNew ? document.querySelectorAll('.item').length : b.dataset.sort;
    var off = isNew ? [] : (b.dataset.off || '').split(',');
    f.querySelectorAll('input[name="off[]"]').forEach(function (c) { c.checked = off.indexOf(c.value) !== -1; });
    d.showModal();
    el('name').focus();
  });
});
</script>
JS;
include ROOT . '/includes/app_foot.php';
