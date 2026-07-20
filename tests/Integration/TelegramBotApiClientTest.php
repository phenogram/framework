<?php

declare(strict_types=1);

namespace Phenogram\Framework\Tests\Integration;

use Phenogram\Framework\TelegramBotApiClient;
use Phenogram\Framework\Tests\Support\Environment;
use PHPUnit\Framework\TestCase;

final class TelegramBotApiClientTest extends TestCase
{
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        if (Environment::get('RUN_TELEGRAM_INTEGRATION') !== '1') {
            $this->markTestSkipped('Set RUN_TELEGRAM_INTEGRATION=1 to run live Telegram tests.');
        }

        $token = Environment::get('TELEGRAM_BOT_TOKEN');

        if ($token === null || trim($token) === '') {
            $this->markTestSkipped('TELEGRAM_BOT_TOKEN is not set.');
        }

        $this->token = $token;
    }

    public function testSendRequest(): void
    {
        $client = new TelegramBotApiClient($this->token);

        $response = $client->sendRequest('getMe', []);

        self::assertTrue($response->ok);
    }
}
