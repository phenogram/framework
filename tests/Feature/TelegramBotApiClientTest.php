<?php

namespace Phenogram\Framework\Tests\Feature;

use Phenogram\Framework\TelegramBotApiClient;
use PHPUnit\Framework\TestCase;

class TelegramBotApiClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->token = $_ENV['TELEGRAM_BOT_TOKEN'];

        if ($this->token === null) {
            $this->markTestSkipped('TELEGRAM_BOT_TOKEN not set');
        }
    }

    public function testSendRequest()
    {
        $client = new TelegramBotApiClient($this->token);

        $response = $client->sendRequest('getMe', []);

        self::assertTrue($response->ok);
    }
}
