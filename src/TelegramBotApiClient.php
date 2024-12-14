<?php

namespace Phenogram\Framework;

use Amp\Http\Client\Form;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;
use Phenogram\Bindings\ClientInterface;
use Phenogram\Bindings\Types;
use Phenogram\Framework\Exception\TelegramBotApiException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use PsrDiscovery\Discover;

final class TelegramBotApiClient implements ClientInterface
{
    private LoggerInterface $logger;
    private HttpClient $client;

    public function __construct(
        private readonly string $token,
        private readonly string $apiUrl = 'https://api.telegram.org',
        ?LoggerInterface $logger = null,
    ) {
        $this->client = HttpClientBuilder::buildDefault();

        $this->logger = $logger ?? Discover::log() ?? new NullLogger();
    }

    public function sendRequest(string $method, array $data): Types\Response
    {
        $this->logger->debug("Request [$method]", [
            'json' => json_encode($data),
        ]);

        $request = new Request(
            uri: "{$this->apiUrl}/bot{$this->token}/{$method}",
            method: 'POST',
        );

        $body = new Form();

        foreach ($data as $key => $value) {
            if ($value instanceof Types\Interfaces\InputFileInterface) {
                if (!file_exists($value->filePath)) {
                    throw new \RuntimeException("File not found: {$value->filePath}");
                }

                $body->addFile($key, $value->filePath);
            } else {
                $body->addField($key, is_array($value) ? json_encode($value) : $value);
            }
        }

        $request->setBody($body);

        $request->setTransferTimeout(60);
        $request->setInactivityTimeout(60);

        try {
            $response = $this->client->request($request);
        } catch (HttpException $e) {
            $message = sprintf(
                'Request [%s] failed: %s',
                $method,
                $e->getMessage()
            );

            $this->logger->error($message);

            throw new TelegramBotApiException($message);
        }

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
