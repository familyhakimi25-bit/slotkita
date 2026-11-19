<?php
// Tetapkan semula kata laluan mana-mana akaun (jika anda terkunci):
//   php tools/reset-password.php emel@contoh.com KataLaluanBaru
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../bootstrap.php';

[$_, $email, $pass] = array_pad($argv, 3, null);
if (!$email || !$pass || strlen($pass) < 8) {
    exit("Guna: php tools/reset-password.php emel@contoh.com KataLaluanBaru(min 8)\n");
}
$n = q('UPDATE users SET password_hash = ?, must_change = 0 WHERE email = ?', [password_hash($pass, PASSWORD_DEFAULT), $email])->rowCount();
q('DELETE FROM login_attempts WHERE email = ?', [$email]);
echo $n ? "Siap.\n" : "Emel tidak dijumpai.\n";
