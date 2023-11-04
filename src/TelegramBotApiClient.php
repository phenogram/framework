<?php

namespace Shanginn\TelegramBotApiFramework;

use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use PsrDiscovery\Discover;
use React\Http\Browser;
use React\Http\Message\ResponseException;
use React\Promise\PromiseInterface;
use Shanginn\TelegramBotApiBindings\TelegramBotApiClientInterface;
use Shanginn\TelegramBotApiFramework\Exception\TelegramBotApiException;

use function React\Promise\reject;

final class TelegramBotApiClient implements TelegramBotApiClientInterface
{
    private LoggerInterface $logger;
    private Browser $client;

    public function __construct(
        private readonly string $token,
        private readonly string $apiUrl = 'https://api.telegram.org',
        LoggerInterface $logger = null,
    ) {
        $this->client = (new Browser())
            ->withTimeout(false);

        $this->logger = $logger ?? Discover::log() ?? new NullLogger();
    }

    public function sendRequest(string $method, string $json): PromiseInterface
    {
        $url = "{$this->apiUrl}/bot{$this->token}/{$method}";

        $this->logger->debug("Request [$method]", [
            'json' => $json,
        ]);

        return $this
            ->postJson($url, $json)
            ->then(
                function (ResponseInterface $response) use ($method) {
                    $responseContent = $response->getBody()->getContents();
                    $responseData = $this->decodeJson($responseContent, $method);

                    $this->logger->debug("Response [$method]", [
                        'response' => $responseData,
                    ]);

                    if (!isset($responseData['ok'], $responseData['result']) || !$responseData['ok']) {
                        $this->logger->error(sprintf(
                            'Response [%s] is not ok: %s',
                            $method,
                            $responseContent
                        ));

                        return reject(new TelegramBotApiException(sprintf(
                            'Response [%s] is not ok: %s',
                            $method,
                            $responseData['description'] ?? 'Unknown error'
                        )));
                    }

                    return json_encode($responseData['result']);
                },
                function (\Throwable $e) use ($method) {
                    $context = [];
                    $errorMessage = sprintf(
                        'Request [%s] failed (%s)',
                        $method,
                        $e->getMessage()
                    );

                    if ($e instanceof ResponseException) {
                        $response = $e->getResponse();
                        $responseContent = $response->getBody()->getContents();
                        $responseData = $this->decodeJson($responseContent, $method);

                        $this->logger->debug("Response [$method]", [
                            'response' => $responseData,
                        ]);

                        $errorMessage .= ': ' . $responseData['description'] ?? 'Unknown error';
                        $context = $responseData;
                    }

                    $this->logger->error(sprintf(
                        'Request [%s] failed (%s)',
                        $method,
                        $e->getMessage()
                    ), $context);

                    return reject(new TelegramBotApiException($errorMessage));
                }
            );
    }

    private function postJson(string $url, string $json): PromiseInterface
    {
        return $this->client->post($url, [
            'Content-Type' => 'application/json',
        ], $json);
    }

    private function decodeJson(string $json, string $method): array
    {
        try {
            return json_decode(
                json: $json,
                associative: true,
                flags: JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $e) {
            $this->logger->error(sprintf(
                'Response [%s] is not json: %s',
                $method,
                $json
            ));

            throw new TelegramBotApiException(sprintf('Response [%s] is not json: %s', $method, $json));
        }
    }
}
