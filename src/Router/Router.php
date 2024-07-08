<?php

declare(strict_types=1);

namespace Shanginn\TelegramBotApiFramework\Router;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Shanginn\TelegramBotApiBindings\Types\Update;
use Shanginn\TelegramBotApiFramework\Exception\RouteException;
use Shanginn\TelegramBotApiFramework\Handler\CallableHandler;
use Shanginn\TelegramBotApiFramework\Handler\UpdateHandlerInterface;
use Shanginn\TelegramBotApiFramework\Interface\RouteInterface;
use Shanginn\TelegramBotApiFramework\Middleware\MiddlewareInterface;
use Shanginn\TelegramBotApiFramework\Trait\ContainerTrait;

final class Router
{
    use ContainerTrait;

    private RouteCollection $collection;

    public function __construct()
    {
        $this->collection = new RouteCollection();
    }

    /**
     * @return \Generator<UpdateHandlerInterface>
     */
    public function supportedHandlers(Update $update): \Generator
    {
        foreach ($this->collection->all() as $route) {
            if ($route->supports($update)) {
                yield $route->getHandler();
            }
        }
    }

    public function add(): RouteConfigurator
    {
        return new RouteConfigurator($this);
    }

    public function register(RouteInterface $route): self
    {
        $this->collection->add($route);

        return $this;
    }

    public function configureRoute(RouteConfigurator $routeConfig): void
    {
        if ($routeConfig->target === null) {
            throw new RouteException(\sprintf('This route has no defined target. Call one of: `callable`, `handler` methods.'));
        }

        $pipeline = $this
            ->createPipelineWithMiddleware($routeConfig->middleware ?? [])
            ->withHandler(
                $this->getHandler($routeConfig->target)
            );

        $this->register(
            new BasicRoute(
                handler: $pipeline,
                condition: $routeConfig->condition
            ),
        );
    }

    /**
     * @throws ContainerExceptionInterface
     */
    private function createPipelineWithMiddleware(array $middleware): Pipeline
    {
        if (\count($middleware) === 1 && $middleware[0] instanceof Pipeline) {
            return $middleware[0];
        }

        $pipeline = new Pipeline();

        foreach ($middleware as $item) {
            if ($item instanceof MiddlewareInterface) {
                $pipeline->pushMiddleware($item);
            } elseif (\is_string($item)) {
                $item = $this->container->get($item);
                \assert($item instanceof MiddlewareInterface);

                $pipeline->pushMiddleware($item);
            } else {
                $name = get_debug_type($item);
                throw new RouteException(\sprintf('Invalid middleware `%s`', $name));
            }
        }

        return $pipeline;
    }

    private function getHandler(string|callable|UpdateHandlerInterface $target): UpdateHandlerInterface
    {
        if ($target instanceof UpdateHandlerInterface) {
            return $target;
        }

        if (\is_callable($target)) {
            return new CallableHandler($target);
        }

        try {
            if (!$this->hasContainer()) {
                throw new RouteException('Unable to configure route pipeline without associated container');
            }

            return $this->container->get($target);
        } catch (ContainerExceptionInterface $e) {
            throw new RouteException('Invalid target resolution', $e->getCode(), $e);
        }
    }
}
