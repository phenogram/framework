<?php

namespace Phenogram\Framework;

use Phenogram\Bindings\ClientInterface;
use Phenogram\Bindings\Types;
use Phenogram\Framework\Exception\HttpTransportException;
use Phenogram\Framework\Exception\PhenogramException;
use Phenogram\Framework\Exception\TelegramBotApiException;
use Phenogram\Framework\Http\CurlHttpClient;
use Phenogram\Framework\Http\HttpClientInterface;
use Phenogram\Framework\Type\LocalFileInterface;
use Phenogram\Framework\Type\ReadableStreamFileInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use PsrDiscovery\Discover;

final class TelegramBotApiClient implements ClientInterface
{
    private const int DEFAULT_REQUEST_TIMEOUT_MILLISECONDS = 60_000;

    private const int LONG_POLL_MARGIN_MILLISECONDS = 10_000;

    private LoggerInterface $logger;
    private HttpClientInterface $client;

    public function __construct(
        private readonly string $token,
        private readonly string $apiUrl = 'https://api.telegram.org',
        ?LoggerInterface $logger = null,
        ?HttpClientInterface $client = null,
    ) {
        $this->client = $client ?? new CurlHttpClient();

        $this->logger = $logger ?? Discover::log() ?? new NullLogger();
    }

    public function sendRequest(string $method, array $data): Types\Response
    {
        $this->logger->debug("Request [$method]", [
            'json' => json_encode($data),
        ]);

        $temporaryFiles = [];

        try {
            $fields = [];
            foreach ($data as $key => $value) {
                if ($value instanceof ReadableStreamFileInterface) {
                    $fields[$key] = $this->stageStreamFile($value, $temporaryFiles);
                } elseif ($value instanceof LocalFileInterface) {
                    if (!is_file($value->filepath) || !is_readable($value->filepath)) {
                        throw new PhenogramException("File is not readable: {$value->filepath}");
                    }

                    $fields[$key] = new \CURLFile(
                        $value->filepath,
                        'application/octet-stream',
                        $value->filename,
                    );
                } else {
                    $fields[$key] = is_array($value)
                        ? json_encode($value, flags: JSON_THROW_ON_ERROR)
                        : (string) $value;
                }
            }

            try {
                $response = $this->client->post(
                    url: "{$this->apiUrl}/bot{$this->token}/{$method}",
                    fields: $fields,
                    timeoutMilliseconds: $this->requestTimeoutMilliseconds($method, $data),
                );
            } catch (HttpTransportException $e) {
                $message = sprintf(
                    'Request [%s] failed: %s',
                    $method,
                    $e->getMessage()
                );

                $this->logger->error($message);

                throw new TelegramBotApiException($message, previous: $e);
            }
        } finally {
            foreach ($temporaryFiles as $temporaryFile) {
                if (file_exists($temporaryFile) && !unlink($temporaryFile)) {
                    $this->logger->warning('Could not remove temporary upload file', [
                        'path' => $temporaryFile,
                    ]);
                }
            }
        }

        $this->logger->debug("Response [$method]: status {$response->status}");

        try {
            $responseData = json_decode(
                json: $response->body,
                associative: true,
                flags: JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $e) {
            $message = sprintf(
                'Response [%s] is not json: %s. Error: %s',
                $method,
                $response->body,
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

    /**
     * @param list<string> $temporaryFiles
     */
    private function stageStreamFile(
        ReadableStreamFileInterface $file,
        array &$temporaryFiles,
    ): \CURLFile {
        $path = tempnam(sys_get_temp_dir(), 'phenogram-upload-');
        if ($path === false) {
            throw new PhenogramException('Could not create a temporary upload file');
        }

        $temporaryFiles[] = $path;
        if (!chmod($path, 0600)) {
            throw new PhenogramException(sprintf('Could not secure temporary upload file: %s', $path));
        }

        $destination = fopen($path, 'wb');
        if ($destination === false) {
            throw new PhenogramException(sprintf('Could not open temporary upload file: %s', $path));
        }

        try {
            $file->writeTo($destination);
        } finally {
            fclose($destination);
        }

        return new \CURLFile($path, 'application/octet-stream', $file->filename);
    }

    /**
     * Telegram holds getUpdates open for the requested server-side timeout, so
     * the transport needs extra time for connection setup and response transfer.
     *
     * @param array<string, mixed> $data
     */
    private function requestTimeoutMilliseconds(string $method, array $data): int
    {
        if ($method !== 'getUpdates' || !isset($data['timeout']) || !is_numeric($data['timeout'])) {
            return self::DEFAULT_REQUEST_TIMEOUT_MILLISECONDS;
        }

        $timeoutSeconds = (float) $data['timeout'];
        if (!is_finite($timeoutSeconds) || $timeoutSeconds <= 0) {
            return self::DEFAULT_REQUEST_TIMEOUT_MILLISECONDS;
        }

        $maximumSeconds = (PHP_INT_MAX - self::LONG_POLL_MARGIN_MILLISECONDS) / 1000;
        if ($timeoutSeconds >= $maximumSeconds) {
            return PHP_INT_MAX;
        }

        $longPollMilliseconds = (int) ceil($timeoutSeconds * 1000);

        return max(
            self::DEFAULT_REQUEST_TIMEOUT_MILLISECONDS,
            $longPollMilliseconds + self::LONG_POLL_MARGIN_MILLISECONDS,
        );
    }
}
