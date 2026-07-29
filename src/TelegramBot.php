<?php

namespace Phenogram\Framework;

use Async\AsyncCancellation;
use Async\Coroutine;
use Async\ScopeProvider;
use Phenogram\Bindings\Api;
use Phenogram\Bindings\ApiInterface;
use Phenogram\Bindings\Serializer;
use Phenogram\Bindings\Types\Interfaces\UpdateInterface;
use Phenogram\Framework\Handler\UpdateHandlerInterface;
use Phenogram\Framework\Interface\ContainerizedInterface;
use Phenogram\Framework\Router\RouteConfigurator;
use Phenogram\Framework\Router\Router;
use Phenogram\Framework\Trait\ContainerTrait;
use Phenogram\Framework\UpdatePuller\UpdatePuller;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use PsrDiscovery\Discover;

use function Async\await;
use function Async\protect;
use function Async\spawn;
use function Async\spawn_with;

class TelegramBot implements ContainerizedInterface
{
    use ContainerTrait;

    public readonly ApiInterface $api;

    protected Router $router;

    public LoggerInterface $logger;

    public \Closure $errorHandler;

    private ?\Closure $stopPulling = null;

    public function __construct(
        protected readonly string $token,
        ?ApiInterface $api = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->api = $api ?? new Api(
            client: new TelegramBotApiClient($token),
            serializer: new Serializer(),
        );

        $this->router = new Router();

        $this->logger = $logger ?? Discover::log() ?? new EchoLogger();
        $this->errorHandler = fn (\Throwable $e, self $bot) => $bot->logger->error($e->getMessage());
    }

    public function withContainer(ContainerInterface $container): self
    {
        $self = clone $this;

        $self->container = $container;
        $self->router = $this->router->withContainer($container);

        return $self;
    }

    public function run(
        ?int $offset = null,
        ?int $limit = 100,
        ?int $timeout = null,
        ?array $allowedUpdates = null,
        float $poolingErrorTimeout = 5.0,
    ): void {
        $updatePuller = new UpdatePuller($this, $poolingErrorTimeout);

        $this->stopPulling = $updatePuller->stop(...);
        $pulling = spawn(fn () => $updatePuller->run(
            offset: $offset,
            limit: $limit,
            timeout: $timeout,
            allowedUpdates: $allowedUpdates,
        ));

        try {
            await($pulling);
        } finally {
            if (!$pulling->isCompleted()) {
                protect(static function () use ($pulling): void {
                    $pulling->cancel(new AsyncCancellation('Phenogram bot run cancelled'));

                    try {
                        await($pulling);
                    } catch (AsyncCancellation) {
                    }
                });
            }

            $this->stopPulling = null;
        }
    }

    /**
     * @throws \LogicException
     */
    public function stop(float $timeout = 5.0): void
    {
        if ($this->stopPulling === null) {
            throw new \LogicException('Pulling is not running');
        }

        ($this->stopPulling)($timeout);
    }

    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * @return list<Coroutine>
     */
    public function handleUpdate(UpdateInterface $update, ?ScopeProvider $scope = null): array
    {
        $tasks = [];

        foreach ($this->router->supportedHandlers($update) as $handler) {
            $task = fn () => $handler->handle($update, $this);
            $tasks[] = $scope === null
                ? spawn($task)
                : spawn_with($scope, $task);
        }

        return $tasks;
    }

    /**
     * Shortcut for adding a handler.
     * The route registries on the __destruct method,
     * so make sure you not save the instance of the RouteConfigurator in a variable.
     */
    public function addHandler(UpdateHandlerInterface|\Closure|string $handler): RouteConfigurator
    {
        return $this->router->add()->handler($handler);
    }

    /**
     * @param \Closure<Router> $callback
     */
    public function defineHandlers(\Closure $callback): void
    {
        $callback($this->router);
    }
}
