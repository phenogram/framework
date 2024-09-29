<?php

namespace Phenogram\Framework;

use Amp\Future;
use Phenogram\Bindings\Api;
use Phenogram\Bindings\ApiInterface;
use Phenogram\Bindings\Serializer;
use Phenogram\Bindings\Types\Update;
use Phenogram\Framework\Handler\UpdateHandlerInterface;
use Phenogram\Framework\Interface\ContainerizedInterface;
use Phenogram\Framework\Interface\RouteInterface;
use Phenogram\Framework\Router\RouteConfigurator;
use Phenogram\Framework\Router\Router;
use Phenogram\Framework\Trait\ContainerTrait;
use Phenogram\Framework\UpdatePuller\UpdatePuller;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use PsrDiscovery\Discover;

use function Amp\async;

class TelegramBot implements ContainerizedInterface
{
    use ContainerTrait;

    public readonly Api $api;

    /**
     * @var array<UpdateHandlerInterface>
     */
    private array $handlers = [];

    protected Router $router;

    public LoggerInterface $logger;

    public \Closure $errorHandler;

    private ?\Closure $stopPulling = null;

    public function __construct(
        protected readonly string $token,
        ApiInterface $api = null,
        LoggerInterface $logger = null,
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

    public function run(float $poolingErrorTimeout = 5.0): void
    {
        $updatePuller = new UpdatePuller($this, $poolingErrorTimeout);

        $this->stopPulling = $updatePuller->stop(...);

        $updatePuller->run();
    }

    /**
     * @throws \LogicException
     */
    public function stop(): void
    {
        if ($this->stopPulling === null) {
            throw new \LogicException('Pulling is not running');
        }

        ($this->stopPulling)();

        $this->stopPulling = null;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function addHandler(UpdateHandlerInterface|\Closure|string $handler): RouteConfigurator
    {
        return $this->router->add()->handler($handler);
    }

    public function addRoute(): RouteConfigurator
    {
        return $this->router->add();
    }

    public function registerRoute(RouteInterface $route): void
    {
        $this->router->register($route);
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
