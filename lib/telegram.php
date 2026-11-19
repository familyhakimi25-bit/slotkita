<?php
declare(strict_types=1);

/** Panggil Bot API. Senyap jika bot belum disetkan. Timeout pendek supaya tempahan tak tergantung. */
function tg_api(string $method, array $params): ?array
{
    $token = (string)cfg('telegram.bot_token');
    if ($token === '' || !function_exists('curl_init')) {
        return null;
    }
    $ch = curl_init("https://api.telegram.org/bot{$token}/{$method}");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $params,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT        => 5,
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    $data = is_string($res) ? json_decode($res, true) : null;
    if (!is_array($data) || empty($data['ok'])) {
        error_log("Telegram $method gagal: " . ($err ?: (string)$res));
        return null;
    }
    return $data;
}

function tg_send(string $chatId, string $text): bool
{
    return $chatId !== '' && tg_api('sendMessage', ['chat_id' => $chatId, 'text' => $text, 'disable_web_page_preview' => 'true']) !== null;
}

function tg_admin(string $text): void
{
    $id = (string)cfg('telegram.admin_chat_id');
    if ($id !== '') {
        tg_send($id, $text);
    }
}

function tg_enabled(): bool
{
    return cfg('telegram.bot_token') !== '' && cfg('telegram.bot_username') !== '';
}

/** Pautan t.me untuk pemilik sambung akaun Telegram kepada kedai */
function tg_connect_link(array $shop): string
{
    return 'https://t.me/' . cfg('telegram.bot_username') . '?start=' . $shop['tg_token'];
}
