<?php
// app.php

require_once 'vendor/autoload.php';

use App\Colors;
use App\Init;
use App\TokenStorage;
use App\TelegramHandler;
use App\Server;

cprintf(null, "Start app\n");
extract(Init::class);

$storage         = new TokenStorage($storagePath);
$telegramHandler = new TelegramHandler($telegramBotToken, $telegramSecretToken, $webhookUrl, $endpointUrl, $botName, $storage);
$server          = new Server("$serverAddr:$serverPort", $telegramHandler);

$server->run();
cprintf(Colors::WHITE, "Shutdown app\n");
