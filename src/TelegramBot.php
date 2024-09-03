<?php

namespace Phenogram\Framework;

use Amp\Future;
use Amp\TimeoutCancellation;
use Phenogram\Bindings\Api;
use Phenogram\Bindings\ClientInterface;
use Phenogram\Bindings\Serializer;
use Phenogram\Bindings\SerializerInterface;
use Phenogram\Bindings\Types\Update;
use Phenogram\Framework\Handler\UpdateHandlerInterface;
use Phenogram\Framework\Interface\ContainerizedInterface;
use Phenogram\Framework\Router\RouteConfigurator;
use Phenogram\Framework\Router\Router;
use Phenogram\Framework\Trait\ContainerTrait;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use PsrDiscovery\Discover;

use function Amp\async;
use function Amp\delay;
use function Amp\Future\awaitAll;

class TelegramBot implements ContainerizedInterface
{
    use ContainerTrait;

    public Api $api;

    /**
     * @var array<UpdateHandlerInterface>
     */
    private array $handlers = [];

    private LoggerInterface $logger;

    private ?BotStatus $status = null;
    private ?Future $pullUpdatesFuture = null;

    private Router $router;

    /**
     * @var array<Future>
     */
    private array $tasks = [];

    public function __construct(
        protected readonly string $token,
        ClientInterface $botClient = null,
        SerializerInterface $serializer = null,
        LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? Discover::log() ?? new NullLogger();

        $this->api = new Api(
            client: $botClient ?? new TelegramBotApiClient($token),
            serializer: $serializer ?? new Serializer(),
        );

        $this->router = new Router();
    }

    public function withContainer(ContainerInterface $container): self
    {
        $self = clone $this;
        $self->container = $container;

        $self->router = $this->router->withContainer($container);

        return $self;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function run(
        int $offset = null,
        ?int $limit = 100,
        int $timeout = null,
        array $allowedUpdates = null,
    ): void {
        $offset = $offset ?? 1;
        $timeout = $timeout ?? 15;

        $this->status = BotStatus::starting;

        $this->logger->info(sprintf(
            'Starting bot with offset %d, limit %d, timeout %d',
            $offset,
            $limit,
            $timeout,
        ));

        foreach ($this->pullUpdates($offset, $limit, $timeout, $allowedUpdates) as $update) {
            $this->tasks[$update->updateId] = async(function () use ($update) {
                [$exceptions] = awaitAll($this->handleUpdate($update));

                if (!empty($exceptions)) {
                    $this->logger->error('Error while handling update', [
                        'update' => $update,
                        'exceptions' => $exceptions,
                    ]);
                }

                unset($this->tasks[$update->updateId]);
            });
        }
    }

    public function stop(float $timeout = null): void
    {
        $this->logger->info('Stopping bot');

        $this->status = BotStatus::stopping;

        if ($timeout !== null) {
            $this->logger->info(sprintf('Waiting for %d seconds before stopping', $timeout));
        }

        [$exceptions] = awaitAll($this->tasks, new TimeoutCancellation($timeout));

        if (count($exceptions) > 0) {
            $this->logger->error('Error while stopping bot', [
                'exceptions' => $exceptions,
            ]);
        }

        $this->status = BotStatus::stopped;
    }

    private function pullUpdates(
        int $offset,
        ?int $limit,
        int $timeout,
        ?array $allowedUpdates,
    ): \Generator {
        $this->status = BotStatus::started;

        while ($this->status !== BotStatus::stopping) {
            $this->logger->debug('Polling updates', [
                'offset' => $offset,
                'limit' => $limit,
                'allowedUpdates' => $allowedUpdates,
                'timeout' => $timeout,
            ]);

            try {
                $updates = $this->api->getUpdates(
                    offset: $offset,
                    limit: $limit,
                    allowedUpdates: $allowedUpdates,
                );
            } catch (\Throwable $e) {
                $waitTime = 5;

                $this->logger->error(sprintf(
                    'Error while pooling updates %s. Waiting for %d seconds until next pull',
                    $e->getMessage(),
                    $waitTime,
                ), [
                    'error' => $e,
                ]);

                delay($waitTime);

                continue;
            }

            $this->logger->debug('Got updates', [
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

    public function addHandler(UpdateHandlerInterface|\Closure|string $handler): RouteConfigurator
    {
        return $this->router->add()->handler($handler);
    }

    public function addRoute(): RouteConfigurator
    {
        return $this->router->add();
    }

    /**
     * @return array<Future>
     */
    public function handleUpdate(Update $update): array
    {
        $tasks = [];

        foreach ($this->router->supportedHandlers($update) as $handler) {
            $tasks[] = async(fn () => $handler->handle($update, $this));
        }

        return $tasks;
    }
}
