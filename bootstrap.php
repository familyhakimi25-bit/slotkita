<?php
// Fail pertama yang dimuatkan oleh SETIAP halaman.
declare(strict_types=1);

define('ROOT', __DIR__);

if (!is_file(ROOT . '/config.php')) {
    http_response_code(500);
    exit('config.php tiada. Salin config.sample.php ke config.php dan isi.');
}
$GLOBALS['CFG'] = require ROOT . '/config.php';
require ROOT . '/lib/helpers.php';

date_default_timezone_set('Asia/Kuala_Lumpur');
mb_internal_encoding('UTF-8');

$isProd = cfg('env') === 'production';
error_reporting(E_ALL);
ini_set('display_errors', $isProd ? '0' : '1');
ini_set('log_errors', '1');
ini_set('error_log', ROOT . '/storage/logs/php-error.log');

// Paksa https di production
if ($isProd && PHP_SAPI !== 'cli' && empty($_SERVER['HTTPS']) && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') !== 'https') {
    header('Location: https://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
    exit;
}

if (PHP_SAPI !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    if (!defined('ALLOW_FRAME')) {
        header('X-Frame-Options: SAMEORIGIN');
    }
    session_save_path(ROOT . '/storage/sessions');
    session_name('sk_sess');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $isProd,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.gc_maxlifetime', (string)(60 * 60 * 12));
    session_start();
}

require ROOT . '/lib/db.php';
require ROOT . '/lib/plans.php';
require ROOT . '/lib/auth.php';
require ROOT . '/lib/icons.php';
