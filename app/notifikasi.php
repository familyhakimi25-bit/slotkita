<?php
require __DIR__ . '/_boot.php';
require_once ROOT . '/lib/telegram.php';
$sid = (int)$shop['id'];

if (is_post()) {
    check_csrf();
    $act = (string)($_POST['action'] ?? '');
    if ($act === 'test' && $shop['tg_chat_id']) {
        $ok = tg_send($shop['tg_chat_id'], "🔔 Mesej ujian dari " . cfg('app_name') . ".\nNotifikasi tempahan baru untuk {$shop['name']} akan masuk ke sini.");
        flash($ok ? 'Mesej ujian dihantar. Semak Telegram anda.' : 'Gagal hantar. Cuba sambung semula.', $ok ? 'ok' : 'bad');
    } elseif ($act === 'disconnect') {
        q('UPDATE shops SET tg_chat_id = NULL, tg_token = ? WHERE id = ?', [bin2hex(random_bytes(8)), $sid]);
        flash('Telegram diputuskan.');
    }
    redirect('notifikasi.php');
}

$allowed = plan_has($shop, 'telegram');
$title = 'Notifikasi';
$page = 'notifikasi';
include ROOT . '/includes/app_head.php';
?>
<div class="grid-2">
  <section class="card">
    <div class="flex" style="margin-bottom:1rem">
      <span class="f-ic" style="width:48px;height:48px;border-radius:14px;background:#e3f2fd;color:#1769aa;display:grid;place-items:center"><?= icon('send') ?></span>
      <div><h2 style="font-size:1.15rem;margin:0">Notifikasi Telegram</h2><p class="muted small" style="margin:0">Percuma. Setiap tempahan baru terus masuk ke telefon anda.</p></div>
    </div>

    <?php if (!$allowed): ?>
      <div class="alert alert-info"><?= icon('lock', 'ic-sm') ?> Notifikasi Telegram tersedia dalam pelan Asas & Pro.</div>
      <a class="btn" href="langganan.php">Lihat pelan</a>
    <?php elseif (!tg_enabled()): ?>
      <div class="alert alert-warn">Bot Telegram platform belum disediakan oleh admin. Ciri ini akan aktif sebaik sahaja bot dipasang.</div>
    <?php elseif ($shop['tg_chat_id']): ?>
      <div class="alert alert-ok"><?= icon('check', 'ic-sm') ?> Disambungkan. Tempahan baru akan dihantar ke Telegram anda.</div>
      <div class="flex flex-wrap">
        <form method="post"><?= csrf_field() ?><button class="btn" name="action" value="test"><?= icon('bell', 'ic-sm') ?> Hantar mesej ujian</button></form>
        <form method="post" data-confirm="Putuskan Telegram? Anda tidak akan terima notifikasi lagi."><?= csrf_field() ?><button class="btn btn-ghost" name="action" value="disconnect">Putuskan</button></form>
      </div>
    <?php else: ?>
      <ol style="padding-left:1.2rem;display:grid;gap:.5rem;margin:0 0 1.2rem">
        <li>Pasang aplikasi Telegram di telefon (jika belum ada).</li>
        <li>Tekan butang di bawah. Telegram akan terbuka.</li>
        <li>Tekan <b>START</b> dalam chat bot.</li>
        <li>Kembali ke sini dan muat semula halaman.</li>
      </ol>
      <div class="flex flex-wrap">
        <a class="btn btn-brand" href="<?= e(tg_connect_link($shop)) ?>" target="_blank" rel="noopener"><?= icon('send', 'ic-sm') ?> Sambung Telegram</a>
        <a class="btn btn-ghost" href="notifikasi.php"><?= icon('refresh', 'ic-sm') ?> Semak semula</a>
      </div>
      <p class="hint" style="margin-top:.8rem">Pautan ini unik untuk kedai anda, jadi jangan kongsi dengan orang lain.</p>
    <?php endif; ?>
  </section>

  <section class="card">
    <h2 style="font-size:1.15rem">Peringatan kepada pelanggan</h2>
    <p class="muted small">Di halaman <a class="link" href="tempahan.php">Tempahan</a>, setiap tempahan akan datang ada butang <b>Ingatkan</b>. Tekan sekali dan WhatsApp akan terbuka dengan mesej peringatan yang sudah siap ditulis (tarikh, masa, servis dan pautan batal). Anda cuma perlu tekan hantar.</p>
    <p class="muted small" style="margin:0">Tip: hantar peringatan pada petang sebelum hari tempahan. Ini banyak kurangkan pelanggan yang tak datang.</p>
  </section>
</div>
<?php include ROOT . '/includes/app_foot.php';
