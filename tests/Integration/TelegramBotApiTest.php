<?php

declare(strict_types=1);

namespace Phenogram\Framework\Tests\Integration;

use Phenogram\Bindings\Api;
use Phenogram\Framework\TelegramBotApiClient;
use Phenogram\Framework\Tests\Support\Environment;
use Phenogram\Framework\Type\BufferedFile;
use Phenogram\Framework\Type\LocalFile;
use PHPUnit\Framework\TestCase;

final class TelegramBotApiTest extends TestCase
{
    private Api $api;
    private string $testChatId;

    protected function setUp(): void
    {
        parent::setUp();

        if (Environment::get('RUN_TELEGRAM_INTEGRATION') !== '1') {
            $this->markTestSkipped('Set RUN_TELEGRAM_INTEGRATION=1 to run live Telegram tests.');
        }

        $token = Environment::get('TELEGRAM_BOT_TOKEN');
        $testChatId = Environment::get('TEST_CHAT_ID');

        if ($token === null || trim($token) === '') {
            $this->markTestSkipped('TELEGRAM_BOT_TOKEN is not set.');
        }

        if ($testChatId === null || trim($testChatId) === '') {
            $this->markTestSkipped('TEST_CHAT_ID is not set.');
        }

        $this->testChatId = $testChatId;
        $this->api = new Api(
            client: new TelegramBotApiClient($token),
        );
    }

    public function testSendDocument(): void
    {
        $message = $this->api->sendDocument(
            chatId: $this->testChatId,
            document: new LocalFile(dirname(__DIR__, 2) . '/README.md'),
            caption: 'Phenogram integration test',
        );

        self::assertNotNull($message->document);
    }

    public function testSendPhoto(): void
    {
        $message = $this->api->sendPhoto(
            chatId: $this->testChatId,
            photo: new LocalFile(dirname(__DIR__) . '/assets/image.jpeg'),
            caption: 'Phenogram integration test',
        );

        self::assertNotNull($message->photo);
    }

    public function testBufferedDocumentCanBeSentTwice(): void
    {
        $file = new BufferedFile(
            file_get_contents(dirname(__DIR__, 2) . '/README.md'),
            'README.md',
        );

        $firstMessage = $this->api->sendDocument(
            chatId: $this->testChatId,
            document: $file,
            caption: 'Phenogram integration test',
        );
        $secondMessage = $this->api->sendDocument(
            chatId: $this->testChatId,
            document: $file,
            caption: 'Phenogram integration test',
        );

        self::assertNotNull($firstMessage->document);
        self::assertNotNull($secondMessage->document);
    }
}
