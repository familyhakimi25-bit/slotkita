<?php
require __DIR__ . '/_boot.php';

$link = shop_url($shop);
$short = preg_replace('#^https?://#', '', $link);
$color = shop_color($shop);
$ink = ink_on($color);
$showBrand = !plan_has($shop, 'white_label');
$captions = [
    'Instagram / TikTok bio' => "📅 Tempah slot online 24 jam 👇\n$link",
    'Status / broadcast WhatsApp' => "Salam semua! Sekarang boleh tempah slot di {$shop['name']} secara online, tak perlu tunggu balasan mesej 😊\n\nPilih servis, staf & masa sendiri di sini:\n$link",
    'Google Business / Facebook' => "Buat tempahan di {$shop['name']} secara online. Pilih servis dan masa yang sesuai, dan slot anda terus dikunci. $link",
];

$title = 'Kongsi & poster';
$page = 'kongsi';
include ROOT . '/includes/app_head.php';
?>
<style>
/* Semua saiz dalam cqi (% lebar poster) supaya sama di skrin dan bila dicetak A5 */
.poster { container-type: inline-size; width: 100%; max-width: 420px; aspect-ratio: 1 / 1.414; margin: 0 auto; background: #fff; border-radius: 18px; border: 1px solid var(--line); box-shadow: var(--shadow); overflow: hidden; }
.poster-in { height: 100%; display: flex; flex-direction: column; }
.poster-top { background: <?= e($color) ?>; color: <?= e($ink) ?>; padding: 7cqi 7cqi 5cqi; text-align: center; }
.poster-top small { font-weight: 800; letter-spacing: .18em; text-transform: uppercase; font-size: 3cqi; opacity: .85; }
.poster-top h2 { font-size: 7.2cqi; margin: 1cqi 0 0; color: inherit; line-height: 1.1; }
.poster-mid { flex: 1; min-height: 0; display: grid; place-items: center; padding: 5cqi 8cqi 2cqi; text-align: center; }
.poster-mid .big { font-size: 7.4cqi; font-weight: 800; letter-spacing: -.03em; line-height: 1.05; margin-bottom: 4cqi; }
.qr-wrap { width: 56cqi; aspect-ratio: 1; padding: 2.5cqi; border-radius: 4cqi; border: .8cqi solid var(--ink); }
.qr-wrap svg { width: 100%; height: 100%; }
.poster-link { font-weight: 800; font-size: 3.8cqi; margin-top: 3cqi; word-break: break-all; }
.poster-steps { display: flex; justify-content: center; gap: 4cqi; font-size: 3.1cqi; font-weight: 700; color: var(--muted); padding: 0 6cqi 4cqi; }
.poster-foot { text-align: center; font-size: 2.6cqi; color: #9a9086; padding-bottom: 4cqi; }
.cap { white-space: pre-wrap; background: var(--paper); border: 1px solid var(--line); border-radius: 12px; padding: .8rem; font-size: .9rem; margin: .4rem 0 .6rem; }
@media print {
  body * { visibility: hidden; }
  .poster, .poster * { visibility: visible; }
  .poster { position: fixed; inset: 0; margin: auto; width: 148mm; max-width: none; height: 210mm; border: 0; border-radius: 0; box-shadow: none; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  @page { size: A5; margin: 0; }
}
</style>

<div class="grid-2">
  <div class="col-stack">
    <section class="card">
      <h2 style="font-size:1.15rem">Pautan anda</h2>
      <div class="share-box"><code><?= e($short) ?></code><button type="button" class="btn btn-sm" data-copy="<?= e($link) ?>"><?= icon('copy', 'ic-sm') ?> Salin</button></div>
      <div class="flex flex-wrap" style="margin-top:.75rem">
        <a class="btn btn-ghost btn-sm" href="<?= e(wa_link('', 'Tempah slot di ' . $shop['name'] . ' secara online: ' . $link)) ?>" target="_blank" rel="noopener"><?= icon('chat', 'ic-sm') ?> WhatsApp</a>
        <a class="btn btn-ghost btn-sm" href="https://www.facebook.com/sharer/sharer.php?u=<?= e(rawurlencode($link)) ?>" target="_blank" rel="noopener"><?= icon('external', 'ic-sm') ?> Facebook</a>
        <a class="btn btn-ghost btn-sm" href="https://t.me/share/url?url=<?= e(rawurlencode($link)) ?>" target="_blank" rel="noopener"><?= icon('send', 'ic-sm') ?> Telegram</a>
      </div>
    </section>

    <section class="card">
      <h2 style="font-size:1.15rem">Ayat siap untuk media sosial</h2>
      <?php foreach ($captions as $label => $text): ?>
        <div class="flex between"><b class="small"><?= e($label) ?></b><button type="button" class="btn btn-ghost btn-sm" data-copy="<?= e($text) ?>"><?= icon('copy', 'ic-sm') ?> Salin</button></div>
        <div class="cap"><?= e($text) ?></div>
      <?php endforeach; ?>
    </section>

    <section class="card" id="jemput">
      <h2 style="font-size:1.15rem">Ajak kedai lain, dapat <?= REFERRAL_DAYS ?> hari percuma</h2>
      <p class="muted small">Kongsi pautan ini dengan kawan yang ada kedai. Bila kedai itu membuat bayaran pertama, anda dapat <?= REFERRAL_DAYS ?> hari percuma. Tiada had bilangan jemputan.</p>
      <div class="share-box"><code><?= e(preg_replace('#^https?://#', '', referral_link($shop))) ?></code><button type="button" class="btn btn-sm" data-copy="<?= e(referral_link($shop)) ?>"><?= icon('copy', 'ic-sm') ?> Salin</button></div>
      <?php $refs = rows('SELECT name, ref_rewarded FROM shops WHERE referred_by = ? ORDER BY id DESC', [$shop['id']]); ?>
      <p class="small" style="margin:.75rem 0 0"><b><?= count($refs) ?></b> kedai dijemput · <b><?= count(array_filter($refs, fn($r) => $r['ref_rewarded'])) ?></b> sudah melanggan</p>
    </section>

    <section class="card">
      <h2 style="font-size:1.15rem">Tip dapat tempahan pertama</h2>
      <ol class="small" style="margin:0;padding-left:1.2rem;display:grid;gap:.4rem">
        <li>Tampal poster di cermin kedai dan kaunter bayaran.</li>
        <li>Letak pautan di bio Instagram & TikTok, dan butang "Tempah" di Google Maps (Business Profile → Pautan tempahan).</li>
        <li>Selepas setiap servis, beritahu pelanggan: <i>"lain kali tempah sini, tak payah tunggu."</i></li>
        <li>Hantar status WhatsApp seminggu sekali dengan slot kosong hujung minggu.</li>
      </ol>
    </section>
  </div>

  <section class="card">
    <div class="card-head"><h2 style="font-size:1.15rem">Poster QR (A5)</h2>
      <div class="flex"><button class="btn btn-ghost btn-sm" type="button" id="dl-qr"><?= icon('image', 'ic-sm') ?> Muat turun QR</button><button class="btn btn-sm" type="button" onclick="window.print()"><?= icon('printer', 'ic-sm') ?> Cetak</button></div>
    </div>
    <div class="poster" id="poster"><div class="poster-in">
      <div class="poster-top"><small>Tempahan online</small><h2><?= e($shop['name']) ?></h2></div>
      <div class="poster-mid">
        <div style="width:100%;display:grid;justify-items:center">
          <div class="big">Imbas &amp; tempah<br>tanpa tunggu</div>
          <div class="qr-wrap" id="qr" aria-label="Kod QR ke <?= e($short) ?>"></div>
          <div class="poster-link"><?= e($short) ?></div>
        </div>
      </div>
      <div class="poster-steps"><span>① Pilih servis</span><span>② Pilih masa</span><span>③ Siap!</span></div>
      <?php if ($showBrand): ?><div class="poster-foot">Dikuasakan oleh <?= e(cfg('app_name')) ?></div><?php endif; ?>
    </div>
    </div>
  </section>
</div>
<?php
$linkJs = json_encode($link);
$slugJs = json_encode('qr-' . $shop['slug'] . '.png');
$footScripts = <<<HTML
<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.js"></script>
<script>
(function () {
  var link = $linkJs;
  var qr = qrcode(0, 'M');
  qr.addData(link);
  qr.make();
  document.getElementById('qr').innerHTML = qr.createSvgTag({ cellSize: 8, margin: 0, scalable: true });
  document.getElementById('dl-qr').addEventListener('click', function () {
    var n = qr.getModuleCount(), cell = 24, pad = cell * 4, c = document.createElement('canvas');
    c.width = c.height = n * cell + pad * 2;
    var x = c.getContext('2d');
    x.fillStyle = '#fff'; x.fillRect(0, 0, c.width, c.height);
    x.fillStyle = '#16130f';
    for (var r = 0; r < n; r++) for (var k = 0; k < n; k++) if (qr.isDark(r, k)) x.fillRect(pad + k * cell, pad + r * cell, cell, cell);
    var a = document.createElement('a');
    a.href = c.toDataURL('image/png');
    a.download = $slugJs;
    a.click();
  });
})();
</script>
HTML;
include ROOT . '/includes/app_foot.php';
