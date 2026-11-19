<?php
require __DIR__ . '/_boot.php';
$me = current_user();
if (!empty($_SESSION['as_shop'])) {
    flash('Akaun pemilik tidak boleh diubah semasa admin melihat kedai. Guna Admin → Kedai → Reset kata laluan.', 'warn');
    redirect('./');
}
include ROOT . '/includes/account_form.php';
