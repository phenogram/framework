<?php

declare(strict_types=1);

namespace Phenogram\Framework\Router;

/**
 * @implements \IteratorAggregate<RouteConfigurator>
 */
class RouteCollection implements \IteratorAggregate, \Countable
{
    /** @var array<string, RouteConfigurator> */
    private array $routes = [];

    public function __clone()
    {
        foreach ($this->routes as $route) {
            $this->routes[] = clone $route;
        }
    }

    /**
     * Gets the current RouteCollection as an Iterator that includes all routes.
     *
     * @return \ArrayIterator<string,RouteConfigurator>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->all());
    }

    /**
     * Gets the number of Routes in this collection.
     */
    public function count(): int
    {
        return \count($this->routes);
    }

    public function add(string $name, RouteConfigurator $route): void
    {
        $this->routes[$name] = $route;
    }

    /**
     * Returns all routes in this collection.
     *
     * @return array<string, RouteConfigurator>
     */
    public function all(): array
    {
        return $this->routes;
    }

    public function addCollection(self $collection): void
    {
        foreach ($collection->all() as $name => $route) {
            $this->routes[$name] = $route;
        }
    }

    /**
     * Adds a specific group to a route.
     *
     * @param non-empty-string $group
     */
    public function group(string $group): void
    {
        foreach ($this->routes as $route) {
            $route->group($group);
        }
    }
}
