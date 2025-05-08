<?php

namespace Phenogram\Framework\Client;

use Amp\Http\Client\HttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\HttpException;
use Phenogram\Bindings\ClientInterface;
use Phenogram\Bindings\Types;
use Phenogram\Framework\Exception\ApiClientException;
use Phenogram\Framework\Exception\TelegramBotApiException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use PsrDiscovery\Discover;

class TelegramBotApiClient implements ClientInterface
{
    private LoggerInterface $logger;
    private HttpClient $client;
    private RequestFactory $requestFactory;

    public function __construct(
        string $token,
        ?LoggerInterface $logger = null,
        ?HttpClient $client = null,
        ?RequestFactory $requestFactory = null,
    ) {
        $this->logger = $logger ?? Discover::log() ?? new NullLogger();
        $this->client = $client ?? HttpClientBuilder::buildDefault();
        $this->requestFactory = $requestFactory ?? new RequestFactory($token);
    }

    public function sendRequest(string $method, array $data): Types\Response
    {
        $this->logger->debug("Request [$method]", [
            'json' => json_encode($data),
        ]);

        $request = $this->requestFactory->createRequest(
            method: $method,
            data: $data,
        );

        try {
            $response = $this->client->request($request);
        } catch (HttpException $e) {
            $message = sprintf(
                'Request [%s] failed: %s',
                $method,
                $e->getMessage()
            );

            $this->logger->error($message, [
                'exception' => $e,
            ]);

            throw new ApiClientException($message);
        } catch (\Throwable $e) {
            $message = sprintf(
                'Request [%s] failed critically: %s',
                $method,
                $e->getMessage()
            );

            $this->logger->error($message, [
                'exception' => $e,
            ]);

            throw new ApiClientException($message);
        }

        $this->logger->debug("Response [$method]: status {$response->getStatus()}");

        $responseContent = $response->getBody()->buffer();

        try {
            $responseData = json_decode(
                json: $responseContent,
                associative: true,
                flags: JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $e) {
            $message = sprintf(
                'Response [%s] is not json: %s. Error: %s',
                $method,
                $json,
                $e->getMessage()
            );

            $this->logger->error($message);

            throw new TelegramBotApiException($message);
        }

        $this->logger->debug("Response [$method]", [
            'response' => $responseData,
        ]);

        if (!isset($responseData['ok']) || !isset($responseData['result'])) {
            return new Types\Response(
                ok: false,
                result: null,
                errorCode: $responseData['error_code'] ?? null,
                description: $responseData['description'] ?? null,
                parameters: isset($responseData['parameters']) ? new Types\ResponseParameters(
                    migrateToChatId: $responseData['parameters']['migrate_to_chat_id'] ?? null,
                    retryAfter: $responseData['parameters']['retry_after'] ?? null,
                ) : null,
            );
        }

        return new Types\Response(
            ok: $responseData['ok'],
            result: $responseData['result'],
            errorCode: $responseData['error_code'] ?? null,
            description: $responseData['description'] ?? null,
            parameters: isset($responseData['parameters']) ? new Types\ResponseParameters(
                migrateToChatId: $responseData['parameters']['migrate_to_chat_id'] ?? null,
                retryAfter: $responseData['parameters']['retry_after'] ?? null,
            ) : null,
        );
    }
}
