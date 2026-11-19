<?php
// GET ?s=slug -> {"ok":true} atau {"ok":false,"error":"..."}  (semakan langsung semasa daftar)
require __DIR__ . '/../bootstrap.php';
require ROOT . '/lib/shop.php';

$shop = current_shop();
$problem = slug_problem(strtolower(trim((string)($_GET['s'] ?? ''))), $shop ? (int)$shop['id'] : 0);
json_out($problem === '' ? ['ok' => true] : ['ok' => false, 'error' => $problem]);
