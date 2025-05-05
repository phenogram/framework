<?php

declare(strict_types=1);

namespace Phenogram\Framework\Tests\Readme;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Phenogram\Bindings\Factories\ChatFactory;
use Phenogram\Bindings\Factories\MessageFactory;
use Phenogram\Bindings\Factories\UpdateFactory;
use Phenogram\Bindings\Types\Interfaces\UpdateInterface;
use Phenogram\Framework\TelegramBot;
use Phenogram\Framework\Tests\TestCase;

use function Amp\async;
use function Amp\delay;

class ReadmeExamplesTest extends TestCase
{
    private TelegramBot $bot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->token = $_ENV['TELEGRAM_BOT_TOKEN'];
        $this->testChatId = $_ENV['TEST_CHAT_ID'];

        if ($this->token === null) {
            $this->markTestSkipped('TELEGRAM_BOT_TOKEN not set');
        }

        if ($this->testChatId === null) {
            $this->markTestSkipped('TEST_CHAT_ID not set');
        }

        $this->testChatId = (int) $this->testChatId;

        $logger = new Logger('test', [
            new StreamHandler('php://stdout'),
        ]);

        $this->bot = new TelegramBot(
            token: $this->token,
            logger: $logger,
        );

        $this->bot->errorHandler = fn (\Throwable $e, TelegramBot $bot) => dump($e);
    }

    public function testSimpleHandlerExample(): void
    {
        $this->bot
            ->addHandler(fn (UpdateInterface $update, TelegramBot $bot) => $bot->api->sendMessage(
                chatId: $update->message->chat->id,
                text: $update->message->text
            ))
            ->supports(fn (UpdateInterface $update) => $update->message?->text !== null);

        // Симулируем отправку сообщения боту через 1 секунду
        async(function () {
            $update = UpdateFactory::make(
                message: MessageFactory::make(
                    chat: ChatFactory::make(id: $this->testChatId),
                    text: 'Hello world',
                )
            );

            delay(1);

            $this->bot->handleUpdate($update);
            $this->bot->stop();
        });

        $this->bot->run();

        self::assertTrue(true);
    }
}
