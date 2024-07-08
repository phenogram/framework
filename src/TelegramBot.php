<?php

namespace Shanginn\TelegramBotApiFramework;

use Psr\Container\ContainerInterface;
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

    private Router $router;

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

        $this->router = new Router();
    }

    public function withContainer(ContainerInterface $container): self
    {
        $self = clone $this;
        $self->container = $container;

        $self->router = $this->router->withContainer($container);

        return $self;
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

                    $promises = [];

                    /** @var Update $update */
                    foreach ($updates as $update) {
                        $offset = max($offset, $update->updateId + 1);

                        $promises[] = $this->promises[$update->updateId] = async(
                            fn () => $this
                                ->handleUpdate($update)
                                ->then(function () use ($update) {
                                    unset($this->promises[$update->updateId]);
                                })
                        );
                    }

                    return parallel($promises);
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

                    return Timer\sleep($waitTime);
                }
            )
            ->then(
                null,
                function (\Throwable $e){
                    $this->logger->error(sprintf(
                        'Error while handling updates: %s.',
                        $e->getMessage(),
                    ), [
                        'error' => $e,
                    ]);
                }
            )
            ->then(
                // Can't use `fn() =>` syntax here, because we need to capture $offset by reference
                function () use (&$offset, $limit, $timeout, $allowedUpdates) {
                    return $this->pullUpdates($offset, $limit, $timeout, $allowedUpdates);
                }
            );
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

    /**
     * @return array<mixed>
     *
     * @throws \Throwable
     */
    public function handleUpdateSync(Update $update): array
    {
        return await($this->handleUpdate($update));
    }
}
