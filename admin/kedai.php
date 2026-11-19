<?php
require __DIR__ . '/_boot.php';

if (is_post()) {
    check_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $s = row('SELECT * FROM shops WHERE id = ?', [$id]);
    $act = (string)($_POST['action'] ?? '');
    if (!$s) {
        redirect('kedai.php');
    }
    switch ($act) {
        case 'impersonate':
            $_SESSION['as_shop'] = $id;
            redirect(url('app/'));
        case 'trial7':
            $base = $s['trial_until'] && $s['trial_until'] >= date('Y-m-d') ? $s['trial_until'] : date('Y-m-d');
            q('UPDATE shops SET trial_until = ? WHERE id = ?', [date('Y-m-d', strtotime("$base +7 days")), $id]);
            flash("Percubaan {$s['name']} dilanjutkan 7 hari.");
            break;
        case 'grant':
            $plan = (string)($_POST['plan'] ?? '');
            $until = (string)($_POST['until'] ?? '');
            if (!isset(PLANS[$plan]) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $until)) {
                flash('Pelan atau tarikh tidak sah.', 'bad');
                break;
            }
            q('UPDATE shops SET plan = ?, paid_until = ? WHERE id = ?', [$plan, $plan === 'percuma' ? null : $until, $id]);
            flash("{$s['name']}: pelan ditetapkan ke " . PLANS[$plan]['name'] . ($plan === 'percuma' ? '' : ' hingga ' . tarikh($until, false)) . '.');
            break;
        case 'suspend':
            q('UPDATE shops SET status = ? WHERE id = ?', [$s['status'] === 'aktif' ? 'digantung' : 'aktif', $id]);
            flash($s['status'] === 'aktif' ? "{$s['name']} digantung." : "{$s['name']} diaktifkan semula.");
            break;
        case 'reset':
            $temp = substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(9))), 0, 10);
            q('UPDATE users SET password_hash = ?, must_change = 1 WHERE shop_id = ?', [password_hash($temp, PASSWORD_DEFAULT), $id]);
            q('DELETE FROM login_attempts WHERE email IN (SELECT email FROM users WHERE shop_id = ?)', [$id]);
            flash("Kata laluan sementara {$s['name']}: $temp  (beri kepada pemilik; mereka wajib tukar selepas log masuk)", 'warn');
            break;
        case 'delete':
            if (($_POST['confirm_slug'] ?? '') !== $s['slug']) {
                flash('Taip pautan kedai dengan tepat untuk padam.', 'bad');
                break;
            }
            foreach ([$s['logo'], $s['cover']] as $f) {
                delete_upload($f);
            }
            q('DELETE FROM shops WHERE id = ?', [$id]);
            flash("{$s['name']} dipadam sepenuhnya.");
            break;
    }
    redirect('kedai.php?' . http_build_query(array_filter(['q' => $_POST['q'] ?? null, 'f' => $_POST['f'] ?? null])));
}

$q = trim((string)($_GET['q'] ?? ''));
$f = (string)($_GET['f'] ?? '');
$where = '1=1';
$params = [date('Y-m-d', strtotime('-30 days'))];
if ($q !== '') {
    $where .= ' AND (s.name LIKE ? OR s.slug LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, '%' . ltrim(preg_replace('/\D/', '', $q) ?: 'x', '0') . '%');
}
$list = rows(
    "SELECT s.*, u.name AS owner_name, u.email, u.phone AS owner_phone, u.last_login,
            (SELECT COUNT(*) FROM bookings b WHERE b.shop_id = s.id AND b.created_at >= ?) AS b30
     FROM shops s LEFT JOIN users u ON u.shop_id = s.id WHERE $where ORDER BY s.id DESC LIMIT 500",
    $params
);
$list = array_values(array_filter($list, function ($s) use ($f) {
    return match ($f) {
        'bayar' => $s['status'] === 'aktif' && shop_plan($s) !== 'percuma' && !on_trial($s),
        'cuba' => $s['status'] === 'aktif' && on_trial($s),
        'percuma' => $s['status'] === 'aktif' && shop_plan($s) === 'percuma',
        'gantung' => $s['status'] !== 'aktif',
        default => true,
    };
}));

$title = 'Kedai';
$page = 'kedai';
include ROOT . '/includes/app_head.php';
?>
<div class="toolbar">
  <nav class="seg" aria-label="Tapis">
    <?php foreach (['' => 'Semua', 'bayar' => 'Berbayar', 'cuba' => 'Percubaan', 'percuma' => 'Percuma', 'gantung' => 'Digantung'] as $k => $l): ?>
      <a href="?<?= e(http_build_query(array_filter(['f' => $k, 'q' => $q]))) ?>" class="<?= $f === $k ? 'on' : '' ?>"><?= $l ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="grow"></div>
  <form method="get" role="search"><input type="hidden" name="f" value="<?= e($f) ?>"><input type="search" name="q" value="<?= e($q) ?>" placeholder="Nama, pautan, emel, telefon" aria-label="Cari kedai" style="min-width:230px;min-height:40px"></form>
</div>

