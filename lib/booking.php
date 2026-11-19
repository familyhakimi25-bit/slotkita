<?php
declare(strict_types=1);

/** Ralat yang mesejnya selamat dipapar kepada pelanggan. */
class BookingError extends RuntimeException {}

const STATUS = [
    'menunggu'    => 'Menunggu',
    'disahkan'    => 'Disahkan',
    'selesai'     => 'Selesai',
    'tidak_hadir' => 'Tidak hadir',
    'batal'       => 'Batal',
];
/** Status yang memegang slot (orang lain tak boleh tempah masa sama) */
const HOLDING = ['menunggu', 'disahkan'];

function shop_by_slug(string $slug): ?array
{
    if (!preg_match('/^[a-z0-9][a-z0-9-]{1,39}$/', $slug)) {
        return null;
    }
    return row('SELECT * FROM shops WHERE slug = ?', [$slug]);
}

/** [weekday => ['open'=>'10:00:00','close'=>'21:00:00'] | null] */
function shop_hours(int $shopId): array
{
    $out = array_fill(0, 7, null);
    foreach (rows('SELECT * FROM hours WHERE shop_id = ?', [$shopId]) as $h) {
        if ($h['open_time'] !== null && $h['close_time'] !== null) {
            $out[(int)$h['weekday']] = ['open' => $h['open_time'], 'close' => $h['close_time']];
        }
    }
    return $out;
}

/** Staf yang boleh ditempah - dihadkan ikut pelan (staf pertama ikut susunan). */
function bookable_staff(array $shop): array
{
    $limit = (int)PLANS[shop_plan($shop)]['staff'];
    return rows("SELECT * FROM staff WHERE shop_id = ? AND active = 1 ORDER BY sort, id LIMIT $limit", [$shop['id']]);
}

function active_services(int $shopId): array
{
    return rows('SELECT * FROM services WHERE shop_id = ? AND active = 1 ORDER BY sort, id', [$shopId]);
}

function closed_dates(int $shopId, string $from, string $to): array
{
    $out = [];
    foreach (rows('SELECT day, note FROM closures WHERE shop_id = ? AND day BETWEEN ? AND ?', [$shopId, $from, $to]) as $c) {
        $out[$c['day']] = $c['note'] ?: 'Cuti';
    }
    return $out;
}

/** Senarai hari yang boleh ditempah: [['date'=>..., 'open'=>bool, 'note'=>...], ...] */
function booking_days(array $shop): array
{
    $hours = shop_hours((int)$shop['id']);
    $today = date('Y-m-d');
    $last = date('Y-m-d', strtotime("+{$shop['max_days']} days"));
    $closed = closed_dates((int)$shop['id'], $today, $last);
    $days = [];
    for ($i = 0; $i <= (int)$shop['max_days']; $i++) {
        $d = date('Y-m-d', strtotime("+$i days"));
        $w = (int)date('w', strtotime($d));
        $open = $hours[$w] !== null && !isset($closed[$d]);
        $days[] = ['date' => $d, 'open' => $open, 'note' => $closed[$d] ?? ($hours[$w] ? '' : 'Tutup')];
    }
    return $days;
}

/**
 * Slot kosong untuk satu hari.
 * Pulangkan ['10:00' => [staffId, ...], ...] - staf yang bebas pada setiap masa mula.
 */
function free_slots(array $shop, string $date, int $duration, int $staffId = 0): array
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $duration < 5) {
        return [];
    }
    $w = (int)date('w', strtotime($date));
    $hours = shop_hours((int)$shop['id'])[$w];
    if ($hours === null || val('SELECT 1 FROM closures WHERE shop_id = ? AND day = ?', [$shop['id'], $date])) {
        return [];
    }
    $today = date('Y-m-d');
    if ($date < $today || $date > date('Y-m-d', strtotime("+{$shop['max_days']} days"))) {
        return [];
    }

    $staff = array_filter(bookable_staff($shop), function ($s) use ($w, $staffId) {
        $off = $s['off_days'] === '' ? [] : array_map('intval', explode(',', $s['off_days']));
        return !in_array($w, $off, true) && ($staffId === 0 || (int)$s['id'] === $staffId);
    });
    if (!$staff) {
        return [];
    }

    // Tempahan sedia ada hari itu, dikumpul ikut staf
    $busy = [];
    $ids = array_map(fn($s) => (int)$s['id'], $staff);
    $in = implode(',', array_fill(0, count($ids), '?'));
    $list = rows(
        "SELECT staff_id, start_at, end_at FROM bookings
         WHERE staff_id IN ($in) AND status IN ('menunggu','disahkan') AND start_at < ? AND end_at > ?",
        array_merge($ids, [$date . ' 23:59:59', $date . ' 00:00:00'])
    );
    foreach ($list as $b) {
        $busy[(int)$b['staff_id']][] = [strtotime($b['start_at']), strtotime($b['end_at'])];
    }

    $open = strtotime("$date {$hours['open']}");
    $close = strtotime("$date {$hours['close']}");
    $earliest = time() + (int)$shop['lead_minutes'] * 60;
    $step = max(5, (int)$shop['slot_step']) * 60;
    $len = $duration * 60;

    $slots = [];
    for ($t = $open; $t + $len <= $close; $t += $step) {
        if ($t < $earliest) {
            continue;
        }
        foreach ($ids as $sid) {
            $clash = false;
            foreach ($busy[$sid] ?? [] as [$bs, $be]) {
                if ($t < $be && $t + $len > $bs) {
                    $clash = true;
                    break;
                }
            }
            if (!$clash) {
                $slots[date('H:i', $t)][] = $sid;
            }
        }
    }
    return $slots;
}

