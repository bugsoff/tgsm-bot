<?php
// TelegramHandler.php

namespace App;

use App\Colors;
use TelegramBot\Api\BotApi;
use TelegramBot\Api\Types\Message;
use TelegramBot\Api\HttpException;
use TelegramBot\Api\InvalidJsonException;
use TelegramBot\Api\Exception;
use Exception as CommonException;
use RuntimeException;
use stdClass;

class TelegramHandler 
{    
    private $botApi;

    public function __construct(
        private string $botToken,
        private string $secretToken,
        private string $webhookUrl,
        protected string $endpointUrl,
        protected string $botName,
        private TokenStorage $storage
    ) {
        cprintf(null, "[%s] Set webhook: %s", __METHOD__, $webhookUrl);
        try {
            $this->botApi = new BotApi($this->botToken);
            $webhookInfo = $this->botApi->getWebhookInfo();
            $currentUrl = $webhookInfo->getUrl();
    
            if ($currentUrl !== $this->webhookUrl) {
                $this->botApi->setWebhook($this->webhookUrl, null, null, null, false, $this->secretToken);
            } else {
                cprintf(Colors::CYAN, "[%s] Webhook %s already properly configured", __METHOD__, $currentUrl);
            }
        } catch (Exception $e) {
            error_log("[" . __METHOD__ . "] Failed to configure webhook: [{$e->getCode()}] {$e->getMessage()}\n");
            throw new RuntimeException("FATAL: Can't create object of " . self::class);
        }        
    }

    /**
     *  {
     *      "update_id": 123456789,
     *      "message": {
     *          "message_id": 1,
     *          "from": {
     *              "id": 987654321,
     *              "first_name": "Иван",
     *              "username": "ivan_user"
     *          },
     *          "chat": {
     *              "id": 987654321,
     *              "first_name": "Иван"
     *          },
     *          "date": 1612345678,
     *          "text": "Привет"
     *      }
     *  }
     */
    public function handleWebhook(stdClass $data): ?bool 
    {
        cprintf(Colors::CYAN, "[%s] Get webhook. Text: %s", __METHOD__, $data->message->text ?? 'NULL');
        switch ($data->message->text ?? '') {
            case "/start":
                return $this->handleStart($data->message);
            case "/stop":
                return $this->handleStop($data->message);
            default:
        }

        return null;
    }

    private function sendMessage(int|stdClass $chat, string $message, ?string $type = null): bool
    {
        $chatId = is_int($chat) ? $chat : $chat->chatId;
        cprintf(null, "[%s] Send message: %s", __METHOD__, $message); 
        try {
            if ($message =  $this->botApi->sendMessage($chatId, $welcomeMessage, $type) instanceof Message) {
                cprintf(Colors::GREEN, "Message #{$message->getMessageId()} sent to chat #{$message->getChat()->getId()}");
                return true;
            } else {
                $errMessage = sprintf("[%s] Unexpected responce from Telegram API: %s", __METHOD__, json_encode($message));
            }
        } catch (HttpException $e) {
            $httpCode = $e->getCode();
            $errMessage = sprintf("[%s] %%s [$httpCode] {$e->getMessage()}", __METHOD__);
            switch ($httpCode) {
                case 400: $errMessage = sprintf($errMessage, "Wrong request parameters"); break;
                case 401: $errMessage = sprintf($errMessage, "Wrong bot token"); break;
                case 403: 
                    $errMessage = sprintf($errMessage, "Blocked by user"); 
                    $this->storage->deleteToken($chat->token ?? null);
                    break;
                case 404: $errMessage = sprintf($errMessage, "Bot not found"); break;
                case 429: $errMessage = sprintf($errMessage, "Too many requests"); break;
                case 500: $errMessage = sprintf($errMessage, "Telegram error"); break;
                default: $errMessage = sprintf($errMessage, "Unknown error"); break;
            }
        } catch (InvalidJsonException $e) {
            $errMessage = sprintf("[%s] JSON error: %s", __METHOD__, $e->getMessage());
        } catch (Exception $e) {
            $errMessage = sprintf("[%s] Telegram API error: %s", __METHOD__, $e->getMessage());
        } catch (CommonException $e) {
            $errMessage = sprintf("[%s] Failed to send message: %s", __METHOD__, $e->getMessage());
        }

        error_log($errMessage);
        cprintf(Colors::RED, $errMessage);
        return false;
    }
    
    private function handleStart(stdClass $message): bool {
        cprintf(null, "[%s] Start bot command", __METHOD__);
        $username = $message->from->first_name ?? 'User';
        $token = $this->storage->newToken($message->chat->id);
        
        $welcomeMessage = "👋 Привет, {$username}!\n\n"
            . "Ваш уникальный API-токен: <code>{$token}</code>\n"
            . "URL для отправки сообщений:\n"
            . "<code>{$this->endpointUrl}/{token}/{text}</code>\n\n"
            . "Пример использования:\n"
            . "<code>GET {$this->endpointUrl}/{$token}/Hello%20World!</code>\n\n"
            . "Для удаления токена отправьте команду: <code>/stop</code>";

        return $this->sendMessage((int) $message->chat->id, $welcomeMessage, 'HTML');
    }

    private function handleStop(stdClass $message) : bool 
    {
        cprintf(null, "[%s] Stop bot command", __METHOD__);
        $chat = $this->storage->getToken($message->chat->id);
        $this->storage->deleteToken($chat->token);
        $message = "API-токен <code>{$chat->token}</code> удалён.\nПока!";
        
        return $this->sendMessage((int) $message->chat->id, $welcomeMessage, 'HTML');
    }
    
    public function sendTo(string $token, string $text): ?bool {
        cprintf(Colors::CYAN, "[%s] Message from API: %s", __METHOD__, $text);
        $chat = $this->storage->getToken($token);
        if (empty($chat->token)) {
            error_log(sprintf("[%s] Unknown API-token: '%s'", __METHOD__, $token));
            return null;
        }
        if ($chat->deleted_at ?? false) {
            error_log(sprintf("[%s] Deleted API-token: '%s'", __METHOD__, $token));
            return null;
        }

        return $this->sendMessage($chat, $text);
    }

    public function getBotName(): string { return $this->botName; }

    public function validateWebhookRequest($request): bool { return $request->getHeaderLine('X-Telegram-Bot-Api-Secret-Token') === $this->secretToken; }
}
