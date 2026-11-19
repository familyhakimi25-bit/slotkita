<?php
// Callback pelayan-ke-pelayan dari ToyyibPay (POST). Sahkan hash + tanya status sebenar.
require __DIR__ . '/../bootstrap.php';
require ROOT . '/lib/payment.php';

if (!is_post() || pay_driver() !== 'toyyibpay' || !tp_callback_valid($_POST)) {
    http_response_code(400);
    exit('invalid');
}
$bill = (string)($_POST['billcode'] ?? '');
if (!preg_match('/^[A-Za-z0-9]{4,40}$/', $bill)) {
    http_response_code(400);
    exit('invalid');
}
process_bill($bill);
echo 'OK';
