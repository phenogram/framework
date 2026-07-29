<?php

declare(strict_types=1);

namespace Phenogram\Framework\Tests\Readme;

use Phenogram\Bindings\Api;
use Phenogram\Bindings\Types\Chat;
use Phenogram\Bindings\Types\Message;
use Phenogram\Bindings\Types\Update;
use Phenogram\Bindings\Types\User;
use Phenogram\Framework\TelegramBot;
use Phenogram\Framework\Tests\Mock\MockTelegramBotApiClient;
use Phenogram\Framework\Type\BufferedFile;
use Phenogram\Framework\Type\LocalFile;
use Phenogram\Framework\Type\ReadableStreamFile;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

use function Async\await_all;
use function Phenogram\Framework\Examples\addPingRoute;
use function Phenogram\Framework\Examples\createEchoBot;
use function Phenogram\Framework\Examples\sendExampleFiles;

require_once dirname(__DIR__, 2) . '/examples/echo-bot.php';
require_once dirname(__DIR__, 2) . '/examples/route-group.php';
require_once dirname(__DIR__, 2) . '/examples/send-files.php';

final class ReadmeExamplesTest extends TestCase
{
    public function testEchoBotExampleWithoutNetwork(): void
    {
        $client = new MockTelegramBotApiClient(responseTimeout: 0);
        $client->addResponse($this->messageResponse(text: 'Hello'), 'sendMessage');

        $bot = createEchoBot(
            token: 'test-token',
            api: new Api($client),
            logger: new NullLogger(),
        );

        [, $errors] = await_all($bot->handleUpdate($this->textUpdate(userId: 7, text: 'Hello')));

        self::assertCount(1, $client->requests);
        self::assertSame('sendMessage', $client->requests[0]['method']);
        self::assertSame(1001, $client->requests[0]['data']['chat_id']);
        self::assertSame('Hello', $client->requests[0]['data']['text']);
        self::assertSame([], $errors);
    }

    public function testRouteGroupExampleWithoutNetwork(): void
    {
        $client = new MockTelegramBotApiClient(responseTimeout: 0);
        $client->addResponse($this->messageResponse(text: 'pong'), 'sendMessage');

        $bot = new TelegramBot(
            token: 'test-token',
            api: new Api($client),
            logger: new NullLogger(),
        );
        addPingRoute($bot, allowedUserId: 7);

        [, $ignoredErrors] = await_all($bot->handleUpdate($this->textUpdate(userId: 8, text: '/ping')));
        [, $acceptedErrors] = await_all($bot->handleUpdate($this->textUpdate(userId: 7, text: '/ping')));

        self::assertCount(1, $client->requests);
        self::assertSame('pong', $client->requests[0]['data']['text']);
        self::assertSame([], $ignoredErrors);
        self::assertSame([], $acceptedErrors);
    }

    public function testFileExamplesWithoutNetwork(): void
    {
        $client = new MockTelegramBotApiClient(responseTimeout: 0);
        foreach (range(1, 3) as $index) {
            $client->addResponse(
                $this->messageResponse(messageId: $index),
                'sendDocument',
            );
        }

        $messages = sendExampleFiles(
            api: new Api($client),
            chatId: 1001,
            path: dirname(__DIR__, 2) . '/README.md',
        );

        self::assertCount(3, $messages);
        self::assertInstanceOf(LocalFile::class, $client->requests[0]['data']['document']);
        self::assertInstanceOf(ReadableStreamFile::class, $client->requests[1]['data']['document']);
        self::assertInstanceOf(BufferedFile::class, $client->requests[2]['data']['document']);
    }

    /**
     * @return array<string, mixed>
     */
    private function messageResponse(int $messageId = 1, ?string $text = null): array
    {
        return [
            'message_id' => $messageId,
            'date' => 1_700_000_000,
            'chat' => [
                'id' => 1001,
                'type' => 'private',
            ],
            'text' => $text,
        ];
    }

    private function textUpdate(int $userId, string $text): Update
    {
        return new Update(
            updateId: 1,
            message: new Message(
                messageId: 1,
                date: 1_700_000_000,
                chat: new Chat(id: 1001, type: 'private'),
                from: new User(id: $userId, isBot: false, firstName: 'Test'),
                text: $text,
            ),
        );
    }
}
