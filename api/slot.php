<?php
// GET ?k=slug&d=YYYY-MM-DD&s=1,2&st=staffId  ->  {"slots":["10:00","10:30",...]}
require __DIR__ . '/../bootstrap.php';
require ROOT . '/lib/shop.php';
require ROOT . '/lib/booking.php';

$shop = shop_by_slug((string)($_GET['k'] ?? ''));
if (!$shop || $shop['status'] !== 'aktif') {
    json_out(['slots' => [], 'error' => 'Kedai tidak dijumpai.'], 404);
}
try {
    [, $mins] = pick_services((int)$shop['id'], explode(',', (string)($_GET['s'] ?? '')));
} catch (BookingError $e) {
    json_out(['slots' => [], 'error' => $e->getMessage()], 422);
}
$slots = free_slots($shop, (string)($_GET['d'] ?? ''), $mins, (int)($_GET['st'] ?? 0));
json_out(['slots' => array_keys($slots)]);
