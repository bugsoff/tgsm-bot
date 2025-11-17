<?php
// server.php

namespace App;

use React\EventLoop\Loop;
use React\EventLoop\StreamSelectLoop;
use React\Http\HttpServer;
use React\Http\Message\Response;
use Psr\Http\Message\ResponseInterface;
use React\Http\Message\ServerRequest;
use React\Socket\SocketServer;
use App\TokenStorage;
use App\TelegramHandler;
use App\Colors;
use stdClass;

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
        $this->server->on('error', function (Throwable $e) {
            cprintf(Colors::BG_RED, "[%s] (%d) %s", get_class($e), $e->getCode(), $e->getMessage());
        });
        $this->server->listen(new SocketServer($this->serverAddr, [], $this->loop));
    }

    public function run()
    {
        cprintf(null, "[%s] Start server", __METHOD__);
        $this->loop->run();
    }

    protected function responseJson(string $message, int $code = 200, ?bool $error = null): Response
    {
        cprintf(Colors::WHITE, "[%s] API response: %d", __METHOD__, $code);
        $error = $error === null ? $code >= 400 : $error; 
        return new Response($code, ['Content-Type' => 'application/json'], 
                            json_encode(['status' => $error ? 'error' : 'success', 'message' => $message ], JSON_UNESCAPED_UNICODE));
    }

    public function process(ServerRequest $request): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        cprintf(Colors::WHITE, "[%s] API request: %s %s", __METHOD__, $request->getMethod(), $path);
        
        $matches = [];
        $pathPatterns = [
            '/^\/$/' => [$this, "processMain"],
            '/\.(webp|jpg|jpeg|png|gif|json|html)$/i' => [$this, "processStatic"],
            // API
            '/\/api\/webhook/i' => [$this, "processWebhook"],
            sprintf("#^/api/([%s]{%d})/([^/]+)$#", str_replace('-', '\\-', TokenStorage::TOKEN_CHARACTERS), TokenStorage::TOKEN_LENGTH) =>
                function(ServerRequest $request, stdClass $data) {
                    return ($token = $data->matches[1] ?? '')
                        ? $this->processSendTo($token, urldecode($data->matches[2] ?? ''))
                        : $this->responseJson("Invalid token", 400);
                }
        ];

        foreach ($pathPatterns as $pattern => $handler) {
            if (preg_match($pattern, $path, $matches)) {
                return $handler($request, (object)['matches' => $matches]);
            }
        }

        return $this->responseJson("Not found", 404);
    }

    protected function processMain(ServerRequest $request): Response
    {
        return new Response(200, ['Content-Type' => 'text/html'], file_get_contents("pub/index.html"));
    }

    protected function processWebhook(ServerRequest $request): Response
    {
        cprintf(null, "[%s] API process webhook", __METHOD__);
        if ($request->getMethod() === 'GET') {
            return $this->responseJson('Webhook endpoint is ready');
        } 
        if ($request->getMethod() === 'POST') {
            // if (!$this->telegramHandler->validateWebhookRequest($request)) {
            //     return $this->responseJson("Oh, no!", 403);
            // }

            $update = json_decode((string) $request->getBody());
            
            if ($update) {
                $handler = $this->telegramHandler;
                $this->loop->futureTick(function() use ($handler, $update) {
                    try {
                        $handler->handleWebhook($update);
                    } catch (Exception $e) {
                        error_log(sprintf("[%s] Error processing webhook: %s", __METHOD__, $e->getMessage()));
                    }
                });
            }
            
            return $this->responseJson('OK');
        }

        return $this->responseJson("Unknown method", 405);
    }

    protected function processSendTo(string $token, string $text): Response
    {
        cprintf(Colors::CYAN, "[%s] Got message from API: %s", __METHOD__, $text);
        if (strlen($text) > self::MESSAGE_MAX_LENGTH) {
            return $this->responseJson("Too long message. Up to 1 Kbyte.", 414);
        }
        if ($result = $this->telegramHandler->sendTo($token, $text)) {
            return $this->responseJson("The message sent to $token");
        }
        if ($result === false) {
            return $this->responseJson("Send temporary failed", 503);
        }
        
        return $this->responseJson('Unknown or deleted Token', 401);
    }

    protected function processStatic(ServerRequest $request): Response
    {
        $filename = "pub/" . trim($request->getUri()->getPath(), '/');
        $patterns = [
            '/\.webp$/i'  => 'image/webp',
            '/\.jpg$/i'  => 'image/jpeg',
            '/\.jpeg$/i' => 'image/jpeg',
            '/\.png$/i'  => 'image/png',
            '/\.gif$/i'  => 'image/gif',
            '/\.html$/i' => 'text/html',
            '/\.json$/i' => 'application/json',
            null         => 'application/octet-stream',   // значение по умолчанию
        ];

        foreach ($patterns as $pattern => $contentType) {
            if (preg_match($pattern, $filename)) {
                break; // остановка после первого совпадения
            }
        }
        
        return (file_exists($filename) && is_file($filename) && is_readable($filename))
            ? Response(200, ['Content-Type' => $contentType], file_get_contents($filename))
            : Response(404);
    }

}