<?php
// POST JSON dari halaman kedai -> cipta tempahan
require __DIR__ . '/../bootstrap.php';
require ROOT . '/lib/shop.php';
require ROOT . '/lib/booking.php';

if (!is_post()) {
    json_out(['ok' => false, 'error' => 'Kaedah tidak dibenarkan.'], 405);
}
$in = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($in)) {
    json_out(['ok' => false, 'error' => 'Data tidak sah.'], 400);
}
$_POST['_csrf'] = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals(csrf_token(), (string)$_POST['_csrf'])) {
    json_out(['ok' => false, 'error' => 'Sesi tamat. Muat semula halaman dan cuba lagi.'], 419);
}
// Perangkap bot: medan tersembunyi mesti kosong
if (!empty($in['website'])) {
    json_out(['ok' => false, 'error' => 'Tempahan ditolak.'], 400);
}

$shop = shop_by_slug((string)($in['k'] ?? ''));
if (!$shop) {
    json_out(['ok' => false, 'error' => 'Kedai tidak dijumpai.'], 404);
}

try {
    $b = create_booking(
        $shop,
        (array)($in['services'] ?? []),
        (int)($in['staff'] ?? 0),
        (string)($in['date'] ?? ''),
        (string)($in['time'] ?? ''),
        (string)($in['name'] ?? ''),
        (string)($in['phone'] ?? ''),
        (string)($in['note'] ?? '')
    );
} catch (BookingError $e) {
    json_out(['ok' => false, 'error' => $e->getMessage()], $e->getCode() === 409 ? 409 : 422);
}

$t = strtotime($b['start_at']);
json_out(['ok' => true, 'booking' => [
    'code'     => $b['code'],
    'status'   => $b['status'],
    'services' => booking_service_names($b),
    'date'     => tarikh(date('Y-m-d', $t)),
    'time'     => masa(date('H:i:s', $t)),
    'staff'    => $b['staff_name'] ?: 'Sesiapa',
    'total'    => rm((int)$b['total_sen']),
    'gcal'     => gcal_link($shop, $b),
    'wa'       => $shop['phone'] ? wa_link($shop['phone'], "Hai {$shop['name']}, saya {$b['customer_name']} dah tempah #{$b['code']} pada " . tarikh(date('Y-m-d', $t)) . ', ' . masa(date('H:i:s', $t)) . ' (' . booking_service_names($b) . ').') : '',
]]);
