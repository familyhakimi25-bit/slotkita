<?php
// Webhook bot Telegram: pemilik kedai tekan START melalui pautan t.me/<bot>?start=<tg_token>
require __DIR__ . '/../bootstrap.php';
require ROOT . '/lib/telegram.php';

$secret = (string)cfg('telegram.webhook_secret');
if ($secret === '' || !hash_equals($secret, (string)($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? ''))) {
    http_response_code(403);
    exit;
}
$u = json_decode((string)file_get_contents('php://input'), true);
$msg = $u['message'] ?? null;
$chatId = isset($msg['chat']['id']) ? (string)$msg['chat']['id'] : '';
$text = trim((string)($msg['text'] ?? ''));
if ($chatId === '' || $text === '') {
    exit('ok');
}

if (preg_match('/^\/start\s+([a-f0-9]{16})$/', $text, $m)) {
    $shop = row('SELECT id, name FROM shops WHERE tg_token = ?', [$m[1]]);
    if ($shop) {
        q('UPDATE shops SET tg_chat_id = ? WHERE id = ?', [$chatId, $shop['id']]);
        tg_send($chatId, "✅ Disambungkan!\nNotifikasi tempahan baru untuk {$shop['name']} akan dihantar ke sini.\n\nTaip /stop untuk berhenti.");
    } else {
        tg_send($chatId, 'Pautan tidak sah atau sudah lama. Buka Panel → Notifikasi dan tekan "Sambung Telegram" semula.');
    }
} elseif ($text === '/stop') {
    $n = q('UPDATE shops SET tg_chat_id = NULL WHERE tg_chat_id = ?', [$chatId])->rowCount();
    tg_send($chatId, $n ? 'Notifikasi dihentikan.' : 'Tiada kedai disambungkan ke chat ini.');
} else {
    tg_send($chatId, 'Hai! Untuk sambung kedai anda, buka Panel ' . cfg('app_name') . ' → Notifikasi → "Sambung Telegram".');
}
echo 'ok';
