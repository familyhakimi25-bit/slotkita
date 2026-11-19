<?php
require __DIR__ . '/_boot.php';
$sid = (int)$shop['id'];
$canBrand = plan_has($shop, 'branding');

if (is_post()) {
    check_csrf();
    $act = (string)($_POST['action'] ?? 'profile');
    try {
        if ($act === 'profile') {
            $name = trim((string)($_POST['name'] ?? ''));
            $slug = strtolower(trim((string)($_POST['slug'] ?? '')));
            $phone = trim((string)($_POST['phone'] ?? ''));
            $phoneN = $phone === '' ? '' : norm_phone($phone);
            $cat = (string)($_POST['category'] ?? 'lain');
            if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
                throw new RuntimeException('Nama kedai 2-80 aksara.');
            }
            if ($slug !== $shop['slug'] && ($p = slug_problem($slug, $sid))) {
                throw new RuntimeException($p);
            }
            if ($phone !== '' && $phoneN === '') {
                throw new RuntimeException('Nombor WhatsApp tidak sah.');
            }
            $color = (string)($_POST['color'] ?? $shop['color']);
            if (!$canBrand || !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                $color = $shop['color'];
            }
            q('UPDATE shops SET name = ?, slug = ?, category = ?, tagline = ?, about = ?, phone = ?, address = ?, city = ?, color = ? WHERE id = ?', [
                $name, $slug, isset(KATEGORI[$cat]) ? $cat : 'lain',
                mb_substr(trim((string)($_POST['tagline'] ?? '')), 0, 140) ?: null,
                mb_substr(trim((string)($_POST['about'] ?? '')), 0, 2000) ?: null,
                $phoneN ?: null,
                mb_substr(trim((string)($_POST['address'] ?? '')), 0, 255) ?: null,
                mb_substr(trim((string)($_POST['city'] ?? '')), 0, 60) ?: null,
                strtolower($color), $sid,
            ]);
            flash($slug !== $shop['slug'] ? 'Disimpan. Pautan baru anda: ' . url($slug) . ' (pautan lama tidak lagi berfungsi, jadi cetak semula poster).' : 'Profil kedai disimpan.');
        } elseif ($act === 'logo' || $act === 'cover') {
            if ($act === 'cover' && !$canBrand) {
                throw new RuntimeException('Gambar muka depan tersedia dalam pelan Asas & Pro.');
            }
            if (!empty($_POST['remove'])) {
                delete_upload($shop[$act]);
                q("UPDATE shops SET $act = NULL WHERE id = ?", [$sid]);
                flash($act === 'logo' ? 'Logo dibuang.' : 'Gambar muka depan dibuang.');
            } else {
                $file = save_upload($_FILES['file'] ?? [], $act . '-' . $sid, $act === 'logo' ? 2_000_000 : 4_000_000);
                delete_upload($shop[$act]);
                q("UPDATE shops SET $act = ? WHERE id = ?", [$file, $sid]);
                flash($act === 'logo' ? 'Logo dikemas kini.' : 'Gambar muka depan dikemas kini.');
            }
        }
    } catch (RuntimeException $ex) {
        flash($ex->getMessage(), 'bad');
    }
    redirect('kedai.php');
}

