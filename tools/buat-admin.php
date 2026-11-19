<?php
// Cipta / kemas kini akaun ADMIN PLATFORM (anda). Jalankan dari terminal sahaja:
//   php tools/buat-admin.php emel@anda.com KataLaluanKuat "Nama Anda"
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../bootstrap.php';

[$_, $email, $pass, $name] = array_pad($argv, 4, null);
if (!$email || !$pass || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 8) {
    exit("Guna: php tools/buat-admin.php emel@anda.com KataLaluan(min 8) \"Nama\"\n");
}
$hash = password_hash($pass, PASSWORD_DEFAULT);
$u = row('SELECT id, role FROM users WHERE email = ?', [$email]);
if ($u && $u['role'] !== 'superadmin') {
    exit("Emel ini milik pemilik kedai. Guna emel lain.\n");
}
if ($u) {
    q('UPDATE users SET password_hash = ?, must_change = 0 WHERE id = ?', [$hash, $u['id']]);
    echo "Kata laluan admin dikemas kini.\n";
} else {
    q("INSERT INTO users (shop_id, name, email, password_hash, role) VALUES (NULL, ?, ?, ?, 'superadmin')", [$name ?: 'Admin', $email, $hash]);
    echo "Admin platform dicipta: $email\n";
}
