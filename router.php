<?php
// Penghala untuk pelayan pembangunan PHP (php -S localhost:8120 router.php).
// Di hosting Apache, .htaccess melakukan kerja yang sama - fail ini tidak digunakan.
$path = rawurldecode((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

// Folder dalaman tidak boleh dicapai dari pelayar
if (preg_match('#^/(lib|includes|storage|database|tools)(/|$)#', $path)
    || preg_match('#^/(config|config\.sample|bootstrap|router)\.php$#', $path)
    || preg_match('#^/uploads/.*\.(php|phtml|phar)#i', $path)) {
    http_response_code(403);
    exit('403');
}

$file = __DIR__ . $path;
if ($path !== '/' && is_file($file)) {
    return false; // fail sebenar - biar pelayan hidang
}
if (is_dir($file) && is_file(rtrim($file, '/') . '/index.php')) {
    if (!str_ends_with($path, '/')) {
        header('Location: ' . $path . '/');
        exit;
    }
    return false;
}
// /nama-kedai  ->  kedai.php?k=nama-kedai
if (preg_match('#^/([a-z0-9][a-z0-9-]{1,39})/?$#', $path, $m)) {
    $_GET['k'] = $m[1];
    $_SERVER['SCRIPT_NAME'] = '/kedai.php';
    require __DIR__ . '/kedai.php';
    return true;
}
http_response_code(404);
require __DIR__ . '/404.php';
