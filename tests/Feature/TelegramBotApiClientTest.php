<?php

namespace Phenogram\Framework\Tests\Feature;

use Phenogram\Framework\TelegramBotApiClient;
use PHPUnit\Framework\TestCase;

class TelegramBotApiClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->token = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11';
    }

    public function testSendRequest()
    {
        self::markTestSkipped('For internal use only');
        $client = new TelegramBotApiClient($this->token);

        $response = $client->sendRequest('getMe', '');
        dd($response);
    }
}
