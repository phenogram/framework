<?php

namespace Shanginn\TelegramBotApiFramework;

use Http\Client\HttpAsyncClient;
use Http\Discovery\HttpAsyncClientDiscovery;
use Http\Discovery\Psr17FactoryDiscovery;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use PsrDiscovery\Discover;
use React\Promise\PromiseInterface;
use Shanginn\TelegramBotApiBindings\TelegramBotApiClientInterface;
use Shanginn\TelegramBotApiFramework\Exception\TelegramBotApiException;

use function React\Async\async;
use function React\Promise\reject;

final class TelegramBotApiClient implements TelegramBotApiClientInterface
{
    private HttpAsyncClient $client;

    private RequestFactoryInterface $requestFactory;

    private LoggerInterface $logger;

    public function __construct(
        private readonly string $token,
        HttpAsyncClient $client = null,
        RequestFactoryInterface $requestFactory = null,
        LoggerInterface $logger = null,
        private readonly string $apiUrl = 'https://api.telegram.org',
    ) {
        $this->client = $client ?? HttpAsyncClientDiscovery::find();

        $this->requestFactory = $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();

        $this->logger = $logger ?? Discover::log() ?? new NullLogger();
    }

    public function sendRequest(string $method, string $json): PromiseInterface
    {
        $url = "{$this->apiUrl}/bot{$this->token}/{$method}";

        $request = $this->requestFactory
            ->createRequest('POST', $url)
            ->withHeader('Content-Type', 'application/json');

        $request->getBody()->write($json);

        $this->logger->debug("Request [$method]", [
            'json' => $json,
        ]);

        return $this
            ->sendAsyncRequest($request)
            ->then(function (ResponseInterface $response) use ($method) {
                $responseContent = $response->getBody()->getContents();
                $responseData = json_decode($responseContent, true);

                $this->logger->debug("Response [$method]", [
                    'response' => $responseData,
                ]);

                if (!$responseData || !isset($responseData['ok'], $responseData['result']) || !$responseData['ok']) {
                    $this->logger->error(sprintf(
                        'Telegram API response is not successful: %s',
                        $responseContent
                    ));

                    return reject(new TelegramBotApiException(sprintf(
                        'Telegram bot API error: %s',
                        $responseData['description'] ?? 'Unknown error'
                    )));
                }

                return json_encode($responseData['result']);
            });
    }

    private function sendAsyncRequest(RequestInterface $request): PromiseInterface
    {
        return async(fn () => $this->client->sendAsyncRequest($request)->wait())();
        //
        //      TODO: why this doesn't work?
        //
        //        $deferred = new Deferred();
        //
        //        $this->client->sendAsyncRequest($request)->then(
        //            $deferred->resolve(...),
        //            $deferred->reject(...),
        //        );
        //
        //        return $deferred->promise();
    }
}
