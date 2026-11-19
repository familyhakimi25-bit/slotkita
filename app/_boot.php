<?php
// Dimuatkan oleh setiap halaman dalam app/ (panel pemilik kedai)
define('IN_SUB', true);
require __DIR__ . '/../bootstrap.php';
require_once ROOT . '/lib/shop.php';
require_once ROOT . '/lib/booking.php';

$shop = require_owner();
$area = 'app';
