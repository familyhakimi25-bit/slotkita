<?php
require __DIR__ . '/_boot.php';
$sid = (int)$shop['id'];

if (is_post()) {
    check_csrf();
    $act = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    if ($act === 'save') {
        $name = trim((string)($_POST['name'] ?? ''));
        $desc = trim((string)($_POST['description'] ?? ''));
        $min = (int)($_POST['duration_min'] ?? 0);
        $sen = to_sen((string)($_POST['price'] ?? ''));
        $sort = (int)($_POST['sort'] ?? 0);
        if ($name === '' || mb_strlen($name) > 80) {
            flash('Nama servis wajib (maksimum 80 aksara).', 'bad');
        } elseif ($min < 5 || $min > 600) {
            flash('Tempoh mesti antara 5 hingga 600 minit.', 'bad');
        } elseif ($sen < 0) {
            flash('Harga tidak sah. Contoh: 25 atau 25.50', 'bad');
        } elseif ($id) {
            q('UPDATE services SET name = ?, description = ?, duration_min = ?, price_sen = ?, sort = ? WHERE id = ? AND shop_id = ?',
                [$name, mb_substr($desc, 0, 200) ?: null, $min, $sen, $sort, $id, $sid]);
            flash('Servis dikemas kini.');
        } else {
            if ((int)val('SELECT COUNT(*) FROM services WHERE shop_id = ?', [$sid]) >= 60) {
                flash('Had 60 servis bagi setiap kedai.', 'bad');
            } else {
                q('INSERT INTO services (shop_id, name, description, duration_min, price_sen, sort) VALUES (?,?,?,?,?,?)',
                    [$sid, $name, mb_substr($desc, 0, 200) ?: null, $min, $sen, $sort]);
                flash('Servis ditambah.');
            }
        }
    } elseif ($act === 'toggle') {
        q('UPDATE services SET active = 1 - active WHERE id = ? AND shop_id = ?', [$id, $sid]);
        flash('Status servis ditukar.');
    } elseif ($act === 'delete') {
        // Tempahan lama kekal - nama & harga servis disalin ke booking_services semasa tempahan
        q('DELETE FROM services WHERE id = ? AND shop_id = ?', [$id, $sid]);
        flash('Servis dipadam.');
    }
    redirect('servis.php');
}

$list = rows('SELECT * FROM services WHERE shop_id = ? ORDER BY sort, id', [$sid]);
$title = 'Servis & harga';
$page = 'servis';
$topActions = '<button class="btn btn-sm" type="button" data-edit="0">' . icon('plus', 'ic-sm') . ' Tambah servis</button>';
include ROOT . '/includes/app_head.php';
?>
<p class="muted" style="margin-top:0">Tempoh servis menentukan berapa lama slot dikunci. Pelanggan boleh pilih lebih dari satu servis, dan tempohnya dijumlahkan.</p>

<?php if ($list): ?>
<div class="item-list">
  <?php foreach ($list as $s): ?>
  <div class="item<?= $s['active'] ? '' : ' off' ?>">
    <div class="grow">
      <b><?= e($s['name']) ?> <?php if (!$s['active']): ?><span class="badge">Disembunyi</span><?php endif; ?></b>
      <small><?= (int)$s['duration_min'] ?> min<?= $s['description'] ? ' · ' . e($s['description']) : '' ?></small>
    </div>
    <span class="price"><?= e(rm((int)$s['price_sen'])) ?></span>
    <button class="btn btn-ghost btn-sm" type="button" data-edit="<?= (int)$s['id'] ?>"
      data-name="<?= e($s['name']) ?>" data-description="<?= e((string)$s['description']) ?>" data-duration_min="<?= (int)$s['duration_min'] ?>"
      data-price="<?= e(rm((int)$s['price_sen'], false)) ?>" data-sort="<?= (int)$s['sort'] ?>"><?= icon('edit', 'ic-sm') ?> Ubah</button>
    <details class="dropdown">
      <summary class="btn btn-ghost btn-sm btn-icon" aria-label="Lagi">⋯</summary>
      <div class="menu">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button name="action" value="toggle"><?= icon($s['active'] ? 'eye' : 'check', 'ic-sm') ?> <?= $s['active'] ? 'Sembunyikan' : 'Tunjuk semula' ?></button></form>
        <form method="post" data-confirm="Padam servis &quot;<?= e($s['name']) ?>&quot;? Tempahan lama tidak terjejas."><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="danger" name="action" value="delete"><?= icon('trash', 'ic-sm') ?> Padam</button></form>
      </div>
    </details>
  </div>
  <?php endforeach; ?>
</div>
<?php else: ?>
  <div class="card empty"><?= icon('scissors', 'ic-lg') ?>Belum ada servis. Tambah servis pertama anda.</div>
<?php endif; ?>

<dialog class="modal" id="edit-dlg">
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="0">
    <div class="modal-head"><h3 id="edit-title">Servis</h3><button type="button" class="btn btn-ghost btn-icon" onclick="this.closest('dialog').close()" aria-label="Tutup"><?= icon('x', 'ic-sm') ?></button></div>
    <div class="modal-body">
      <div class="field"><label for="s-name">Nama servis</label><input id="s-name" name="name" maxlength="80" required></div>
      <div class="row-2">
        <div class="field"><label for="s-price">Harga</label><div class="input-group"><span class="addon">RM</span><input id="s-price" name="price" inputmode="decimal" required placeholder="25"></div></div>
        <div class="field"><label for="s-min">Tempoh</label><div class="input-group"><input id="s-min" name="duration_min" type="number" min="5" max="600" step="5" required value="30"><span class="addon">minit</span></div></div>
      </div>
      <div class="field"><label for="s-desc">Penerangan <span class="muted">(pilihan)</span></label><input id="s-desc" name="description" maxlength="200" placeholder="Cth: termasuk cuci & styling"></div>
      <div class="field"><label for="s-sort">Susunan</label><input id="s-sort" name="sort" type="number" min="0" max="999" value="0"><span class="hint">Nombor kecil dipapar dahulu.</span></div>
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
    var isNew = b.dataset.edit === '0';
    // guna elements.namedItem: f.id / f.name ialah sifat borang itu sendiri, bukan input
    function el(n) { return f.elements.namedItem(n); }
    el('id').value = b.dataset.edit;
    document.getElementById('edit-title').textContent = isNew ? 'Tambah servis' : 'Ubah servis';
    el('name').value = isNew ? '' : b.dataset.name;
    el('description').value = isNew ? '' : b.dataset.description;
    el('duration_min').value = isNew ? 30 : b.dataset.duration_min;
    el('price').value = isNew ? '' : b.dataset.price;
    el('sort').value = isNew ? document.querySelectorAll('.item').length : b.dataset.sort;
    d.showModal();
    el('name').focus();
  });
});
</script>
JS;
include ROOT . '/includes/app_foot.php';
