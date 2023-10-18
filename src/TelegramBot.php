<?php

namespace Shanginn\TelegramBotApiFramework;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use PsrDiscovery\Discover;
use React\EventLoop\Loop;
use React\Promise\PromiseInterface;
use Shanginn\TelegramBotApiBindings\TelegramBotApi;
use Shanginn\TelegramBotApiBindings\TelegramBotApiClientInterface;
use Shanginn\TelegramBotApiBindings\Types\Update;
use function React\Async\async;
use function React\Async\await;
use function React\Promise\all;

class TelegramBot
{
    public TelegramBotApi $api;

    // TODO: remove?
    protected TelegramBotApiClientInterface $botClient;

    /**
     * @var array<UpdateHandlerInterface>
     */
    protected array $handlers = [];

    private LoggerInterface $logger;

    public function __construct(
        protected readonly string $token,
        TelegramBotApiClientInterface $botClient = null,
        LoggerInterface $logger = null,
    ) {
        $this->botClient = $botClient ?? new TelegramBotApiClient($token);
        $this->api = new TelegramBotApi($this->botClient);
        $this->logger = $logger ?? Discover::log() ?? new NullLogger();
    }

    public function run(
        int $offset = null,
        ?int $limit = 100,
        int $timeout = null,
        array $allowedUpdates = null,
    ): void
    {
        $offset = $offset ?? 1;
        $timeout = $timeout ?? 15;

        Loop::addPeriodicTimer(1, async(function() use (&$offset, $limit, $allowedUpdates, $timeout) {
            $this->logger->debug('Polling updates', [
                'offset' => $offset,
                'limit' => $limit,
                'allowedUpdates' => $allowedUpdates,
                'timeout' => $timeout,
            ]);

            $updates = $this->api->getUpdates(
                offset: $offset,
                limit: $limit,
                allowedUpdates: $allowedUpdates,
            );

            $promises = [];
            foreach ($updates as $update) {
                $promises[] = $this->handleUpdate($update);
                $offset = max($offset, $update->updateId + 1);
            }

            // Wait for all update handling promises to settle
            await(all($promises));
        }));

        Loop::run();  // Start the event loop
    }

    public function addHandler(UpdateHandlerInterface $handler): self
    {
        $this->handlers[] = $handler;

        return $this;
    }

    public function handleUpdate(Update $update): PromiseInterface
    {
        return async(function () use ($update) {
            foreach ($this->handlers as $handler) {
                if (!$handler->supports($update)) {
                    continue;
                }

                try {
                    await($handler->handle($update, $this));
                } catch (\Throwable $e) {
                    $this->logger->error('Error while handling update', [
                        'update' => $update,
                        'handler' => get_class($handler),
                        'exception' => $e,
                    ]);
                }
            }
        })();
    }
}
