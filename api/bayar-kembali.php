<?php
// Pembayar kembali dari ToyyibPay. JANGAN percaya status_id dalam URL - sahkan terus dengan gerbang.
require __DIR__ . '/../bootstrap.php';
require ROOT . '/lib/payment.php';

$bill = (string)($_GET['billcode'] ?? '');
$status = preg_match('/^[A-Za-z0-9]{4,40}$/', $bill) ? process_bill($bill) : 'unknown';
$msg = [
    'paid'    => ['Terima kasih! Bayaran berjaya dan langganan anda sudah aktif.', 'ok'],
    'pending' => ['Bayaran sedang diproses oleh bank. Status akan dikemas kini secara automatik dalam beberapa minit.', 'warn'],
    'failed'  => ['Bayaran tidak berjaya atau dibatalkan. Tiada caj dikenakan. Cuba lagi atau guna pindahan bank.', 'bad'],
    'unknown' => ['Bil tidak dijumpai.', 'bad'],
][$status];
flash($msg[0], $msg[1]);
redirect(url('app/langganan.php'));
