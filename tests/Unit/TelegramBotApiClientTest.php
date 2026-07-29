<?php

declare(strict_types=1);

namespace Phenogram\Framework\Tests\Unit;

use Phenogram\Framework\Exception\PhenogramException;
use Phenogram\Framework\Exception\TelegramBotApiException;
use Phenogram\Framework\Http\HttpClientInterface;
use Phenogram\Framework\Http\HttpResponse;
use Phenogram\Framework\TelegramBotApiClient;
use Phenogram\Framework\Type\BufferedFile;
use Phenogram\Framework\Type\LocalFile;
use Phenogram\Framework\Type\ReadableStreamFile;
use PHPUnit\Framework\TestCase;

final class TelegramBotApiClientTest extends TestCase
{
    public function testMalformedJsonErrorContainsTheResponseBody(): void
    {
        $client = new TelegramBotApiClient(
            token: 'test-token',
            client: $this->httpClientThatReturns('<html>bad gateway</html>'),
        );

        $this->expectException(TelegramBotApiException::class);
        $this->expectExceptionMessage('Response [getMe] is not json: <html>bad gateway</html>.');

        $client->sendRequest('getMe', []);
    }

    public function testSuccessfulJsonResponseIsDecoded(): void
    {
        $client = new TelegramBotApiClient(
            token: 'test-token',
            client: $this->httpClientThatReturns('{"ok":true,"result":{"id":42}}'),
        );

        $response = $client->sendRequest('getMe', []);

        self::assertTrue($response->ok);
        self::assertSame(['id' => 42], $response->result);
    }

    public function testBuildsNativeMultipartAndCleansTheStagedStream(): void
    {
        $localPath = tempnam(sys_get_temp_dir(), 'phenogram-local-');
        self::assertNotFalse($localPath);
        file_put_contents($localPath, 'local contents');

        $stream = fopen('php://memory', 'w+b');
        self::assertIsResource($stream);
        fwrite($stream, 'stream contents');
        rewind($stream);

        $httpClient = new class implements HttpClientInterface {
            /**
             * @var array<string, string|\CURLFile|\CURLStringFile>
             */
            public array $fields = [];

            /**
             * @var array<string, string>
             */
            public array $stagedPaths = [];

            /**
             * @var array<string, string>
             */
            public array $stagedContents = [];

            /**
             * @var array<string, int>
             */
            public array $stagedPermissions = [];

            public function post(string $url, array $fields, int $timeoutMilliseconds): HttpResponse
            {
                $this->fields = $fields;

                foreach (['stream', 'buffered'] as $field) {
                    if (!$fields[$field] instanceof \CURLFile) {
                        throw new \LogicException(sprintf('%s was not staged as a CURLFile', $field));
                    }

                    $this->stagedPaths[$field] = $fields[$field]->getFilename();
                    $this->stagedContents[$field] = file_get_contents($this->stagedPaths[$field]);
                    $this->stagedPermissions[$field] = fileperms($this->stagedPaths[$field]) & 0777;
                }

                return new HttpResponse(
                    status: 200,
                    body: '{"ok":true,"result":{"id":42}}',
                );
            }
        };

        try {
            $client = new TelegramBotApiClient(token: 'test-token', client: $httpClient);
            $client->sendRequest('sendMedia', [
                'local' => new LocalFile($localPath, 'local.txt'),
                'stream' => new ReadableStreamFile($stream, 'stream.txt'),
                'buffered' => new BufferedFile('buffered contents', 'buffered.txt'),
                'options' => ['silent' => true],
            ]);

            self::assertInstanceOf(\CURLFile::class, $httpClient->fields['local']);
            self::assertSame($localPath, $httpClient->fields['local']->getFilename());
            self::assertSame('local.txt', $httpClient->fields['local']->getPostFilename());

            self::assertSame('stream contents', $httpClient->stagedContents['stream']);
            self::assertSame('buffered contents', $httpClient->stagedContents['buffered']);
            self::assertSame(0600, $httpClient->stagedPermissions['stream']);
            self::assertSame(0600, $httpClient->stagedPermissions['buffered']);
            self::assertSame('stream.txt', $httpClient->fields['stream']->getPostFilename());
            self::assertSame('buffered.txt', $httpClient->fields['buffered']->getPostFilename());
            self::assertFileDoesNotExist($httpClient->stagedPaths['stream']);
            self::assertFileDoesNotExist($httpClient->stagedPaths['buffered']);
            self::assertSame('{"silent":true}', $httpClient->fields['options']);
        } finally {
            fclose($stream);
            unlink($localPath);
        }
    }

    public function testRejectsNonBlockingReadableStream(): void
    {
        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        self::assertIsArray($sockets);
        stream_set_blocking($sockets[0], false);

        $httpClient = new class implements HttpClientInterface {
            public function post(string $url, array $fields, int $timeoutMilliseconds): HttpResponse
            {
                throw new \LogicException('HTTP must not start for an unsafe stream');
            }
        };

        $client = new TelegramBotApiClient(token: 'test-token', client: $httpClient);

        $this->expectException(PhenogramException::class);
        $this->expectExceptionMessage('must be in blocking mode');

        try {
            $client->sendRequest('sendDocument', [
                'document' => new ReadableStreamFile($sockets[0], 'stream.txt'),
            ]);
        } finally {
            fclose($sockets[0]);
            fclose($sockets[1]);
        }
    }

    public function testAcceptsRegularFileReadableStream(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'phenogram-stream-');
        self::assertNotFalse($path);
        file_put_contents($path, 'regular file contents');

        $stream = fopen($path, 'rb');
        self::assertIsResource($stream);

        $httpClient = new class implements HttpClientInterface {
            public ?string $contents = null;

            public function post(string $url, array $fields, int $timeoutMilliseconds): HttpResponse
            {
                if (!$fields['document'] instanceof \CURLFile) {
                    throw new \LogicException('The regular file stream was not staged as a CURLFile');
                }

                $this->contents = file_get_contents($fields['document']->getFilename());

                return new HttpResponse(
                    status: 200,
                    body: '{"ok":true,"result":{"id":42}}',
                );
            }
        };

        try {
            $client = new TelegramBotApiClient(token: 'test-token', client: $httpClient);
            $client->sendRequest('sendDocument', [
                'document' => new ReadableStreamFile($stream, 'file.txt'),
            ]);

            self::assertSame('regular file contents', $httpClient->contents);
        } finally {
            fclose($stream);
            unlink($path);
        }
    }

    public function testLongPollingTimeoutIncludesTransportMargin(): void
    {
        $httpClient = new class implements HttpClientInterface {
            public int $timeoutMilliseconds = 0;

            public function post(string $url, array $fields, int $timeoutMilliseconds): HttpResponse
            {
                $this->timeoutMilliseconds = $timeoutMilliseconds;

                return new HttpResponse(
                    status: 200,
                    body: '{"ok":true,"result":[]}',
                );
            }
        };

        $client = new TelegramBotApiClient(token: 'test-token', client: $httpClient);
        $client->sendRequest('getUpdates', ['timeout' => 120]);

        self::assertSame(130_000, $httpClient->timeoutMilliseconds);
    }

    private function httpClientThatReturns(string $body): HttpClientInterface
    {
        return new readonly class($body) implements HttpClientInterface {
            public function __construct(
                private string $body,
            ) {
            }

            public function post(string $url, array $fields, int $timeoutMilliseconds): HttpResponse
            {
                return new HttpResponse(
                    status: 200,
                    body: $this->body,
                );
            }
        };
    }
}
