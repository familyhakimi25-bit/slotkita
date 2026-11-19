<?php
// Cipta / set semula kedai DEMO (slotkita.my/demo) yang dipautkan dari laman utama.
//   php tools/seed-demo.php
// Selamat dijalankan semula: kedai demo lama dipadam dan dicipta baru dengan tempahan contoh.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../bootstrap.php';
require ROOT . '/lib/shop.php';
require ROOT . '/lib/booking.php';

// Di komputer sendiri kata laluan mudah; di hosting rawak supaya orang luar tak boleh ubah kedai demo
$demoPass = cfg('env') === 'development' ? 'demo1234' : bin2hex(random_bytes(6));

$old = row("SELECT id FROM shops WHERE slug = 'demo'");
if ($old) {
    q('DELETE FROM shops WHERE id = ?', [$old['id']]);   // CASCADE padam semua data kedai demo
}

[$shopId] = create_shop([
    'slug' => 'demo', 'shop_name' => 'Demo Barbershop', 'category' => 'barber',
    'owner_name' => 'Pemilik Demo', 'staff_name' => 'Abang Zul',
    'email' => 'demo@slotkita.test', 'phone' => '60123456789', 'password' => $demoPass,
    'city' => 'Kuala Lumpur', 'tagline' => 'Ini kedai contoh. Cuba buat tempahan - tiada apa akan dicaj.',
]);
q("UPDATE shops SET plan = 'pro', paid_until = '2099-12-31', address = 'Jalan Contoh 1, Taman Demo', color = '#FF5A1F',
   about = 'Halaman ini menunjukkan apa yang pelanggan anda nampak. Pilih servis, staf dan masa - cuba sendiri.' WHERE id = ?", [$shopId]);
q("INSERT INTO staff (shop_id, name, title, sort) VALUES (?, 'Amir', 'Barber senior', 1), (?, 'Kumar', 'Barber', 2)", [$shopId, $shopId]);

// Tempahan contoh: 30 hari lepas (selesai) + beberapa hari ini/esok (supaya slot nampak sibuk)
$shop = row('SELECT * FROM shops WHERE id = ?', [$shopId]);
$staff = array_column(rows('SELECT id FROM staff WHERE shop_id = ?', [$shopId]), 'id');
$services = rows('SELECT * FROM services WHERE shop_id = ?', [$shopId]);
$names = ['Hafiz', 'Danish', 'Aiman', 'Farid', 'Wei Jie', 'Arjun', 'Syafiq', 'Irfan', 'Luqman', 'Hakim', 'Rizal', 'Zikri'];
mt_srand(42);
$n = 0;
for ($day = -30; $day <= 1; $day++) {
    $date = date('Y-m-d', strtotime("$day days"));
    $count = $day <= 0 ? mt_rand(3, 9) : 4;
    for ($i = 0; $i < $count; $i++) {
        $svc = $services[mt_rand(0, count($services) - 1)];
        $hour = 10 + mt_rand(0, 9);
        $start = sprintf('%s %02d:%s:00', $date, $hour, mt_rand(0, 1) ? '00' : '30');
        $sid = $staff[mt_rand(0, count($staff) - 1)];
        $end = date('Y-m-d H:i:s', strtotime($start) + $svc['duration_min'] * 60);
        if (val("SELECT 1 FROM bookings WHERE staff_id = ? AND start_at < ? AND end_at > ?", [$sid, $end, $start])) {
            continue;
        }
        $status = strtotime($start) < time() ? (mt_rand(1, 12) === 1 ? 'tidak_hadir' : 'selesai') : 'disahkan';
        $created = date('Y-m-d H:i:s', strtotime($start) - mt_rand(1, 72) * 3600);
        q(
            "INSERT INTO bookings (shop_id, code, staff_id, customer_name, customer_phone, start_at, end_at, total_sen, status, source, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)",
            [$shopId, random_code(6), $sid, $names[mt_rand(0, count($names) - 1)], '6011' . mt_rand(1000000, 9999999),
             $start, $end, $svc['price_sen'], $status, mt_rand(1, 4) === 1 ? 'walkin' : 'online', $created]
        );
        $bid = (int)db()->lastInsertId();
        q('INSERT INTO booking_services (booking_id, service_id, name, price_sen, duration_min) VALUES (?,?,?,?,?)',
            [$bid, $svc['id'], $svc['name'], $svc['price_sen'], $svc['duration_min']]);
        $n++;
    }
}
echo "Kedai demo siap: " . url('demo') . "\nLog masuk pemilik demo: demo@slotkita.test / $demoPass\nTempahan contoh: $n\n";
