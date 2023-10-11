<?php

namespace Shanginn\TelegramBotApiFramework;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use PsrDiscovery\Discover;
use Shanginn\TelegramBotApiBindings\TelegramBotApiClientInterface;
use Shanginn\TelegramBotApiBindings\Types\TypeInterface;

final class TelegramBotApiClient implements TelegramBotApiClientInterface
{
    private ClientInterface $client;

    private RequestFactoryInterface $requestFactory;

    private LoggerInterface $logger;

    public function __construct(
        private readonly string $token,
        ClientInterface $client = null,
        RequestFactoryInterface $requestFactory = null,
        LoggerInterface $logger = null,
        private readonly string $apiUrl = 'https://api.telegram.org',
    ) {
        $this->client = $client ?? Psr18ClientDiscovery::find();
        $this->requestFactory = $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();
        $this->logger = $logger ?? Discover::log() ?? new NullLogger();
    }

    public function sendRequest(string $method, array $args = []): mixed
    {
        $url = "{$this->apiUrl}/bot{$this->token}/{$method}";

        $request = $this->requestFactory
            ->createRequest('POST', $url)
            ->withHeader('Content-Type', 'application/json');

        // All the array should be encoded JSON strings.
        $args = array_map(
            fn ($param) => is_array($param) ? json_encode($param) : $param,
            $args
        );

        if (count($args) > 0) {
            $request->getBody()->write(
                json_encode($args)
            );
        }

        $this->logger->debug("Request [$method]", [
            'params' => $args,
        ]);

        $response = $this->client->sendRequest($request);
        $responseContent = $response->getBody()->getContents();
        $responseData = json_decode($responseContent, true);

        $this->logger->debug("Response [$method]", [
            'response' => $responseData,
        ]);

        if (!$responseData || !isset($responseData['ok']) || !$responseData['ok']) {
            $this->logger->error(sprintf(
                'Telegram API response if not successful: %s',
                $responseContent
            ));

            throw new \RuntimeException(sprintf('Telegram API error: %s', $responseData['description'] ?? 'Unknown error'));
        }

        return $responseData['result'];
    }

    public function convertResponseToType(mixed $response, array $returnTypes): TypeInterface|array|int|string|bool
    {
        foreach ($returnTypes as $type) {
            if (class_exists($type) && is_subclass_of($type, TypeInterface::class)) {
                return $type::fromResponseResult($response);
            } elseif ($type === 'bool') {
                return (bool) $response;
            } elseif ($type === 'int') {
                return (int) $response;
            } elseif ($type === 'string') {
                return (string) $response;
            } elseif (str_starts_with($type, 'array<')) {
                preg_match('/array<(.+)>/', $type, $matches);
                $innerType = $matches[1];
                $resultArray = [];
                foreach ($response as $item) {
                    $resultArray[] = $this->convertResponseToType($item, [$innerType]);
                }

                return $resultArray;
            }
        }

        $this->logger->error(sprintf(
            'Failed to decode response (%s) to any of the expected types: %s',
            json_encode($response),
            implode(', ', $returnTypes)
        ));

        throw new \UnexpectedValueException(sprintf('Failed to decode response to any of the expected types: %s', implode(', ', $returnTypes)));
    }
}
