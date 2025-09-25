<?php
// server.php

namespace App;

use React\EventLoop\Loop;
use React\EventLoop\StreamSelectLoop;
use React\Http\HttpServer;
use React\Http\Message\Response;
use React\Socket\SocketServer;
use App\TokenStorage;
use App\TelegramHandler;
use App\Colors;

class Server
{
    private StreamSelectLoop $loop;
    private HttpServer $server;
    public const MESSAGE_MAX_LENGTH = 1024;

    public function __construct(
        private string $serverAddr,
        private TelegramHandler $telegramHandler,
    ) {
        cprintf(null, "[%s] Make server %s", __METHOD__, $serverAddr);
        $this->loop = Loop::get();
        $this->server = new HttpServer([$this, 'process']);
        $server->listen(new SocketServer($this->serverAddr, [], $this->loop));
    }

    public function run()
    {
        cprintf(null, "[%s] Start server", __METHOD__);
        $this->loop->run();
    }

    protected function response(string $message, int $code = 200, ?bool $error = null): Response
    {
        cprintf(Colors::WHITE, "[%s] API response: %s %s", __METHOD__, $code, $message);
        $error = $error === null ? $code >= 400 : $error; 
        return new Response($code, ['Content-Type' => 'application/json'], json_encode(['status' => $error ? 'error' : 'success', 'message' => $message ]));
    }

    protected function process($request): Response
    {
        $path = $request->getUri()->getPath();
        cprintf(Colors::WHITE, "[%s] API request: %s %s", __METHOD__, $request->getMethod(), $path);
        switch($path) {
            case '/': return $this->response("Telegram Send Message Bot is running. Write to @{$this->telegramHandler->botName} to use it!");
            case '/webhook': return $this->responseWebhook($request);
            default:
                $tokenSymbols = str_replace('-', '\\-', TokenStorage::TOKEN_CHARACTERS);
                $tokenLength = TokenStorage::TOKEN_LENGTH;
                preg_match("#^/api/([$symbols]\{$tokenLength\})/([^/]+)$#", $path, $matches);
                if ($token = $matches[1] ?? '') {
                    return $this->responseSendTo($token, urldecode($matches[2] ?? ''));
                }
        }

        return $this->response("Not found", 404);
    }

    protected function responseWebhook($request): Response
    {
        cprintf(null, "[%s] API process webhook", __METHOD__);

        if ($request->getMethod() === 'GET') {
            return $this->response('Webhook endpoint is ready');
        } 
        if ($request->getMethod() === 'POST') {
            if (!$this->telegramHandler->validateWebhookRequest($request)) {
                $this->response("Oh, no!", 403);
            }

            $update = json_decode((string) $request->getBody(), true);
            
            if ($update) {
                $handler = $this->telegramHandler;
                $loop->futureTick(function() use ($handler, $update) {
                    try {
                        $handler->handleWebhook($update);
                    } catch (Exception $e) {
                        error_log(sprintf("[%s] Error processing webhook: %s", __METHOD__, $e->getMessage()));
                    }
                });
            }
            
            return $this->response('OK');
        }

        return $this->response("Unknown method", 405);
    }

    protected function responseSendTo($token, $text)
    {
        cprintf(null, "[%s] API process sendTo");
        if (strlen($text) > self::MESSAGE_MAX_LENGTH) {
            return $this->response("Too long message. Up to 1 Kbyte.", 414);
        }
        if ($result = $telegramHandler->sendTo($token, $text)) {
            return $this->response("Message sent: $text");
        }
        if ($result === false) {
            return $this->response("Send temporary failed", 503);
        }
        
        return $this->response('Unknown or deleted Token', 401);
    }

}