<?php

declare(strict_types=1);

/**
 * Sends a test message to the configured Telegram channel.
 * If TELEGRAM_CHAT_ID is empty, lists the chats the bot can currently see
 * (post a message in the channel first so it shows up in getUpdates).
 * CLI only:  php tools/test-telegram.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/mailer.php';

$token = env('TELEGRAM_BOT_TOKEN', '');
$chatId = env('TELEGRAM_CHAT_ID', '');

if ($token === '') {
    echo "TELEGRAM_BOT_TOKEN is not set in .env.\nCreate a bot with @BotFather and paste its token there.\n";
    exit(1);
}

if ($chatId === '') {
    echo "TELEGRAM_CHAT_ID is empty - looking up chats visible to the bot...\n";
    echo "(The bot must be an admin of the channel, and the channel needs at\n";
    echo "least one recent post for it to appear here.)\n\n";

    $ch = curl_init("https://api.telegram.org/bot{$token}/getUpdates");
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
    $response = curl_exec($ch);
    $data = is_string($response) ? json_decode($response, true) : null;

    if (!is_array($data) || empty($data['ok'])) {
        echo 'getUpdates failed: ' . ($response === false ? curl_error($ch) : $response) . "\n";
        exit(1);
    }

    $chats = [];
    foreach ($data['result'] as $update) {
        $msg = $update['channel_post'] ?? $update['message'] ?? null;
        if (isset($msg['chat']['id'])) {
            $chat = $msg['chat'];
            $chats[$chat['id']] = sprintf(
                '%s  (%s, "%s")',
                $chat['id'],
                $chat['type'] ?? '?',
                $chat['title'] ?? ($chat['username'] ?? '?'),
            );
        }
    }

    if (!$chats) {
        echo "No chats found. Post any message in the channel, then run this again.\n";
        exit(1);
    }

    echo "Found:\n";
    foreach ($chats as $line) {
        echo "  {$line}\n";
    }
    echo "\nPut the right id into TELEGRAM_CHAT_ID in .env and run this tool again.\n";
    exit(0);
}

printf("Chat id: %s\nSending test message...\n\n", $chatId);

$ok = send_telegram(
    sprintf("✅ Monitor test message\n\nSent at: %s (%s)", local_time(now()), app_timezone()->getName()),
    debug: true,
);

echo $ok ? "\nRESULT: sent OK\n" : "\nRESULT: FAILED (see response above)\n";
exit($ok ? 0 : 1);
