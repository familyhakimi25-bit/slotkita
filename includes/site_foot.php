<?php if (empty($bare)): ?>
<footer class="site-foot">
  <div class="wrap foot-in">
    <div>
      <a class="logo-mark" href="<?= e(url('')) ?>"><span class="dot"><?= icon('calendar') ?></span><?= e(cfg('app_name')) ?></a>
      <p class="muted small">Sistem tempahan online untuk kedai servis di Malaysia.</p>
    </div>
    <nav class="foot-links" aria-label="Pautan kaki">
      <a href="<?= e(url('daftar.php')) ?>">Daftar kedai</a>
      <a href="<?= e(url('masuk.php')) ?>">Masuk</a>
      <a href="<?= e(url('demo')) ?>">Demo</a>
      <a href="<?= e(url('terma.php')) ?>">Terma</a>
      <a href="<?= e(url('terma.php')) ?>#privasi">Privasi</a>
      <a href="<?= e(wa_link((string)cfg('support_whatsapp'), 'Hai, saya nak tanya tentang ' . cfg('app_name'))) ?>" target="_blank" rel="noopener">WhatsApp kami</a>
    </nav>
  </div>
  <div class="wrap copy small muted">© <?= date('Y') ?> <?= e(cfg('app_name')) ?></div>
</footer>
<?php endif; ?>
<?= $footScripts ?? '' ?>
</body>
</html>
