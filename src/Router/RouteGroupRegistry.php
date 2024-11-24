<?php

declare(strict_types=1);

namespace Phenogram\Framework\Router;

/**
 * Manages the presets for various route groups.
 *
 * @implements \IteratorAggregate<non-empty-string, RouteGroup>
 */
final class RouteGroupRegistry implements \IteratorAggregate
{
    /** @var non-empty-string */
    private string $defaultGroup = 'default';

    /** @var array<non-empty-string, RouteGroup> */
    private array $groups = [];

    /**
     * @param non-empty-string $name
     */
    public function getGroup(string $name): RouteGroup
    {
        if (!isset($this->groups[$name])) {
            $this->groups[$name] = new RouteGroup();
        }

        return $this->groups[$name];
    }

    /**
     * @param non-empty-string $group
     */
    public function setDefaultGroupName(string $group): self
    {
        $this->defaultGroup = $group;

        return $this;
    }

    /**
     * @return non-empty-string
     */
    public function getDefaultGroupName(): string
    {
        return $this->defaultGroup;
    }

    public function getDefaultGroup(): RouteGroup
    {
        return $this->getGroup($this->defaultGroup);
    }

    /**
     * Push routes from each group to the router.
     *
     * @internal
     */
    public function registerRoutes(Router $router): void
    {
        foreach ($this->groups as $group) {
            $group->register($router);
        }
    }

    /**
     * @return \ArrayIterator<non-empty-string, RouteGroup>
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->groups);
    }
}
