<?php
// Daftar webhook bot Telegram SELEPAS laman berada di https (hosting):
//   php tools/telegram-webhook.php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../bootstrap.php';
require ROOT . '/lib/telegram.php';

if (!tg_enabled()) {
    exit("Isi telegram.bot_token dan telegram.bot_username dalam config.php dahulu.\n");
}
$res = tg_api('setWebhook', [
    'url'             => url('api/telegram.php'),
    'secret_token'    => cfg('telegram.webhook_secret'),
    'allowed_updates' => json_encode(['message']),
]);
echo $res ? "Webhook didaftar ke " . url('api/telegram.php') . "\n" : "Gagal - semak storage/logs/php-error.log\n";