/** Servis terpilih milik kedai ini - [senarai, jumlah minit, jumlah sen] */
function pick_services(int $shopId, array $serviceIds): array
{
    $serviceIds = array_values(array_unique(array_filter(array_map('intval', $serviceIds))));
    if (!$serviceIds || count($serviceIds) > 10) {
        throw new BookingError('Sila pilih sekurang-kurangnya satu servis.');
    }
    $in = implode(',', array_fill(0, count($serviceIds), '?'));
    $list = rows("SELECT * FROM services WHERE shop_id = ? AND active = 1 AND id IN ($in) ORDER BY sort, id", array_merge([$shopId], $serviceIds));
    if (count($list) !== count($serviceIds)) {
        throw new BookingError('Servis yang dipilih tidak lagi ditawarkan. Muat semula halaman.');
    }
    $mins = array_sum(array_column($list, 'duration_min'));
    $sen = array_sum(array_column($list, 'price_sen'));
    return [$list, (int)$mins, (int)$sen];
}

/**
 * Cipta tempahan. $staffId 0 = "sesiapa". Kunci baris staf supaya dua orang
 * tidak dapat slot yang sama walaupun tekan serentak.
 */
function create_booking(array $shop, array $serviceIds, int $staffId, string $date, string $time, string $name, string $phone, string $note = '', string $source = 'online', ?string $status = null): array
{
    $name = trim(preg_replace('/\s+/', ' ', $name));
    $phoneN = norm_phone($phone);
    if (mb_strlen($name) < 2 || mb_strlen($name) > 60) {
        throw new BookingError('Sila isi nama anda.');
    }
    // Walk-in boleh tanpa nombor telefon; tempahan online wajib
    if ($phoneN === '' && !($source === 'walkin' && trim($phone) === '')) {
        throw new BookingError('Nombor telefon tidak sah. Contoh: 012-345 6789');
    }
    if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
        throw new BookingError('Sila pilih masa.');
    }
    [$services, $mins, $sen] = pick_services((int)$shop['id'], $serviceIds);

    if ($source === 'online') {
        if ($shop['status'] !== 'aktif') {
            throw new BookingError('Tempahan online kedai ini ditutup buat sementara.');
        }
        if (booking_quota_left($shop) === 0) {
            throw new BookingError('Tempahan online kedai ini penuh untuk bulan ini. Sila hubungi kedai terus.');
        }
        $active = (int)val(
            "SELECT COUNT(*) FROM bookings WHERE shop_id = ? AND customer_phone = ? AND status IN ('menunggu','disahkan') AND start_at > NOW()",
            [$shop['id'], $phoneN]
        );
        if ($active >= 3) {
            throw new BookingError('Nombor ini sudah ada 3 tempahan aktif. Batalkan satu dahulu atau hubungi kedai.');
        }
        $recent = (int)val('SELECT COUNT(*) FROM bookings WHERE ip = ? AND created_at > ?', [client_ip(), date('Y-m-d H:i:s', time() - 3600)]);
        if ($recent >= 10) {
            throw new BookingError('Terlalu banyak tempahan dari peranti ini. Cuba sebentar lagi.');
        }
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        q('SELECT id FROM staff WHERE shop_id = ? FOR UPDATE', [$shop['id']]);
        $slots = free_slots($shop, $date, $mins, $staffId);
        if ($source === 'walkin' && !isset($slots[$time])) {
            // Walk-in boleh diletak di luar grid slot selagi tidak bertindih
            $slots = walkin_slot($shop, $date, $time, $mins, $staffId);
        }
        if (empty($slots[$time])) {
            throw new BookingError('Maaf, slot ' . $time . ' baru sahaja diambil. Sila pilih masa lain.', 409);
        }
        $chosen = $slots[$time][0];
        if (count($slots[$time]) > 1) {
            // Sesiapa: beri kepada staf paling kurang tempahan hari itu
            $in = implode(',', array_fill(0, count($slots[$time]), '?'));
            $load = [];
            foreach (rows(
                "SELECT staff_id, COUNT(*) n FROM bookings WHERE staff_id IN ($in) AND DATE(start_at) = ? AND status IN ('menunggu','disahkan') GROUP BY staff_id",
                array_merge($slots[$time], [$date])
            ) as $r) {
                $load[(int)$r['staff_id']] = (int)$r['n'];
            }
            usort($slots[$time], fn($a, $b) => ($load[$a] ?? 0) <=> ($load[$b] ?? 0));
            $chosen = $slots[$time][0];
        }

        $start = "$date $time:00";
        $end = date('Y-m-d H:i:s', strtotime($start) + $mins * 60);
        $status = $status ?? ((int)$shop['auto_confirm'] === 1 ? 'disahkan' : 'menunggu');
        for ($try = 0; $try < 5; $try++) {
            $code = random_code(6);
            if (!val('SELECT 1 FROM bookings WHERE code = ?', [$code])) {
                break;
            }
        }
        q(
            'INSERT INTO bookings (shop_id, code, staff_id, customer_name, customer_phone, note, start_at, end_at, total_sen, status, source, ip)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
            [$shop['id'], $code, $chosen, $name, $phoneN, mb_substr(trim($note), 0, 200) ?: null, $start, $end, $sen, $status, $source, client_ip()]
        );
        $bid = (int)$pdo->lastInsertId();
        foreach ($services as $s) {
            q('INSERT INTO booking_services (booking_id, service_id, name, price_sen, duration_min) VALUES (?,?,?,?,?)',
                [$bid, $s['id'], $s['name'], $s['price_sen'], $s['duration_min']]);
        }
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }

    $b = booking_full($bid);
    if ($source === 'online') {
        notify_shop_new_booking($shop, $b);
    }
    return $b;
}

