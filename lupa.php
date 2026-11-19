<?php
require __DIR__ . '/bootstrap.php';
$title = 'Lupa kata laluan — ' . cfg('app_name');
$bare = true;
include ROOT . '/includes/site_head.php';
$msg = 'Hai, saya lupa kata laluan ' . cfg('app_name') . '. Emel akaun saya: ';
?>
<div class="auth">
  <?php include ROOT . '/includes/auth_side.php'; ?>
  <main class="auth-main">
    <div class="auth-box">
      <a class="logo-mark auth-mobile-logo" href="<?= e(url('')) ?>"><span class="dot"><?= icon('calendar') ?></span><?= e(cfg('app_name')) ?></a>
      <h1>Lupa kata laluan?</h1>
      <p class="sub">Hantar mesej WhatsApp kepada kami dari nombor telefon yang anda daftarkan. Kami akan sahkan dan beri kata laluan sementara, biasanya dalam masa beberapa jam.</p>
      <a class="btn btn-lg btn-block" href="<?= e(wa_link((string)cfg('support_whatsapp'), $msg)) ?>" target="_blank" rel="noopener"><?= icon('chat', 'ic-sm') ?> WhatsApp sokongan</a>
      <p class="center small muted" style="margin-top:1.2rem"><a class="link" href="<?= e(url('masuk.php')) ?>">Kembali ke log masuk</a></p>
    </div>
  </main>
</div>
<?php include ROOT . '/includes/site_foot.php';
