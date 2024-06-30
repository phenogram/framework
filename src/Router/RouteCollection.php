<?php

declare(strict_types=1);

namespace Shanginn\TelegramBotApiFramework\Router;

use Shanginn\TelegramBotApiFramework\Interface\RouteInterface;

/**
 * @implements \IteratorAggregate<RouteInterface>
 */
class RouteCollection implements \IteratorAggregate, \Countable
{
    /** @var list<RouteInterface> */
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
     * @return \ArrayIterator<RouteInterface>
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

    public function add(RouteInterface $route): void
    {
        $this->routes[] = $route;
    }

    /**
     * Returns all routes in this collection.
     *
     * @return array<RouteInterface>
     */
    public function all(): array
    {
        return $this->routes;
    }

    public function addCollection(self $collection): void
    {
        foreach ($collection->all() as $route) {
            $this->routes[] = $route;
        }
    }
}
