<?php

namespace Shanginn\TelegramBotApiFramework;

use Phenogram\Bindings\Api;
use Phenogram\Bindings\ClientInterface;
use Phenogram\Bindings\Serializer;
use Phenogram\Bindings\SerializerInterface;
use Phenogram\Bindings\Types\Update;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use PsrDiscovery\Discover;
use React\EventLoop\Loop;
use React\Promise\PromiseInterface;
use React\Promise\Timer;
use Shanginn\TelegramBotApiFramework\Handler\UpdateHandlerInterface;
use Shanginn\TelegramBotApiFramework\Interface\ContainerizedInterface;
use Shanginn\TelegramBotApiFramework\Router\RouteConfigurator;
use Shanginn\TelegramBotApiFramework\Router\Router;
use Shanginn\TelegramBotApiFramework\Trait\ContainerTrait;

use function React\Async\async;
use function React\Async\await;
use function React\Async\parallel;
use function React\Promise\all;

class TelegramBot implements ContainerizedInterface
{
    use ContainerTrait;

    public Api $api;

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

    private Router $router;

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
            fn () => Loop::stop(),
            function (\Throwable $e) {
                $this->logger->error(sprintf(
                    'Error while waiting for pending promises: %s',
                    $e->getMessage(),
                ), [
                    'error' => $e,
                ]);

                Loop::stop();
            }
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

        try {
            $updates = await($this->api->getUpdates(
                offset: $offset,
                limit: $limit,
                allowedUpdates: $allowedUpdates,
            ));
        } catch (\Throwable $e) {
            $waitTime = 5;

            $this->logger->error(sprintf(
                'Error while pooling updates %s. Waiting for %d seconds until next pull',
                $e->getMessage(),
                $waitTime,
            ), [
                'error' => $e,
            ]);

            try {
                await(Timer\sleep($waitTime));
            } catch (\Throwable $e) {
                $this->logger->error(sprintf(
                    'Error while waiting for next pull: %s',
                    $e->getMessage(),
                ), [
                    'error' => $e,
                ]);
            }

            return $this->pullUpdates($offset, $limit, $timeout, $allowedUpdates);
        }

        $this->logger->debug('Got updates', [
            'updates' => $updates,
        ]);

        $tasks = [];

        $offset = array_reduce(
            $updates,
            fn ($max, $update) => max($max, $update->updateId + 1),
            $offset
        );

        $pullUpdatesPromise = async(
            fn () => $this->pullUpdates($offset, $limit, $timeout, $allowedUpdates)
        )();

        /** @var Update $update */
        foreach ($updates as $update) {
            $tasks[] = $this->promises[$update->updateId] = async(
                fn () => $this
                    ->handleUpdate($update)
                    ->then(
                        null,
                        function (\Throwable $e) {
                            $this->logger->error(sprintf(
                                'Error while handling updates: %s.',
                                $e->getMessage(),
                            ), [
                                'error' => $e,
                            ]);
                        }
                    )
                    ->then(function () use ($update) {
                        unset($this->promises[$update->updateId]);
                    })
            );
        }

        return parallel($tasks)
            ->then(fn () => $pullUpdatesPromise);
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
     * @return PromiseInterface<array<mixed>>
     */
    public function handleUpdate(Update $update): PromiseInterface
    {
        $tasks = [];

        foreach ($this->router->supportedHandlers($update) as $handler) {
            $tasks[] = async(fn () => $handler->handle($update, $this));
        }

        return parallel($tasks);
    }
}
