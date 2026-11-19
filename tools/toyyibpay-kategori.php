<?php
// Cipta kategori bil ToyyibPay SEKALI, kemudian salin kod ke config.php -> toyyibpay.category_code
//   php tools/toyyibpay-kategori.php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../bootstrap.php';
require ROOT . '/lib/payment.php';

if (cfg('toyyibpay.secret_key') === '') {
    exit("Isi dahulu toyyibpay.secret_key dalam config.php\n");
}
$data = tp_post('createCategory', [
    'catname'        => tp_clean(cfg('app_name') . ' Langganan', 30),
    'catdescription' => tp_clean('Bayaran langganan kedai ' . cfg('app_name'), 100),
    'userSecretKey'  => cfg('toyyibpay.secret_key'),
]);
$code = $data[0]['CategoryCode'] ?? '';
echo $code ? "CategoryCode: $code\nSalin ke config.php -> 'category_code' => '$code'\n" : 'Gagal: ' . json_encode($data) . "\n";