$title = 'Profil kedai';
$page = 'kedai';
$topActions = '<a class="btn btn-ghost btn-sm" href="' . e(shop_url($shop)) . '" target="_blank" rel="noopener">' . icon('eye', 'ic-sm') . ' Lihat halaman</a>';
include ROOT . '/includes/app_head.php';
?>
<div class="grid-2">
  <form method="post" class="card">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="profile">
    <h2 style="font-size:1.15rem">Maklumat kedai</h2>
    <div class="field"><label for="name">Nama kedai</label><input id="name" name="name" value="<?= e($shop['name']) ?>" maxlength="80" required></div>
    <div class="field">
      <label for="slug">Pautan tempahan</label>
      <div class="input-group"><span class="addon"><?= e(preg_replace('#^https?://#', '', url(''))) ?></span><input id="slug" name="slug" value="<?= e($shop['slug']) ?>" maxlength="40" required autocapitalize="off" spellcheck="false"></div>
      <span class="hint">Tukar pautan hanya jika perlu. Pautan lama akan berhenti berfungsi.</span>
    </div>
    <div class="row-2">
      <div class="field"><label for="category">Jenis perniagaan</label>
        <select id="category" name="category"><?php foreach (KATEGORI as $k => $l): ?><option value="<?= e($k) ?>"<?= $shop['category'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="phone">No. WhatsApp kedai</label><input id="phone" name="phone" type="tel" value="<?= e($shop['phone'] ? show_phone($shop['phone']) : '') ?>" maxlength="20"></div>
    </div>
    <div class="field"><label for="tagline">Slogan</label><input id="tagline" name="tagline" value="<?= e((string)$shop['tagline']) ?>" maxlength="140" placeholder="Cth: Fade kemas, harga student"></div>
    <div class="field"><label for="about">Tentang kedai</label><textarea id="about" name="about" maxlength="2000" rows="4" placeholder="Pengalaman, kepakaran, parking, dan sebagainya"><?= e((string)$shop['about']) ?></textarea></div>
    <div class="field"><label for="address">Alamat</label><input id="address" name="address" value="<?= e((string)$shop['address']) ?>" maxlength="255"></div>
    <div class="field"><label for="city">Bandar</label><input id="city" name="city" value="<?= e((string)$shop['city']) ?>" maxlength="60"></div>
    <div class="field">
      <label for="color">Warna jenama</label>
      <div class="flex">
        <input id="color" name="color" type="color" value="<?= e($shop['color']) ?>"<?= $canBrand ? '' : ' disabled' ?>>
        <?php if (!$canBrand): ?><span class="locked-note"><?= icon('lock', 'ic-sm') ?> Pelan Asas & Pro</span><?php else: ?><span class="hint">Warna butang & aksen di halaman tempahan. Teks dilaraskan automatik supaya sentiasa jelas.</span><?php endif; ?>
      </div>
    </div>
    <button class="btn" type="submit">Simpan</button>
  </form>

  <div class="col-stack">
    <section class="card">
      <h2 style="font-size:1.15rem">Logo</h2>
      <div class="flex" style="align-items:flex-start">
        <?php if ($shop['logo']): ?><img class="logo-preview" src="<?= e(upload_url($shop['logo'])) ?>" alt="Logo semasa"><?php else: ?><div class="logo-preview" style="display:grid;place-items:center;color:var(--muted)"><?= icon('image') ?></div><?php endif; ?>
        <div class="grow">
          <form method="post" enctype="multipart/form-data">
            <?= csrf_field() ?><input type="hidden" name="action" value="logo">
            <input type="file" name="file" accept="image/jpeg,image/png,image/webp" required aria-label="Pilih logo" style="max-width:100%">
            <p class="hint">Segi empat sama, JPG/PNG, maksimum 2MB.</p>
            <button class="btn btn-sm" type="submit">Muat naik</button>
          </form>
          <?php if ($shop['logo']): ?><form method="post" style="margin-top:.5rem"><?= csrf_field() ?><input type="hidden" name="action" value="logo"><button class="link small" name="remove" value="1">Buang logo</button></form><?php endif; ?>
        </div>
      </div>
    </section>

    <section class="card">
      <h2 style="font-size:1.15rem">Gambar muka depan</h2>
      <?php if (!$canBrand): ?>
        <p class="muted small">Letak gambar kedai di belakang nama kedai. <span class="locked-note"><?= icon('lock', 'ic-sm') ?> Pelan Asas & Pro</span></p>
        <a class="btn btn-sm" href="langganan.php">Lihat pelan</a>
      <?php else: ?>
        <?php if ($shop['cover']): ?><img class="cover-preview" src="<?= e(upload_url($shop['cover'])) ?>" alt="Gambar muka depan semasa"><?php endif; ?>
        <form method="post" enctype="multipart/form-data" style="margin-top:.75rem">
          <?= csrf_field() ?><input type="hidden" name="action" value="cover">
          <input type="file" name="file" accept="image/jpeg,image/png,image/webp" required aria-label="Pilih gambar muka depan" style="max-width:100%">
          <p class="hint">Mendatar (landskap), maksimum 4MB. Gambar digelapkan sedikit supaya teks jelas.</p>
          <button class="btn btn-sm" type="submit">Muat naik</button>
        </form>
        <?php if ($shop['cover']): ?><form method="post" style="margin-top:.5rem"><?= csrf_field() ?><input type="hidden" name="action" value="cover"><button class="link small" name="remove" value="1">Buang gambar</button></form><?php endif; ?>
      <?php endif; ?>
    </section>
  </div>
</div>
<?php include ROOT . '/includes/app_foot.php';
