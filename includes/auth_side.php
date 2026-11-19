<aside class="auth-side">
  <a class="logo-mark" href="<?= e(url('')) ?>"><span class="dot"><?= icon('calendar') ?></span><?= e(cfg('app_name')) ?></a>
  <div>
    <h2><?= $sideTitle ?? 'Biar pelanggan tempah sendiri, 24 jam.' ?></h2>
    <ul>
      <li><?= icon('check', 'ic-sm') ?> Pautan tempahan kedai anda sendiri</li>
      <li><?= icon('check', 'ic-sm') ?> Tiada tempahan bertindih, dijamin sistem</li>
      <li><?= icon('check', 'ic-sm') ?> Notifikasi Telegram setiap tempahan baru</li>
      <li><?= icon('check', 'ic-sm') ?> <?= (int)cfg('trial_days', 14) ?> hari pelan Pro percuma, tanpa kad kredit</li>
    </ul>
  </div>
  <p class="small" style="color:#a79d91;position:relative;z-index:1">© <?= date('Y') ?> <?= e(cfg('app_name')) ?></p>
</aside>
