<?php

declare(strict_types=1);

namespace Phenogram\Framework\Router;

use Phenogram\Framework\Interface\RouteInterface;
use Phenogram\Framework\Middleware\MiddlewareInterface;

/**
 * RouteGroup provides the ability to configure multiple routes to controller/actions using same presets.
 *
 * @psalm-type MiddlewareType = MiddlewareInterface|class-string<MiddlewareInterface>
 */
final class RouteGroup
{
    private string $namePrefix = '';

    /** @var array<non-empty-string, RouteInterface> */
    private array $routes = [];

    /** @var array<MiddlewareType> */
    private array $middlewares = [];

    /**
     * Check if group has a route with given name.
     */
    public function hasRoute(string $name): bool
    {
        return \array_key_exists($name, $this->routes);
    }

    /**
     * Route name prefix added to all routes.
     */
    public function setNamePrefix(string $prefix): self
    {
        $this->namePrefix = $prefix;

        return $this;
    }

    /**
     * @param MiddlewareType $middleware
     */
    public function addMiddleware(MiddlewareInterface|string $middleware): self
    {
        $this->middlewares[] = $middleware;

        return $this;
    }

    /**
     * Push routes to router.
     *
     * @internal
     */
    public function register(Router $router): void
    {
        foreach ($this->routes as $name => $route) {
            if (method_exists($route, 'withMiddleware')) {
                $route = $route->withMiddleware(...$this->middlewares);
            }

            $router->setRoute($name, $route);
        }
    }

    /**
     * Add a route to a route group.
     *
     * @param non-empty-string $name
     */
    public function addRoute(string $name, RouteInterface $route): self
    {
        $this->routes[$this->namePrefix . $name] = $route;

        return $this;
    }
}
