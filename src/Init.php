<?php
// Init.php

namespace App;

use Colors;

class Init
{
    public function __invoke()
    {
        cprintf(null, "[%s] Init config", __METHOD__);
        $storagePath = getenv('STORAGE_PATH') ?? '/db';
        ($telegramBotToken = getenv('TELEGRAM_BOT_TOKEN'))     || exit("TELEGRAM_BOT_TOKEN environment variable is required\n");
        $telegramSecretToken = getenv('TELEGRAM_SECRET_TOKEN') ?? bin2hex(random_bytes(32));
        
        $webhookUrl = getenv('WEBHOOK_URL');
        if (!$webhookUrl) {
            die("WEBHOOK_URL environment variable is required\n");
        }
        $endpointUrl = trim(getenv('ENDPOINT_URL'), '/');
        if (!$endpointUrl) {
            die("ENDPOINT_URL environment variable is required\n");
        }
        $botName = getenv('TELEGRAM_BOT_NAME');
        if (!$botName) {
            die("TELEGRAM_BOT_NAME environment variable is required\n");
        }
        $serverAddr = getenv('SERVER_ADDR') ?? '0.0.0.0';
        $serverPort = getenv('SERVER_PORT') ?? '80';

        return compact(
            "storagePath",
            "telegramBotToken",
            "telegramSecretToken",
            "webhookUrl",
            "endpointUrl",
            "botName",
            "serverAddr",
            "serverPort",
        );
    }
}