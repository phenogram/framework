<?php

declare(strict_types=1);

namespace Phenogram\Framework\Tests\Unit;

use Amp\Cancellation;
use Amp\Http\Client\DelegateHttpClient;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Phenogram\Framework\Exception\TelegramBotApiException;
use Phenogram\Framework\TelegramBotApiClient;
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

    private function httpClientThatReturns(string $body): HttpClient
    {
        $delegate = new readonly class($body) implements DelegateHttpClient {
            public function __construct(
                private string $body,
            ) {
            }

            public function request(Request $request, Cancellation $cancellation): Response
            {
                return new Response(
                    protocolVersion: '1.1',
                    status: 200,
                    reason: 'OK',
                    headers: ['content-type' => 'application/json'],
                    body: $this->body,
                    request: $request,
                );
            }
        };

        return new HttpClient($delegate, []);
    }
}
