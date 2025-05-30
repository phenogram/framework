<?php

declare(strict_types=1);

namespace Phenogram\Framework\Tests\Feature;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Phenogram\Bindings\Api;
use Phenogram\Bindings\Factories\UpdateFactory;
use Phenogram\Bindings\Types\Interfaces\UpdateInterface;
use Phenogram\Framework\Handler\UpdateHandlerInterface;
use Phenogram\Framework\TelegramBot;
use Phenogram\Framework\Tests\Mock\MockTelegramBotApiClient;
use Phenogram\Framework\Tests\TestCase;

use function Amp\delay;
use function Amp\Future\await;

final class TelegramBotTest extends TestCase
{
    public function testUpdateHandlersAreWorkingInPulling()
    {
        $logger = new Logger('test', [
            new StreamHandler('php://stdout'),
        ]);

        $client = new MockTelegramBotApiClient(
            1.5,
            []
        );

        $updateResponse = [['update_id' => 437567765]];

        $client->addResponse(
            $updateResponse,
            'getUpdates'
        );

        $bot = new TelegramBot(
            token: 'token',
            api: new Api(
                client: $client
            ),
        );

        $bot->logger = $logger;

        $counter = 0;

        $bot->addHandler(
            new class($counter) implements UpdateHandlerInterface {
                public function __construct(
                    private int &$counter,
                ) {
                }

                public function handle(UpdateInterface $update, TelegramBot $bot)
                {
                    ++$this->counter;

                    $bot->stop();
                }
            }
        );

        $bot->run();

        $this->assertEquals(1, $counter);
    }

    public function testCanHandleSingleUpdateWithoutEventLoop()
    {
        $bot = new TelegramBot(
            token: 'token',
        );

        $counter = 0;

        $bot->addHandler(function () use (&$counter) {
            delay(1);

            ++$counter;
        });

        $bot->handleUpdate(UpdateFactory::make())[0]->await();

        $this->assertEquals(1, $counter);
    }

    public function test1000UpdateHandlersInParallel()
    {
        $bot = new TelegramBot(
            token: 'token',
        );

        $counter = 0;
        foreach (range(1, 1000) as $i) {
            $bot->addHandler(function () use (&$counter) {
                delay(1);

                ++$counter;
            });
        }

        await($bot->handleUpdate(UpdateFactory::make()));

        $this->assertEquals(1000, $counter);
    }

    public function testExceptionInUpdateHandlerIsCaught()
    {
        $logger = new Logger('test', [
            new StreamHandler('php://stdout'),
        ]);

        $client = new MockTelegramBotApiClient(
            10,
            []
        );

        $updateResponse = [['update_id' => 437567765]];
        $client->addResponse(
            $updateResponse,
            'getUpdates'
        );

        $bot = new TelegramBot(
            token: 'token',
            api: new Api(
                client: $client
            ),
        );

        $bot->logger = $logger;

        $customException = new class extends \Exception {
            protected $message = 'Custom exception';
        };

        $counter = 0;

        $exceptionHandler = function (\Throwable $e, TelegramBot $bot) use (&$counter, $customException) {
            if ($e->getPrevious() instanceof $customException) {
                ++$counter;
            }

            $bot->logger->error($e->getMessage());
        };

        $bot->errorHandler = $exceptionHandler;

        $bot->addHandler(function (UpdateInterface $update, TelegramBot $bot) use (&$counter) {
            ++$counter;

            $bot->stop();
        });

        $bot->addHandler(fn () => throw new $customException());

        $bot->run();

        $this->assertEquals(2, $counter);
    }
}