/** Semakan slot bebas untuk walk-in pada masa sebarang (bukan ikut grid). */
function walkin_slot(array $shop, string $date, string $time, int $mins, int $staffId): array
{
    $start = strtotime("$date $time:00");
    $end = $start + $mins * 60;
    $free = [];
    foreach (rows('SELECT id FROM staff WHERE shop_id = ? AND active = 1 ORDER BY sort, id', [$shop['id']]) as $s) {
        if ($staffId && (int)$s['id'] !== $staffId) {
            continue;
        }
        $clash = val(
            "SELECT 1 FROM bookings WHERE staff_id = ? AND status IN ('menunggu','disahkan') AND start_at < ? AND end_at > ?",
            [$s['id'], date('Y-m-d H:i:s', $end), date('Y-m-d H:i:s', $start)]
        );
        if (!$clash) {
            $free[] = (int)$s['id'];
        }
    }
    return $free ? [$time => $free] : [];
}

function booking_full(int $id): ?array
{
    $b = row('SELECT b.*, st.name AS staff_name FROM bookings b LEFT JOIN staff st ON st.id = b.staff_id WHERE b.id = ?', [$id]);
    if ($b) {
        $b['services'] = rows('SELECT * FROM booking_services WHERE booking_id = ?', [$id]);
    }
    return $b;
}

function booking_service_names(array $b): string
{
    return implode(', ', array_column($b['services'], 'name'));
}

/** Pautan "Tambah ke Google Calendar" */
function gcal_link(array $shop, array $b): string
{
    $fmt = fn($dt) => gmdate('Ymd\THis\Z', strtotime($dt));
    return 'https://calendar.google.com/calendar/render?' . http_build_query([
        'action'   => 'TEMPLATE',
        'text'     => booking_service_names($b) . ' - ' . $shop['name'],
        'dates'    => $fmt($b['start_at']) . '/' . $fmt($b['end_at']),
        'details'  => 'Kod tempahan: ' . $b['code'],
        'location' => trim(($shop['address'] ?? '') . ' ' . ($shop['city'] ?? '')),
    ]);
}

function notify_shop_new_booking(array $shop, array $b): void
{
    if (!$shop['tg_chat_id'] || !plan_has($shop, 'telegram')) {
        return;
    }
    require_once ROOT . '/lib/telegram.php';
    $t = strtotime($b['start_at']);
    tg_send($shop['tg_chat_id'], sprintf(
        "📅 Tempahan baru #%s\n👤 %s — %s\n🕒 %s, %s\n✂️ %s%s\n💵 %s\n%s%s",
        $b['code'],
        $b['customer_name'], show_phone($b['customer_phone']),
        tarikh(date('Y-m-d', $t)), masa(date('H:i:s', $t)),
        booking_service_names($b),
        $b['staff_name'] ? ' (' . $b['staff_name'] . ')' : '',
        rm((int)$b['total_sen']),
        $b['status'] === 'menunggu' ? '⏳ PERLU DISAHKAN di panel' : '✅ Disahkan automatik',
        $b['note'] ? "\n📝 " . $b['note'] : ''
    ));
}
