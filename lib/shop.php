<?php
declare(strict_types=1);

/** Slug yang tidak boleh diambil kedai (bertembung dengan halaman sistem) */
const RESERVED_SLUGS = [
    'admin', 'app', 'api', 'assets', 'uploads', 'storage', 'lib', 'includes', 'database', 'tools',
    'daftar', 'masuk', 'keluar', 'semak', 'harga', 'lupa', 'terma', 'privasi', 'www', 'mail', 'ftp',
    'cpanel', 'webmail', 'blog', 'help', 'bantuan', 'support', 'slotkita', 'kedai', 'index', 'login',
    'router', 'bootstrap', 'config', 'favicon', 'robots', 'sitemap',
];

/** Pulangkan mesej ralat, atau '' jika slug boleh digunakan. */
function slug_problem(string $slug, int $exceptShopId = 0): string
{
    if (!preg_match('/^[a-z0-9][a-z0-9-]{2,39}$/', $slug) || str_ends_with($slug, '-')) {
        return 'Pautan mesti 3-40 aksara: huruf kecil, nombor dan sengkang (-) sahaja.';
    }
    if (in_array($slug, RESERVED_SLUGS, true)) {
        return 'Pautan ini dikhaskan. Cuba nama lain.';
    }
    if (val('SELECT 1 FROM shops WHERE slug = ? AND id <> ?', [$slug, $exceptShopId])) {
        return 'Pautan ini sudah diambil kedai lain.';
    }
    return '';
}

const SAMPLE_SERVICES = [
    'barber'  => [['Gunting rambut', 30, 1800], ['Gunting + cuci', 45, 2500], ['Fade / skin fade', 45, 3000], ['Cukur & rapi janggut', 15, 1000], ['Kanak-kanak (bawah 12)', 20, 1200]],
    'salon'   => [['Potong & blow', 60, 4500], ['Cuci & blow', 40, 3000], ['Warna rambut', 120, 15000], ['Rawatan rambut', 60, 8000]],
    'spa'     => [['Urut badan penuh', 60, 9000], ['Urut kaki refleksologi', 45, 5000], ['Facial asas', 60, 7000]],
    'carwash' => [['Cuci luar', 30, 1500], ['Cuci luar + vakum', 45, 2500], ['Polish & wax', 120, 12000]],
    'klinik'  => [['Konsultasi', 30, 5000], ['Rawatan susulan', 30, 4000]],
    'studio'  => [['Sesi 30 minit', 30, 8000], ['Sesi 1 jam', 60, 15000]],
    'lain'    => [['Servis standard', 30, 3000], ['Servis premium', 60, 6000]],
];

/**
 * Cipta kedai baru lengkap: waktu operasi lalai, seorang staf, servis contoh, akaun pemilik.
 * Pulangkan [shopId, userId].
 */
function create_shop(array $d): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $trial = (int)cfg('trial_days', 14);
        q(
            'INSERT INTO shops (slug, name, category, phone, city, tagline, trial_until, tg_token, referred_by) VALUES (?,?,?,?,?,?,?,?,?)',
            [
                $d['slug'], $d['shop_name'], $d['category'], $d['phone'] ?: null, $d['city'] ?? null,
                $d['tagline'] ?? null,
                $trial > 0 ? date('Y-m-d', strtotime("+$trial days")) : null,
                bin2hex(random_bytes(8)),
                $d['referred_by'] ?? null,
            ]
        );
        $shopId = (int)$pdo->lastInsertId();

        // Isnin-Sabtu 10 pg - 9 mlm, Ahad 11 pg - 6 ptg. Pemilik ubah di panel.
        for ($w = 0; $w < 7; $w++) {
            [$o, $c] = $w === 0 ? ['11:00:00', '18:00:00'] : ['10:00:00', '21:00:00'];
            q('INSERT INTO hours (shop_id, weekday, open_time, close_time) VALUES (?,?,?,?)', [$shopId, $w, $o, $c]);
        }
        q('INSERT INTO staff (shop_id, name, title, sort) VALUES (?,?,?,0)', [$shopId, $d['staff_name'] ?? $d['owner_name'], 'Pemilik']);
        foreach (SAMPLE_SERVICES[$d['category']] ?? SAMPLE_SERVICES['lain'] as $i => [$n, $m, $p]) {
            q('INSERT INTO services (shop_id, name, duration_min, price_sen, sort) VALUES (?,?,?,?,?)', [$shopId, $n, $m, $p, $i]);
        }
        q(
            "INSERT INTO users (shop_id, name, email, phone, password_hash, role) VALUES (?,?,?,?,?,'owner')",
            [$shopId, $d['owner_name'], $d['email'], $d['phone'] ?: null, password_hash($d['password'], PASSWORD_DEFAULT)]
        );
        $userId = (int)$pdo->lastInsertId();
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }
    return [$shopId, $userId];
}

