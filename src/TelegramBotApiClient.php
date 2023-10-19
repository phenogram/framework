<?php

namespace Shanginn\TelegramBotApiFramework;

use Http\Client\HttpAsyncClient;
use Http\Discovery\HttpAsyncClientDiscovery;
use Http\Discovery\Psr17FactoryDiscovery;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use PsrDiscovery\Discover;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Shanginn\TelegramBotApiBindings\TelegramBotApiClientInterface;
use Shanginn\TelegramBotApiBindings\Types\TypeInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

final class TelegramBotApiClient implements TelegramBotApiClientInterface
{
    private HttpAsyncClient $client;

    private RequestFactoryInterface $requestFactory;

    private LoggerInterface $logger;

    private SerializerInterface $serializer;

    public function __construct(
        private readonly string $token,
        HttpAsyncClient $client = null,
        RequestFactoryInterface $requestFactory = null,
        LoggerInterface $logger = null,
        SerializerInterface $serializer = null,
        private readonly string $apiUrl = 'https://api.telegram.org',
    ) {
        $this->client = $client ?? HttpAsyncClientDiscovery::find();

        $this->requestFactory = $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();

        $this->logger = $logger ?? Discover::log() ?? new NullLogger();

        $this->serializer = $serializer ?? new Serializer([new ObjectNormalizer(
            null,
            new CamelCaseToSnakeCaseNameConverter()
        )], [new JsonEncoder()]);
    }

    public function sendRequest(string $method, string $json): PromiseInterface
    {
        $url = "{$this->apiUrl}/bot{$this->token}/{$method}";

        $request = $this->requestFactory
            ->createRequest('POST', $url)
            ->withHeader('Content-Type', 'application/json');

        $request->getBody()->write($json);

        $this->logger->debug("Request [$method]", [
            'params_string' => $json,
        ]);

        $deferred = new Deferred();

        $this->client->sendAsyncRequest($request)->then(
            function ($response) use ($deferred, $method) {
                $responseContent = $response->getBody()->getContents();
                $responseData = json_decode($responseContent, true);

                $this->logger->debug("Response [$method]", [
                    'response' => $responseData,
                ]);

                if (!$responseData || !isset($responseData['ok']) || !$responseData['ok']) {
                    $this->logger->error(sprintf(
                        'Telegram API response is not successful: %s',
                        $responseContent
                    ));

                    $deferred->reject(new \RuntimeException(sprintf(
                        'Telegram API error: %s',
                        $responseData['description'] ?? 'Unknown error'
                    )));
                }

                $deferred->resolve($response);
            },
            $deferred->reject(...),
        );

        return $deferred->promise();
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

    public function serialize(mixed $data): string
    {
        return $this->serializer->serialize($data, 'json');
    }

    public function deserialize(string $data, array $types): mixed
    {
        foreach ($types as $type) {
            try {
                return $this->serializer->deserialize($data, $type, 'json');
            } catch (\Throwable) {
            }
        }

        throw new \UnexpectedValueException(sprintf('Failed to decode response to any of the expected types: %s', implode(', ', $types)));
    }
}
