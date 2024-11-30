<?php

declare(strict_types=1);

namespace Phenogram\Framework\Router;

use Phenogram\Framework\Middleware\MiddlewareInterface;

class RouteGroupConfigurator
{
    /**
     * @var array<RouteConfigurator>
     */
    private array $routes = [];

    /**
     * @var array<MiddlewareInterface|string|\Closure>
     */
    private array $middlewares = [];

    public function __construct(
        private Router $router,
    ) {
    }

    public function __destruct()
    {
        foreach ($this->routes as $configurator) {
            $configurator->middleware(...$this->middlewares);
        }
    }

    public function add(): RouteConfigurator
    {
        $configurator = new RouteConfigurator($this->router);

        $this->routes[] = $configurator;

        return $configurator;
    }

    public function middleware(MiddlewareInterface|string|\Closure ...$middleware): self
    {
        $this->middlewares = $middleware;

        return $this;
    }
}
