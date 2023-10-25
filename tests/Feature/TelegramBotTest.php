<?php

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use React\EventLoop\Loop;
use React\Promise\PromiseInterface;
use Shanginn\TelegramBotApiBindings\Types\Update;
use Shanginn\TelegramBotApiFramework\Handler\UpdateHandlerInterface;
use Shanginn\TelegramBotApiFramework\TelegramBot;
use Shanginn\TelegramBotApiFramework\Tests\Mock\MockTelegramBotApiClient;

use function React\Promise\resolve;

test('Update handlers are working', function () {
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

            public function handle(Update $update, TelegramBot $bot): PromiseInterface
            {
                return resolve(++$this->counter);
            }
        }
    );

    Loop::addTimer(2, fn () => $bot->stop());

    $bot->run();

    expect($counter)->toBe(1);
});