<section class="card">
  <div class="card-head"><h2 style="font-size:1.1rem"><?= count($list) ?> kedai</h2></div>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Kedai</th><th>Pemilik</th><th>Pelan</th><th class="num">Tempahan 30h</th><th>Daftar</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($list as $s): $eff = shop_plan($s); ?>
        <tr>
          <td><b><?= e($s['name']) ?></b><?= $s['status'] !== 'aktif' ? ' <span class="badge badge-bad">Digantung</span>' : '' ?><br><a class="link small" href="<?= e(url($s['slug'])) ?>" target="_blank">/<?= e($s['slug']) ?></a> <small class="muted">· <?= e(KATEGORI[$s['category']] ?? '') ?><?= $s['city'] ? ' · ' . e($s['city']) : '' ?></small></td>
          <td class="small"><?= e((string)$s['owner_name']) ?><br><span class="muted"><?= e((string)$s['email']) ?></span><?php if ($s['owner_phone']): ?><br><a class="link" href="<?= e(wa_link($s['owner_phone'])) ?>" target="_blank"><?= e(show_phone($s['owner_phone'])) ?></a><?php endif; ?></td>
          <td class="small">
            <?php if (on_trial($s)): ?><span class="badge badge-brand">Percubaan</span><br><span class="muted">hingga <?= e(tarikh($s['trial_until'], false)) ?></span>
            <?php elseif ($eff !== 'percuma'): ?><span class="badge badge-ok"><?= e(PLANS[$eff]['name']) ?></span><br><span class="muted">hingga <?= e(tarikh($s['paid_until'], false)) ?></span>
            <?php else: ?><span class="badge">Percuma</span><?php endif; ?>
          </td>
          <td class="num"><?= (int)$s['b30'] ?></td>
          <td class="small nowrap"><?= e(tarikh(substr($s['created_at'], 0, 10), false)) ?><br><span class="muted"><?= $s['last_login'] ? 'masuk ' . e(tarikh(substr($s['last_login'], 0, 10), false)) : 'belum log masuk' ?></span></td>
          <td class="num">
            <details class="dropdown">
              <summary class="btn btn-ghost btn-sm">Urus</summary>
              <div class="menu" style="min-width:250px">
                <?php $hidden = csrf_field() . '<input type="hidden" name="id" value="' . (int)$s['id'] . '"><input type="hidden" name="q" value="' . e($q) . '"><input type="hidden" name="f" value="' . e($f) . '">'; ?>
                <form method="post"><?= $hidden ?><button name="action" value="impersonate"><?= icon('eye', 'ic-sm') ?> Masuk sebagai kedai</button></form>
                <form method="post"><?= $hidden ?><button name="action" value="trial7"><?= icon('sparkle', 'ic-sm') ?> Lanjut percubaan +7 hari</button></form>
                <form method="post" style="display:grid;gap:.4rem;padding:.5rem .7rem;border-top:1px solid var(--line-2);border-bottom:1px solid var(--line-2);margin:.3rem 0">
                  <?= $hidden ?><input type="hidden" name="action" value="grant">
                  <span class="small muted" style="text-align:left">Tetapkan pelan (tanpa bayaran)</span>
                  <select name="plan" style="min-height:38px"><?php foreach (PLANS as $k => $p): ?><option value="<?= e($k) ?>"<?= $s['plan'] === $k ? ' selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?></select>
                  <input type="date" name="until" value="<?= e($s['paid_until'] ?: date('Y-m-d', strtotime('+1 month'))) ?>" style="min-height:38px">
                  <button class="btn btn-sm" style="justify-content:center">Simpan pelan</button>
                </form>
                <form method="post" data-confirm="Jana kata laluan sementara untuk <?= e($s['name']) ?>? Kata laluan lama tidak lagi berfungsi."><?= $hidden ?><button name="action" value="reset"><?= icon('lock', 'ic-sm') ?> Reset kata laluan</button></form>
                <form method="post" data-confirm="<?= $s['status'] === 'aktif' ? 'Gantung ' . e($s['name']) . '? Halaman tempahan akan ditutup.' : 'Aktifkan semula ' . e($s['name']) . '?' ?>"><?= $hidden ?><button name="action" value="suspend" class="<?= $s['status'] === 'aktif' ? 'danger' : '' ?>"><?= icon('shieldx', 'ic-sm') ?> <?= $s['status'] === 'aktif' ? 'Gantung' : 'Aktifkan semula' ?></button></form>
                <form method="post" style="display:grid;gap:.4rem;padding:.5rem .7rem;border-top:1px solid var(--line-2)" data-confirm="PADAM <?= e($s['name']) ?> dan SEMUA tempahannya? Tidak boleh dibatalkan.">
                  <?= $hidden ?><input type="hidden" name="action" value="delete">
                  <input name="confirm_slug" placeholder="Taip: <?= e($s['slug']) ?>" style="min-height:38px" aria-label="Taip pautan kedai untuk sahkan">
                  <button class="btn btn-danger btn-sm" style="justify-content:center"><?= icon('trash', 'ic-sm') ?> Padam kedai</button>
                </form>
              </div>
            </details>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php include ROOT . '/includes/app_foot.php';
