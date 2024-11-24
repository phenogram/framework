<?php

declare(strict_types=1);

namespace Phenogram\Framework\Router;

use Phenogram\Bindings\Types\Update;
use Phenogram\Framework\Exception\RouteException;
use Phenogram\Framework\Handler\CallableHandler;
use Phenogram\Framework\Handler\UpdateHandlerInterface;
use Phenogram\Framework\Interface\RouteInterface;
use Phenogram\Framework\Middleware\CallableMiddleware;
use Phenogram\Framework\Middleware\MiddlewareInterface;
use Phenogram\Framework\Trait\ContainerTrait;
use Psr\Container\ContainerExceptionInterface;

final class Router
{
    use ContainerTrait;

    /**
     * @var array<RouteInterface>
     */
    private array $routes = [];

    public function __construct(
        public readonly RouteGroupRegistry $groups
    ) {
    }

    /**
     * @return \Generator<UpdateHandlerInterface>
     */
    public function supportedHandlers(Update $update): \Generator
    {
        foreach ($this->routes as $route) {
            if ($route->supports($update)) {
                yield $route->getHandler();
            }
        }
    }

    public function setRoute(string $name, RouteInterface $route): self
    {
        $this->routes[$name] = $route;

        return $this;
    }

    public function import(
        RoutingConfigurator $routes,
    ): void {
        foreach ($routes->collection as $name => $configurator) {
            try {
                $route = $this->configureRoute($configurator);
            } catch (RouteException $e) {
                throw new RouteException(\sprintf('Unable to configure route `%s`: %s', $name, $e->getMessage()), $e->getCode(), $e);
            }

            if (!isset($this->routes[$name])) {
                $group = $this->groups->getGroup(
                    $configurator->group ?? $this->groups->getDefaultGroupName()
                );
                $group->addRoute($name, $route);
            }
        }
    }

    public function boot(): void
    {
        $this->groups->registerRoutes($this);
    }

    private function configureRoute(RouteConfigurator $configurator): RouteInterface
    {
        if ($configurator->target === null) {
            throw new RouteException('This route has no defined target. Call one of: `callable`, `handler` methods.');
        }

        $handler = $this->getHandler($configurator->target);

        $route = new Route(
            handler: $handler,
            condition: $configurator->condition
        );

        if ($configurator->middleware !== null) {
            $middlewares = array_map(
                fn (string|MiddlewareInterface $middleware) => $this->getMiddleware($middleware),
                $configurator->middleware
            );

            $route = $route->withMiddleware(...$middlewares);
        }

        return $route;
    }

    private function getMiddleware(string|callable|MiddlewareInterface $middleware): MiddlewareInterface
    {
        if ($middleware instanceof MiddlewareInterface) {
            return $middleware;
        }

        if (\is_callable($middleware)) {
            return new CallableMiddleware($middleware);
        }

        if (\is_string($middleware)) {
            if (!$this->hasContainer()) {
                throw new RouteException('Unable to configure route pipeline without associated container');
            }

            try {
                return $this->container->get($middleware);
            } catch (ContainerExceptionInterface $e) {
                throw new RouteException('Invalid middleware resolution', $e->getCode(), $e);
            }
        }

        $name = get_debug_type($middleware);
        throw new RouteException(\sprintf('Invalid middleware `%s`', $name));
    }

    private function getHandler(string|callable|UpdateHandlerInterface $target): UpdateHandlerInterface
    {
        if ($target instanceof UpdateHandlerInterface) {
            return $target;
        }

        if (\is_callable($target)) {
            return new CallableHandler($target);
        }

        if (!$this->hasContainer()) {
            throw new RouteException('Unable to configure route pipeline without associated container');
        }

        try {
            return $this->container->get($target);
        } catch (ContainerExceptionInterface $e) {
            throw new RouteException('Invalid target resolution', $e->getCode(), $e);
        }
    }
}
