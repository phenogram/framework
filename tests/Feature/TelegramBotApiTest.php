<?php

namespace Phenogram\Framework\Tests\Feature;

use Phenogram\Bindings\Api;
use Phenogram\Framework\Client\TelegramBotApiClient;
use Phenogram\Framework\Type\BufferedFile;
use Phenogram\Framework\Type\LocalFile;
use PHPUnit\Framework\TestCase;

class TelegramBotApiTest extends TestCase
{
    private Api $api;
    private string $token;
    private string $testChatId;

    protected function setUp(): void
    {
        parent::setUp();

        $token = $_ENV['TELEGRAM_BOT_TOKEN'];
        $testChatId = $_ENV['TEST_CHAT_ID'];

        if ($token === null) {
            $this->markTestSkipped('TELEGRAM_BOT_TOKEN not set');
        }

        $this->token = $token;

        if ($testChatId === null) {
            $this->markTestSkipped('TEST_CHAT_ID not set');
        }

        $this->testChatId = $testChatId;

        $this->api = new Api(
            client: new TelegramBotApiClient($this->token)
        );
    }

    public function testSendDocument()
    {
        $file = __DIR__ . '/../../README.md';

        $message = $this->api->sendDocument(
            chatId: $this->testChatId,
            document: new LocalFile($file),
            caption: 'Test caption'
        );

        self::assertNotNull($message->document);
    }

    public function testSendPhoto()
    {
        $photo = __DIR__ . '/../assets/image.jpeg';

        $message = $this->api->sendPhoto(
            chatId: $this->testChatId,
            photo: new LocalFile($photo),
            caption: 'Test caption'
        );

        self::assertNotNull($message->photo);
    }

    public function testCacheDocument()
    {
        $fileContent = file_get_contents(__DIR__ . '/../../README.md');

        $file = new BufferedFile($fileContent, 'README.md');

        $message = $this->api->sendDocument(
            chatId: $this->testChatId,
            document: $file,
            caption: 'Test caption'
        );

        self::assertNotNull($message->document);

        $message = $this->api->sendDocument(
            chatId: $this->testChatId,
            document: $file,
            caption: 'Test caption'
        );

        self::assertNotNull($message->document);
    }
}
