<?php
declare(strict_types=1);

function cfg(string $key, $default = null)
{
    $v = $GLOBALS['CFG'];
    foreach (explode('.', $key) as $k) {
        if (!is_array($v) || !array_key_exists($k, $v)) {
            return $default;
        }
        $v = $v[$k];
    }
    return $v;
}

function e($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** URL penuh dari laluan relatif, cth url('app/') */
function url(string $path = ''): string
{
    return rtrim((string)cfg('app_url'), '/') . '/' . ltrim($path, '/');
}

/** Laluan relatif ke akar laman dari halaman semasa (berfungsi dalam subfolder app/ admin/) */
function base(): string
{
    return defined('IN_SUB') ? '../' : '';
}

function redirect(string $to): void
{
    header('Location: ' . $to);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

/* ---------------- CSRF ---------------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function check_csrf(): void
{
    $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        exit('Sesi tamat atau borang tidak sah. Muat semula halaman dan cuba lagi.');
    }
}

/* ---------------- Mesej sekali papar ---------------- */

function flash(string $msg, string $type = 'ok'): void
{
    $_SESSION['flash'][] = ['msg' => $msg, 'type' => $type];
}

function flashes(): string
{
    $out = '';
    foreach ($_SESSION['flash'] ?? [] as $f) {
        $out .= '<div class="alert alert-' . e($f['type']) . '" role="status">' . e($f['msg']) . '</div>';
    }
    unset($_SESSION['flash']);
    return $out;
}

/* ---------------- Format ---------------- */

function rm(int $sen, bool $symbol = true): string
{
    $s = number_format($sen / 100, $sen % 100 === 0 ? 0 : 2);
    return $symbol ? 'RM' . $s : $s;
}

/** "25.50" / "25" -> 2550 sen */
function to_sen(string $s): int
{
    $s = str_replace([',', 'RM', 'rm', ' '], '', trim($s));
    if (!preg_match('/^\d{1,6}(\.\d{1,2})?$/', $s)) {
        return -1;
    }
    return (int)round(((float)$s) * 100);
}

const HARI = ['Ahad', 'Isnin', 'Selasa', 'Rabu', 'Khamis', 'Jumaat', 'Sabtu'];
const HARI_PENDEK = ['Ahd', 'Isn', 'Sel', 'Rab', 'Kha', 'Jum', 'Sab'];
const BULAN = ['Jan', 'Feb', 'Mac', 'Apr', 'Mei', 'Jun', 'Jul', 'Ogo', 'Sep', 'Okt', 'Nov', 'Dis'];
const BULAN_PENUH = ['Januari', 'Februari', 'Mac', 'April', 'Mei', 'Jun', 'Julai', 'Ogos', 'September', 'Oktober', 'November', 'Disember'];

/** 2026-09-26 -> "Sabtu, 26 Sep 2026" */
function tarikh(string $ymd, bool $withDay = true): string
{
    $t = strtotime($ymd);
    $s = date('j', $t) . ' ' . BULAN[(int)date('n', $t) - 1] . ' ' . date('Y', $t);
    return $withDay ? HARI[(int)date('w', $t)] . ', ' . $s : $s;
}

/** "14:30:00" -> "2:30 PTG" */
function masa(string $hms): string
{
    $t = strtotime('2000-01-01 ' . $hms);
    $h = (int)date('G', $t);
    $suffix = $h < 12 ? 'PG' : ($h < 19 ? 'PTG' : 'MLM');
    return date('g:i', $t) . ' ' . $suffix;
}

/** Nombor telefon Malaysia -> "60123456789", atau '' jika tidak sah */
function norm_phone(string $p): string
{
    $d = preg_replace('/\D+/', '', $p);
    if (str_starts_with($d, '0')) {
        $d = '6' . $d;
    } elseif (str_starts_with($d, '1')) {
        $d = '60' . $d;
    }
    return preg_match('/^60\d{8,10}$/', $d) ? $d : '';
}

/** "60123456789" -> "012-345 6789" */
function show_phone(?string $p): string
{
    $p = (string)$p;
    if (!str_starts_with($p, '60')) {
        return $p;
    }
    $local = '0' . substr($p, 2);
    return substr($local, 0, 3) . '-' . substr($local, 3, 3) . ' ' . substr($local, 6);
}

function wa_link(string $phone, string $text = ''): string
{
    return 'https://wa.me/' . preg_replace('/\D+/', '', $phone) . ($text !== '' ? '?text=' . rawurlencode($text) : '');
}

function slugify(string $s): string
{
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim(substr((string)$s, 0, 40), '-');
}

function json_out($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function random_code(int $len = 8): string
{
    $abc = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // tiada 0/O/1/I untuk elak keliru
    $out = '';
    for ($i = 0; $i < $len; $i++) {
        $out .= $abc[random_int(0, strlen($abc) - 1)];
    }
    return $out;
}

/**
 * Simpan gambar yang dimuat naik. Pulangkan nama fail (dalam uploads/) atau lempar RuntimeException.
 * Semakan: saiz, jenis sebenar (getimagesize), sambungan dipaksa dari jenis sebenar.
 */
function save_upload(array $file, string $prefix, int $maxBytes = 3_000_000, string $dir = 'uploads'): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Muat naik gagal. Cuba lagi.');
    }
    if ($file['size'] > $maxBytes) {
        throw new RuntimeException('Fail terlalu besar (had ' . round($maxBytes / 1_000_000, 1) . 'MB).');
    }
    $info = @getimagesize($file['tmp_name']);
    $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$info[2] ?? 0] ?? null;
    if (!$info || !$ext) {
        throw new RuntimeException('Hanya gambar JPG, PNG atau WEBP diterima.');
    }
    $name = $prefix . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], ROOT . '/' . $dir . '/' . $name)) {
        throw new RuntimeException('Tidak dapat simpan fail.');
    }
    return $name;
}

function delete_upload(?string $name): void
{
    if ($name && preg_match('/^[a-z0-9-]+\.(jpg|png|webp)$/', $name) && is_file(ROOT . '/uploads/' . $name)) {
        @unlink(ROOT . '/uploads/' . $name);
    }
}

/** Nama kategori perniagaan */
const KATEGORI = [
    'barber' => 'Kedai gunting / Barber',
    'salon'  => 'Salon & kecantikan',
    'spa'    => 'Spa & urut',
    'carwash'=> 'Cuci kereta / Detailing',
    'klinik' => 'Klinik / Terapi',
    'studio' => 'Studio foto / Tatu',
    'lain'   => 'Lain-lain',
];
