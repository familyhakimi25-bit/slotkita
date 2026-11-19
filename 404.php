<?php
require_once __DIR__ . '/bootstrap.php';
http_response_code(404);
$title = 'Halaman tidak dijumpai — ' . cfg('app_name');
include ROOT . '/includes/site_head.php';
?>
<main class="wrap narrow prose center" style="padding:5rem 0">
  <h1>Halaman tidak dijumpai</h1>
  <p class="muted">Pautan mungkin salah eja, atau kedai ini sudah menukar pautannya.</p>
  <p><a class="btn" href="<?= e(url('')) ?>">Ke laman utama</a></p>
</main>
<?php include ROOT . '/includes/site_foot.php';
