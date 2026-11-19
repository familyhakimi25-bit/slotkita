<?php
$title = 'Akaun digantung';
include ROOT . '/includes/site_head.php';
?>
<main class="wrap narrow prose center" style="padding:5rem 0">
  <h1>Akaun kedai digantung</h1>
  <p class="muted">Akses panel dan halaman tempahan kedai ini ditutup buat sementara. Sila hubungi kami untuk maklumat lanjut.</p>
  <p><a class="btn" href="<?= e(wa_link((string)cfg('support_whatsapp'), 'Hai, akaun kedai saya digantung. Emel: ' . (current_user()['email'] ?? ''))) ?>" target="_blank" rel="noopener">WhatsApp sokongan</a></p>
  <form method="post" action="<?= e(url('keluar.php')) ?>"><?= csrf_field() ?><button class="link">Log keluar</button></form>
</main>
<?php include ROOT . '/includes/site_foot.php';
