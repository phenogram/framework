<?php

namespace Shanginn\TelegramBotApiFramework\Interface;

use Psr\Http\Message\ResponseInterface;
use React\Promise\PromiseInterface;

interface AsyncHttpClientInterface
{
    /**
     * @return PromiseInterface<ResponseInterface>
     */
    public function get(string $url, array $headers = []): PromiseInterface;

    /**
     * @return PromiseInterface<ResponseInterface>
     */
    public function post(string $url, array $headers = [], string $body = ''): PromiseInterface;
}
