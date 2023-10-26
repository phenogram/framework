<?php

namespace Shanginn\TelegramBotApiFramework;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use PsrDiscovery\Discover;
use React\EventLoop\Loop;
use React\Promise\PromiseInterface;
use React\Promise\Timer;
use Shanginn\TelegramBotApiBindings\TelegramBotApi;
use Shanginn\TelegramBotApiBindings\TelegramBotApiClientInterface;
use Shanginn\TelegramBotApiBindings\TelegramBotApiSerializer;
use Shanginn\TelegramBotApiBindings\TelegramBotApiSerializerInterface;
use Shanginn\TelegramBotApiBindings\Types\Update;
use Shanginn\TelegramBotApiFramework\Handler\UpdateHandlerInterface;

use function React\Async\parallel;
use function React\Promise\all;

class TelegramBot
{
    public TelegramBotApi $api;

    /**
     * @var array<UpdateHandlerInterface>
     */
    private array $handlers = [];

    private LoggerInterface $logger;

    /**
     * @var array<PromiseInterface>
     */
    private array $promises = [];

    private ?PromiseInterface $pullUpdatesPromise = null;

    public function __construct(
        protected readonly string $token,
        TelegramBotApiClientInterface $botClient = null,
        TelegramBotApiSerializerInterface $serializer = null,
        LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? Discover::log() ?? new NullLogger();

        $this->api = new TelegramBotApi(
            client: $botClient ?? new TelegramBotApiClient($token),
            serializer: $serializer ?? new TelegramBotApiSerializer(),
        );
    }

    public function run(
        int $offset = null,
        ?int $limit = 100,
        int $timeout = null,
        array $allowedUpdates = null,
    ): void {
        $offset = $offset ?? 1;
        $timeout = $timeout ?? 15;

        $this->logger->info(sprintf(
            'Starting bot with offset %d, limit %d, timeout %d',
            $offset,
            $limit,
            $timeout,
        ));

        $this->pullUpdatesPromise = $this->pullUpdates(
            offset: $offset,
            limit: $limit,
            timeout: $timeout,
            allowedUpdates: $allowedUpdates,
        );

        Loop::run();
    }

    public function stop(float $timeout = null): void
    {
        $this->logger->info('Stopping bot');

        $this->pullUpdatesPromise->cancel();

        $this->logger->info(sprintf(
            'Waiting for pending promises%s',
            $timeout !== null ? sprintf(' for %d seconds', $timeout) : '',
        ));

        if ($timeout !== null) {
            $waitingPromise = Timer\timeout(all($this->promises), $timeout);
        } else {
            $waitingPromise = all($this->promises);
        }

        $waitingPromise->then(
            fn () => Loop::stop()
        );
    }

    private function pullUpdates(
        int $offset,
        ?int $limit,
        int $timeout,
        ?array $allowedUpdates,
    ): PromiseInterface {
        $this->logger->debug('Polling updates', [
            'offset' => $offset,
            'limit' => $limit,
            'allowedUpdates' => $allowedUpdates,
            'timeout' => $timeout,
        ]);

        return $this->api
            ->getUpdates(
                offset: $offset,
                limit: $limit,
                allowedUpdates: $allowedUpdates,
            )
            ->then(
                function ($updates) use (&$offset) {
                    $this->logger->debug('Got updates', [
                        'updates' => $updates,
                    ]);

                    foreach ($updates as $update) {
                        $offset = max($offset, $update->updateId + 1);

                        $this->promises[] = $this->handleUpdate($update);
                    }
                },
                function (\Throwable $e) {
                    $waitTime = 5;

                    $this->logger->error(sprintf(
                        'Error while pooling updates %s. Waiting for %d seconds until next pull',
                        $e->getMessage(),
                        $waitTime,
                    ), [
                        'error' => $e,
                    ]);

                    return Timer\sleep(5);
                }
            )
            ->then(
                // Can't use `fn() =>` syntax here, because we need to capture $offset by reference
                function () use (&$offset, $limit, $timeout, $allowedUpdates) {
                    return $this->pullUpdates($offset, $limit, $timeout, $allowedUpdates);
                }
            );
    }

    public function addHandler(UpdateHandlerInterface $handler): self
    {
        $this->handlers[] = $handler;

        return $this;
    }

    /**
     * @return PromiseInterface<PromiseInterface[]>
     */
    public function handleUpdate(Update $update): PromiseInterface
    {
        $supportedHandlers = array_filter(
            $this->handlers,
            fn (UpdateHandlerInterface $handler) => $handler->supports($update)
        );

        $tasks = array_map(
            fn (UpdateHandlerInterface $handler) => fn () => $handler->handle($update, $this),
            $supportedHandlers
        );

        return parallel($tasks);
    }
}
