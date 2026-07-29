<?php

declare(strict_types=1);

namespace Phenogram\Framework\Tests\Feature;

use Async\AsyncCancellation;
use Phenogram\Bindings\Api;
use Phenogram\Bindings\Factories\UpdateFactory;
use Phenogram\Bindings\Types\Interfaces\UpdateInterface;
use Phenogram\Framework\Handler\UpdateHandlerInterface;
use Phenogram\Framework\TelegramBot;
use Phenogram\Framework\Tests\Mock\MockTelegramBotApiClient;
use Phenogram\Framework\Tests\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\NullLogger;

use function Async\await_all;
use function Async\delay;
use function Async\spawn;

final class TelegramBotTest extends TestCase
{
    public function testUpdateHandlersAreWorkingInPulling()
    {
        $client = new MockTelegramBotApiClient(
            0.02,
            []
        );

        $updateResponse = [['update_id' => 437567765]];

        $client->addResponse(
            $updateResponse,
            'getUpdates'
        );
        $client->addResponse(
            [['update_id' => 437567766]],
            'getUpdates',
        );

        $bot = new TelegramBot(
            token: 'token',
            api: new Api(
                client: $client
            ),
            logger: new NullLogger(),
        );

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

                    delay(10);
                    $bot->stop();
                }
            }
        );

        $bot->run();

        $this->assertEquals(1, $counter);
        $this->assertSame(15, $client->requests[0]['data']['timeout']);
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

        [, $errors] = await_all($bot->handleUpdate(UpdateFactory::make()));

        $this->assertEquals(1, $counter);
        $this->assertSame([], $errors);
    }

    public function test1000UpdateHandlersInParallel()
    {
        $bot = new TelegramBot(
            token: 'token',
        );

        $counter = 0;
        foreach (range(1, 1000) as $i) {
            $bot->addHandler(function () use (&$counter) {
                delay(1000);

                ++$counter;
            });
        }

        [, $errors] = await_all($bot->handleUpdate(UpdateFactory::make()));

        $this->assertEquals(1000, $counter);
        $this->assertSame([], $errors);
    }

    public function testExceptionInUpdateHandlerIsCaught()
    {
        $client = new MockTelegramBotApiClient(
            0.02,
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
            logger: new NullLogger(),
        );

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

            delay(10);
            $bot->stop();
        });

        $bot->addHandler(fn () => throw new $customException());

        $bot->run();

        $this->assertEquals(2, $counter);
    }

    public function testRouteFailureIsIsolatedToOneUpdateAndPollingContinues(): void
    {
        $client = new MockTelegramBotApiClient(0.01, []);
        $client->addResponse([['update_id' => 437567765]], 'getUpdates');
        $client->addResponse([['update_id' => 437567766]], 'getUpdates');

        $bot = new TelegramBot(
            token: 'token',
            api: new Api(client: $client),
            logger: new NullLogger(),
        );

        $routeFailure = new \RuntimeException('Route matching failed');
        $errors = [];
        $handledUpdates = [];
        $bot->errorHandler = static function (\Throwable $error) use (&$errors): void {
            $errors[] = $error;
        };

        $bot->addHandler(
            static function (UpdateInterface $update, TelegramBot $bot) use (&$handledUpdates): void {
                $handledUpdates[] = $update->updateId;
                $bot->stop();
            },
        )->supports(static function (UpdateInterface $update) use ($routeFailure): bool {
            if ($update->updateId === 437567765) {
                throw $routeFailure;
            }

            return true;
        });

        $bot->run();

        self::assertSame([437567766], $handledUpdates);
        self::assertCount(1, $errors);
        self::assertSame('Error while handling update: Route matching failed', $errors[0]->getMessage());
        self::assertSame($routeFailure, $errors[0]->getPrevious());
        self::assertSame(437567766, $client->requests[1]['data']['offset']);
    }

    public function testStopFromHandlerCancelsSlowSiblingAfterGracePeriod(): void
    {
        $client = new MockTelegramBotApiClient(0.02, []);
        $client->addResponse([['update_id' => 437567765]], 'getUpdates');

        $bot = new TelegramBot(
            token: 'token',
            api: new Api(client: $client),
            logger: new NullLogger(),
        );

        $slowHandlerCompleted = false;
        $slowHandlerCleanedUp = false;

        $bot->addHandler(static function (UpdateInterface $update, TelegramBot $bot): void {
            delay(5);
            $bot->stop(0.01);
        });
        $bot->addHandler(static function () use (&$slowHandlerCompleted, &$slowHandlerCleanedUp): void {
            try {
                delay(1000);
                $slowHandlerCompleted = true;
            } finally {
                $slowHandlerCleanedUp = true;
            }
        });

        $startedAt = hrtime(true);
        $bot->run();
        $elapsedMilliseconds = (hrtime(true) - $startedAt) / 1_000_000;

        self::assertFalse($slowHandlerCompleted);
        self::assertTrue($slowHandlerCleanedUp);
        self::assertLessThan(250, $elapsedMilliseconds);
    }

    public function testRuntimeCancellationIsNotSwallowedByPollingRetry(): void
    {
        $bot = new TelegramBot(
            token: 'token',
            api: new Api(client: new MockTelegramBotApiClient(0.02, [])),
            logger: new NullLogger(),
        );

        $run = spawn($bot->run(...));
        delay(5);
        $run->cancel();

        [, $errors] = await_all([$run]);

        self::assertInstanceOf(AsyncCancellation::class, $errors[0]);
        $this->expectException(\LogicException::class);
        $bot->stop();
    }

    public function testStopInterruptsActiveLongPoll(): void
    {
        $bot = new TelegramBot(
            token: 'token',
            api: new Api(client: new MockTelegramBotApiClient(0.3, [])),
            logger: new NullLogger(),
        );

        $stopper = spawn(static function () use ($bot): void {
            delay(10);
            $bot->stop(0.01);
        });

        $startedAt = hrtime(true);
        $bot->run();
        $elapsedMilliseconds = (hrtime(true) - $startedAt) / 1_000_000;
        [, $errors] = await_all([$stopper]);

        self::assertSame([], $errors);
        self::assertLessThan(100, $elapsedMilliseconds);
    }

    public function testReadyStopBeforePollingCoroutineIsPreserved(): void
    {
        $client = new MockTelegramBotApiClient(0.05, []);
        $bot = new TelegramBot(
            token: 'token',
            api: new Api(client: $client),
            logger: new NullLogger(),
        );

        $stopper = spawn(static function () use ($bot): void {
            $bot->stop(0.01);
        });

        $bot->run();
        [, $errors] = await_all([$stopper]);

        self::assertSame([], $errors);
        self::assertSame([], $client->requests);
    }

    public function testConcurrentStopRequestsAreIdempotent(): void
    {
        $client = new MockTelegramBotApiClient(0.01, []);
        $client->addResponse([['update_id' => 437567765]], 'getUpdates');

        $bot = new TelegramBot(
            token: 'token',
            api: new Api(client: $client),
            logger: new NullLogger(),
        );

        $errors = [];
        $bot->errorHandler = static function (\Throwable $error) use (&$errors): void {
            $errors[] = $error;
        };

        $stops = 0;
        $bot->addHandler(static function (UpdateInterface $update, TelegramBot $bot) use (&$stops): void {
            delay(5);
            ++$stops;
            $bot->stop(0.05);
        });
        $bot->addHandler(static function (UpdateInterface $update, TelegramBot $bot) use (&$stops): void {
            delay(6);
            ++$stops;
            $bot->stop(0.05);
        });

        $bot->run();

        self::assertSame(2, $stops);
        self::assertSame([], $errors);
    }

    public function testLaterShorterStopTightensActiveGracePeriod(): void
    {
        $client = new MockTelegramBotApiClient(0.01, []);
        $client->addResponse([['update_id' => 437567765]], 'getUpdates');

        $bot = new TelegramBot(
            token: 'token',
            api: new Api(client: $client),
            logger: new NullLogger(),
        );

        $slowHandlerCompleted = false;
        $bot->addHandler(static function (UpdateInterface $update, TelegramBot $bot): void {
            delay(5);
            $bot->stop(0.2);
        });
        $bot->addHandler(static function (UpdateInterface $update, TelegramBot $bot): void {
            delay(20);
            $bot->stop(0.01);
        });
        $bot->addHandler(static function () use (&$slowHandlerCompleted): void {
            delay(120);
            $slowHandlerCompleted = true;
        });

        $startedAt = hrtime(true);
        $bot->run();
        $elapsedMilliseconds = (hrtime(true) - $startedAt) / 1_000_000;

        self::assertFalse($slowHandlerCompleted);
        self::assertLessThan(80, $elapsedMilliseconds);
    }

    public function testStopRequestedByStartupLoggerIsPreserved(): void
    {
        $client = new MockTelegramBotApiClient(0, []);
        $bot = new TelegramBot(
            token: 'token',
            api: new Api(client: $client),
            logger: new NullLogger(),
        );

        $bot->logger = new class($bot) extends AbstractLogger {
            private bool $stopped = false;

            public function __construct(
                private readonly TelegramBot $bot,
            ) {
            }

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                if (!$this->stopped && str_starts_with((string) $message, 'Starting bot')) {
                    $this->stopped = true;
                    $this->bot->stop();
                }
            }
        };

        $bot->run();

        self::assertSame([], $client->requests);
    }
}
