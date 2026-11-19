<?php
require __DIR__ . '/bootstrap.php';
if (is_post()) {
    check_csrf();
    // Admin yang "masuk sebagai kedai" - kembali ke panel admin, bukan log keluar
    if (!empty($_POST['stop_as']) && is_superadmin()) {
        unset($_SESSION['as_shop']);
        redirect(url('admin/kedai.php'));
    }
    logout();
}
redirect(url('masuk.php?keluar=1'));
