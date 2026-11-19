<?php
// Dimuatkan oleh setiap halaman dalam admin/ (admin platform - anda)
define('IN_SUB', true);
require __DIR__ . '/../bootstrap.php';
require_once ROOT . '/lib/shop.php';
require_once ROOT . '/lib/booking.php';

$me = require_superadmin();
$area = 'admin';
