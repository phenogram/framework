<?php

declare(strict_types=1);

namespace Phenogram\Framework\UpdatePuller;

use Async\AsyncCancellation;
use Async\Coroutine;
use Async\OperationCanceledException;
use Async\Scope;
use Phenogram\Bindings\Types\Interfaces\UpdateInterface;
use Phenogram\Bindings\Types\UpdateType;
use Phenogram\Framework\Exception\PhenogramException;
use Phenogram\Framework\Exception\UpdatePullingException;
use Phenogram\Framework\TelegramBot;

use function Async\current_coroutine;
use function Async\delay;
use function Async\protect;
use function Async\timeout;

class UpdatePuller
{
    private BotStatus $status = BotStatus::stopped;

    /**
     * @var array<int, Coroutine>
     */
    private array $tasks = [];

    private ?Scope $updatesScope = null;

    private ?Coroutine $pollingCoroutine = null;

    private float $stopTimeout = 5.0;

    private ?float $stopDeadlineMilliseconds = null;

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
        ?int $timeout = null,
        ?array $allowedUpdates = null,
    ): void {
        $offset = $offset ?? 1;
        $timeout = $timeout ?? 15;

        if ($this->status !== BotStatus::stopping) {
            $this->status = BotStatus::started;
        }

        $this->pollingCoroutine = current_coroutine();

        try {
            $this->bot->logger->info(sprintf(
                'Starting bot with offset %d, limit %d, timeout %d',
                $offset,
                $limit,
                $timeout,
            ));

            if ($this->status === BotStatus::started) {
                $this->updatesScope = Scope::inherit()->asNotSafely();
                $this->updatesScope->setExceptionHandler(
                    function (Scope $scope, Coroutine $task, \Throwable $exception): void {
                        if (!$exception instanceof AsyncCancellation) {
                            $this->reportUpdateError($exception);
                        }
                    },
                );

                foreach ($this->pullUpdates($offset, $limit, $timeout, $allowedUpdates) as $update) {
                    foreach ($this->bot->handleUpdate($update, $this->updatesScope) as $task) {
                        $taskId = $task->getId();
                        $this->tasks[$taskId] = $task;
                        $task->finally(function () use ($taskId): void {
                            unset($this->tasks[$taskId]);
                        });
                    }
                }
            }
        } catch (AsyncCancellation $exception) {
            if ($this->status !== BotStatus::stopping) {
                throw $exception;
            }
        } finally {
            try {
                protect(function (): void {
                    $this->drainUpdates();
                });
            } finally {
                $this->pollingCoroutine = null;
                $this->stopDeadlineMilliseconds = null;
                $this->status = BotStatus::stopped;
            }
        }
    }

    public function stop(float $timeout = 5.0): void
    {
        if (!is_finite($timeout) || $timeout <= 0 || $timeout > PHP_INT_MAX / 1000) {
            throw new \ValueError('The stop timeout must be greater than zero');
        }

        $deadline = $this->monotonicMilliseconds() + ($timeout * 1000);

        if ($this->status === BotStatus::stopping) {
            $this->stopTimeout = min($this->stopTimeout, $timeout);
            $this->stopDeadlineMilliseconds = min(
                $this->stopDeadlineMilliseconds ?? $deadline,
                $deadline,
            );

            return;
        }

        $this->stopTimeout = $timeout;
        $this->stopDeadlineMilliseconds = $deadline;
        $this->status = BotStatus::stopping;

        $this->bot->logger->info('Stopping bot');

        $currentCoroutine = current_coroutine();
        if (
            $this->pollingCoroutine !== null
            && $this->pollingCoroutine->getId() !== $currentCoroutine->getId()
            && !$this->pollingCoroutine->isCompleted()
        ) {
            $this->pollingCoroutine->cancel(new AsyncCancellation('Phenogram stop requested'));
        }
    }

    private function drainUpdates(): void
    {
        if ($this->updatesScope === null) {
            return;
        }

        $this->bot->logger->info(
            "Waiting for all requests to complete for a maximum of {$this->stopTimeout} seconds, then cancelling them."
        );

        $completed = false;
        while (($remainingMilliseconds = $this->remainingStopMilliseconds()) > 0) {
            try {
                $this->updatesScope->awaitCompletion(
                    timeout(min(10, $remainingMilliseconds)),
                );
                $completed = true;

                break;
            } catch (OperationCanceledException) {
            }
        }

        if (!$completed) {
            $this->updatesScope->cancel(new AsyncCancellation('Phenogram stop timeout'));

            try {
                $this->updatesScope->awaitAfterCancellation(
                    function (\Throwable $exception, Scope $scope): void {
                        if (!$exception instanceof AsyncCancellation) {
                            $this->reportUpdateError($exception);
                        }
                    },
                    timeout($this->secondsToMilliseconds($this->stopTimeout)),
                );
            } catch (OperationCanceledException $exception) {
                $this->bot->logger->error(
                    'Timed out while cancelling update handlers',
                    ['exception' => $exception],
                );
            }
        }

        $this->updatesScope->dispose();
        $this->updatesScope = null;
        $this->tasks = [];
    }

    /**
     * @param list<UpdateType>|null $allowedUpdates
     *
     * @return \Generator<UpdateInterface>
     */
    private function pullUpdates(
        int $offset,
        ?int $limit,
        int $timeout,
        ?array $allowedUpdates,
    ): \Generator {
        if ($allowedUpdates !== null) {
            $allowedUpdates = array_map(
                fn (UpdateType $type) => $type->value,
                $allowedUpdates
            );
        }

        while ($this->status === BotStatus::started) {
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
                    timeout: $timeout,
                    allowedUpdates: $allowedUpdates,
                );
            } catch (AsyncCancellation $exception) {
                throw $exception;
            } catch (\Throwable $e) {
                if ($this->status !== BotStatus::started) {
                    break;
                }

                $message = "Error while pooling updates: '{$e->getMessage()}'.";

                if ($this->poolingErrorTimeout !== 0.0) {
                    $message .= " Waiting for {$this->poolingErrorTimeout} seconds until next pull";
                }

                ($this->bot->errorHandler)(new UpdatePullingException(
                    message: $message,
                    previous: $e,
                ), $this->bot);

                if ($this->poolingErrorTimeout !== 0.0) {
                    delay($this->secondsToMilliseconds($this->poolingErrorTimeout));
                }

                continue;
            }

            if ($this->status !== BotStatus::started) {
                break;
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
    }

    private function reportUpdateError(\Throwable $exception): void
    {
        try {
            ($this->bot->errorHandler)(new PhenogramException(
                message: sprintf('Error while handling update: %s', $exception->getMessage()),
                previous: $exception,
            ), $this->bot);
        } catch (\Throwable $handlerException) {
            $this->bot->logger->error(
                'The bot error handler failed',
                ['exception' => $handlerException],
            );
        }
    }

    private function secondsToMilliseconds(float $seconds): int
    {
        return max(1, (int) ceil($seconds * 1000));
    }

    private function remainingStopMilliseconds(): int
    {
        $this->stopDeadlineMilliseconds ??= $this->monotonicMilliseconds() + ($this->stopTimeout * 1000);

        return max(
            0,
            (int) ceil($this->stopDeadlineMilliseconds - $this->monotonicMilliseconds()),
        );
    }

    private function monotonicMilliseconds(): float
    {
        return hrtime(true) / 1_000_000;
    }
}
