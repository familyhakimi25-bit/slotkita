<?php
// Salin fail ini ke config.php dan isi. JANGAN kongsi config.php (ada kunci rahsia).
return [
    'env'      => 'production',            // 'development' = papar ralat, 'production' = sorok ralat
    'app_name' => 'SlotKita',
    'app_url'  => 'https://slotkita.my',    // tanpa garis miring di hujung

    'db_host' => 'localhost',
    'db_port' => 3306,
    'db_name' => 'nama_db',
    'db_user' => 'pengguna_db',
    'db_pass' => 'kata_laluan_db',

    // Hubungan platform (dipapar di laman utama & halaman lupa kata laluan)
    'support_whatsapp' => '60123456789',
    'support_email'    => 'hello@slotkita.my',

    // Hari percubaan percuma (pelan Pro) untuk kedai baru
    'trial_days' => 14,

    // Gerbang bayaran: 'toyyibpay' (sebenar) atau 'mock' (ujian di komputer sendiri SAHAJA)
    'payment_driver' => 'toyyibpay',
    'toyyibpay' => [
        'sandbox'       => false,          // true = dev.toyyibpay.com
        'secret_key'    => '',             // Dashboard ToyyibPay > Settings > User Secret Key
        'category_code' => '',             // jana dengan: php tools/toyyibpay-kategori.php
        'charge_customer' => false,        // true = caj FPX ditanggung pembayar
    ],

    // Pindahan bank manual (sandaran bila FPX gagal)
    'bank' => [
        'name'   => 'Maybank',
        'number' => '000000000000',
        'holder' => 'NAMA ANDA',
    ],

    // Notifikasi Telegram (percuma). Cipta bot melalui @BotFather.
    'telegram' => [
        'bot_token'     => '',
        'bot_username'  => '',             // tanpa @
        'webhook_secret'=> '',             // rentetan rawak, cth: bin2hex(random_bytes(16))
        'admin_chat_id' => '',             // chat ID anda sendiri untuk notifikasi bayaran masuk
    ],
];
