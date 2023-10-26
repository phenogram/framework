<?php

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use React\EventLoop\Loop;
use Shanginn\TelegramBotApiBindings\Types\Update;
use Shanginn\TelegramBotApiFramework\Handler\UpdateHandlerInterface;
use Shanginn\TelegramBotApiFramework\TelegramBot;
use Shanginn\TelegramBotApiFramework\Tests\Mock\MockTelegramBotApiClient;

use function React\Async\await;

test('Update handlers are working in pulling', function () {
    $logger = new Logger('test', [
        new StreamHandler('php://stdout'),
    ]);

    $client = new MockTelegramBotApiClient(
        1.5,
        '[]'
    );

    $updateResponse = '[{"update_id":437567765}]';
    $client->addResponse(
        $updateResponse,
        'getUpdates'
    );

    $bot = new TelegramBot(
        token: 'token',
        logger: $logger,
        botClient: $client,
    );

    $counter = 0;

    $bot->addHandler(
        new class($counter) implements UpdateHandlerInterface {
            public function __construct(
                private int &$counter
            ) {
            }

            public function supports(Update $update): bool
            {
                return true;
            }

            public function handle(Update $update, TelegramBot $bot)
            {
                await(
                    \React\Promise\Timer\sleep(1),
                );

                ++$this->counter;
            }
        }
    );

    Loop::addTimer(2, fn () => $bot->stop());

    $bot->run();

    expect($counter)->toBe(1);
});

test('Can handle single update without event loop', function () {
    $bot = new TelegramBot(
        token: 'token',
    );

    $counter = 0;

    $bot->addHandler(
        new class($counter) implements UpdateHandlerInterface {
            public function __construct(
                private int &$counter
            ) {
            }

            public function supports(Update $update): bool
            {
                return true;
            }

            public function handle(Update $update, TelegramBot $bot): void
            {
                await(React\Promise\Timer\sleep(1));

                ++$this->counter;
            }
        }
    );

    $bot->handleUpdateSync(
        new Update(
            updateId: 1,
        )
    );

    expect($counter)->toBe(1);
});