/** Simpan ?ref=<slug> dalam kuki 30 hari (dari label "Dikuasakan oleh" / pautan jemputan) */
function capture_referral(): void
{
    $ref = strtolower((string)($_GET['ref'] ?? ''));
    if ($ref !== '' && preg_match('/^[a-z0-9-]{3,40}$/', $ref) && val('SELECT 1 FROM shops WHERE slug = ?', [$ref])) {
        setcookie('sk_ref', $ref, [
            'expires' => time() + 30 * 86400, 'path' => '/', 'secure' => cfg('env') === 'production',
            'httponly' => true, 'samesite' => 'Lax',
        ]);
        $_COOKIE['sk_ref'] = $ref;
    }
}

function referral_link(array $shop): string
{
    return url('?ref=' . $shop['slug']);
}

/** Pautan awam kedai, cth http://localhost:8120/tokdin */
function shop_url(array $shop): string
{
    return url($shop['slug']);
}

function upload_url(?string $file): string
{
    return $file ? url('uploads/' . $file) : '';
}

/* ---------- Warna jenama: pastikan teks sentiasa boleh dibaca ---------- */

function hex_rgb(string $hex): array
{
    $hex = ltrim($hex, '#');
    return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
}

function luminance(array $rgb): float
{
    $c = array_map(function ($v) {
        $v /= 255;
        return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
    }, $rgb);
    return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
}

function contrast(array $a, array $b): float
{
    [$l1, $l2] = [luminance($a), luminance($b)];
    return (max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05);
}

/** Warna teks (gelap/putih) yang paling jelas di atas warna jenama */
function ink_on(string $hex): string
{
    $rgb = hex_rgb($hex);
    return contrast($rgb, [22, 19, 15]) >= contrast($rgb, [255, 255, 255]) ? '#16130f' : '#ffffff';
}

/** Versi warna jenama yang cukup gelap untuk teks atas latar putih (>= 4.5:1) */
function readable_on_white(string $hex): string
{
    [$r, $g, $b] = hex_rgb($hex);
    for ($i = 0; $i < 20 && contrast([$r, $g, $b], [255, 255, 255]) < 4.5; $i++) {
        [$r, $g, $b] = [(int)($r * .88), (int)($g * .88), (int)($b * .88)];
    }
    return sprintf('#%02x%02x%02x', $r, $g, $b);
}

function shop_color(array $shop): string
{
    return plan_has($shop, 'branding') && preg_match('/^#[0-9a-fA-F]{6}$/', $shop['color']) ? $shop['color'] : '#FF5A1F';
}

/** Status buka/tutup sekarang untuk pil di halaman kedai */
function open_now(array $shop): array
{
    $hours = shop_hours((int)$shop['id']);
    $now = time();
    $today = date('Y-m-d');
    $h = $hours[(int)date('w')];
    $closedToday = (bool)val('SELECT 1 FROM closures WHERE shop_id = ? AND day = ?', [$shop['id'], $today]);
    if ($h && !$closedToday) {
        $o = strtotime("$today {$h['open']}");
        $c = strtotime("$today {$h['close']}");
        if ($now >= $o && $now < $c) {
            return ['open' => true, 'text' => 'Buka sekarang · tutup ' . masa($h['close'])];
        }
        if ($now < $o) {
            return ['open' => false, 'text' => 'Buka hari ini ' . masa($h['open'])];
        }
    }
    for ($i = 1; $i <= 7; $i++) {
        $d = date('Y-m-d', strtotime("+$i days"));
        $hh = $hours[(int)date('w', strtotime($d))];
        if ($hh && !val('SELECT 1 FROM closures WHERE shop_id = ? AND day = ?', [$shop['id'], $d])) {
            return ['open' => false, 'text' => 'Tutup · buka ' . ($i === 1 ? 'esok' : HARI[(int)date('w', strtotime($d))]) . ' ' . masa($hh['open'])];
        }
    }
    return ['open' => false, 'text' => 'Tutup'];
}
