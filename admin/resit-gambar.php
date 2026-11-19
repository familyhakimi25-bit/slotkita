<?php
// Papar gambar resit pindahan (disimpan di storage/receipts, tidak boleh dicapai terus)
require __DIR__ . '/_boot.php';
$p = row('SELECT receipt_file FROM payments WHERE id = ?', [(int)($_GET['id'] ?? 0)]);
$f = $p && preg_match('/^[a-z0-9-]+\.(jpg|png|webp)$/', (string)$p['receipt_file']) ? ROOT . '/storage/receipts/' . $p['receipt_file'] : '';
if (!$f || !is_file($f)) {
    http_response_code(404);
    exit('Tiada resit');
}
header('Content-Type: ' . ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][pathinfo($f, PATHINFO_EXTENSION)]);
header('Cache-Control: private, max-age=3600');
readfile($f);
