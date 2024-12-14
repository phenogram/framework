<?php

declare(strict_types=1);

namespace Phenogram\Framework\UpdatePuller;

use Amp\Future;
use Amp\TimeoutCancellation;
use Phenogram\Bindings\Types\Update;
use Phenogram\Bindings\Types\UpdateType;
use Phenogram\Framework\Exception\PhenogramException;
use Phenogram\Framework\Exception\UpdatePullingException;
use Phenogram\Framework\TelegramBot;
use Revolt\EventLoop;

use function Amp\async;
use function Amp\delay;
use function Amp\Future\awaitAll;

class UpdatePuller
{
    private BotStatus $status = BotStatus::stopped;

    /**
     * @var array<Future>
     */
    private array $tasks = [];

    public function __construct(
        private TelegramBot $bot,
        private float $poolingErrorTimeout = 5.0,
    ) {
    }

    /**
     * @param list<UpdateType>|null $allowedUpdates
     */
    public function run(
        ?int $offset = null,
        ?int $limit = 100,
        int $timeout = null,
        array $allowedUpdates = null,
    ): void {
        $offset = $offset ?? 1;
        $timeout = $timeout ?? 15;

        $this->status = BotStatus::starting;

        $this->bot->logger->info(sprintf(
            'Starting bot with offset %d, limit %d, timeout %d',
            $offset,
            $limit,
            $timeout,
        ));

        foreach ($this->pullUpdates($offset, $limit, $timeout, $allowedUpdates) as $update) {
            $this->tasks[$update->updateId] = async(function () use ($update) {
                [$exceptions] = awaitAll($this->bot->handleUpdate($update));

                /** @var \Throwable $exception */
                foreach ($exceptions as $exception) {
                    ($this->bot->errorHandler)(new PhenogramException(
                        message: sprintf('Error while handling update: %s', $exception->getMessage()),
                        previous: $exception,
                    ), $this->bot);
                }

                unset($this->tasks[$update->updateId]);
            });
        }
    }

    public function stop(float $timeout = 0.0): void
    {
        $this->bot->logger->info('Stopping bot');

        $this->status = BotStatus::stopping;

        if ($timeout !== 0.0) {
            $this->bot->logger->info(sprintf('Waiting for %d seconds before stopping', $timeout));
        }

        $timeoutTimer = new TimeoutCancellation($timeout);

        [$exceptions] = awaitAll($this->tasks, $timeoutTimer);

        /** @var \Throwable $exception */
        foreach ($exceptions as $exception) {
            ($this->bot->errorHandler)(new PhenogramException(
                message: sprintf('Error while stopping bot: %s', $exception->getMessage()),
                previous: $exception,
            ), $this->bot);
        }

        $this->status = BotStatus::stopped;
    }

    /**
     * @param list<UpdateType>|null $allowedUpdates
     *
     * @return \Generator<Update>
     */
    private function pullUpdates(
        int $offset,
        ?int $limit,
        int $timeout,
        ?array $allowedUpdates,
    ): \Generator {
        $this->status = BotStatus::started;

        if ($allowedUpdates !== null) {
            $allowedUpdates = array_map(
                fn (UpdateType $type) => $type->value,
                $allowedUpdates
            );
        }

        $oldErrorHandler = EventLoop::getErrorHandler();
        EventLoop::setErrorHandler($this->bot->errorHandler);

        while ($this->status !== BotStatus::stopping) {
            $this->bot->logger->debug('Polling updates', [
                'offset' => $offset,
                'limit' => $limit,
                'allowedUpdates' => $allowedUpdates,
                'timeout' => $timeout,
            ]);

            try {
                $updates = $this->bot->api->getUpdates(
                    offset: $offset,
                    limit: $limit,
                    allowedUpdates: $allowedUpdates,
                );
            } catch (\Throwable $e) {
                $message = "Error while pooling updates: '{$e->getMessage()}'.";

                if ($this->poolingErrorTimeout !== 0.0) {
                    $message .= " Waiting for {$this->poolingErrorTimeout} seconds until next pull";
                }

                ($this->bot->errorHandler)(new UpdatePullingException(
                    message: $message,
                    previous: $e,
                ), $this->bot);

                if ($this->poolingErrorTimeout !== 0.0) {
                    try {
                        // 🥴 при резком исчезновении интернета на этом месте в лупе возникает ошибка
                        // "Stream watcher invoked after stream closed" (Http2ConnectionProcessor.php:1588)
                        // Может я не правильно использую что-то, но пока так
                        delay($this->poolingErrorTimeout);
                    } catch (\Throwable) {
                        delay($this->poolingErrorTimeout);
                    }
                }

                continue;
            }

            $this->bot->logger->debug('Got updates', [
                'updates' => $updates,
            ]);

            $offset = array_reduce(
                $updates,
                fn ($max, $update) => max($max, $update->updateId + 1),
                $offset
            );

            foreach ($updates as $update) {
                yield $update;
            }
        }

        EventLoop::setErrorHandler($oldErrorHandler);
    }
}
