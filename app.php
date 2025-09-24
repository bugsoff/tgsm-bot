<?php
// app.php

require_once 'vendor/autoload.php';

use App\Init;
use App\TokenStorage;
use App\TelegramHandler;
use App\Server;
use App\Colors;

cprintf(Colors::BLACK.Colors::BG_WHITE, "Start app");
extract(new Init()());

$storage         = new TokenStorage($storagePath);
$telegramHandler = new TelegramHandler($telegramBotToken, $telegramSecretToken, $webhookUrl, $endpointUrl, $botName, $storage);
$server          = new Server("$serverAddr:$serverPort", $telegramHandler);

$server->run();
cprintf(Colors::BLACK.Colors::BG_WHITE, "Shutdown app");
