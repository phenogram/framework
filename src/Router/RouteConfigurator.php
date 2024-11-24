<?php

declare(strict_types=1);

namespace Phenogram\Framework\Router;

use Phenogram\Framework\Exception\RouteException;
use Phenogram\Framework\Handler\UpdateHandlerInterface;
use Phenogram\Framework\Middleware\MiddlewareInterface;
use Phenogram\Framework\Trait\ContainerTrait;

final class RouteConfigurator
{
    use ContainerTrait;

    private ?string $group = null;

    /** @var MiddlewareInterface[]|string[]|callable[]|null */
    private ?array $middleware = null;

    /**
     * @var callable the condition to match the route
     */
    private $condition;

    /** @var string|callable|UpdateHandlerInterface|null */
    private mixed $target = null;

    public function __construct(
        private readonly RouteCollection $collection,
        private readonly ?string $name = null,
    ) {
    }

    public function __destruct()
    {
        $name = $this->name;
        if ($this->target === null) {
            throw new RouteException(sprintf('Route [%s] has no defined target. Call one of: `callable`, `handler` methods.', $name ?? 'unnamed'));
        }

        $this->collection->add($name ?? $this->generateName(), $this);
    }

    private function generateName(): string
    {
        if (\is_string($this->target)) {
            return $this->target;
        } elseif ($this->target instanceof UpdateHandlerInterface) {
            return $this->target::class;
        } elseif (\is_callable($this->target)) {
            throw new \LogicException('Callable handlers must have a name.');
        }

        throw new \LogicException('Unable to generate route name.');
    }

    /**
     * @internal
     *
     * Don't use this method. For internal use only.
     */
    public function __get(string $name): mixed
    {
        return match ($name) {
            'target' => $this->target,
            'group' => $this->group,
            'middleware' => $this->middleware,
            'condition' => $this->condition,
            default => throw new \BadMethodCallException(\sprintf('Unable to access %s.', $name))
        };
    }

    public function callable(array|\Closure $callable): self
    {
        $this->target = $callable;

        return $this;
    }

    public function handler(UpdateHandlerInterface|\Closure|string $target): self
    {
        $this->target = $target;

        return $this;
    }

    public function group(string $group): self
    {
        $this->group = $group;

        return $this;
    }

    public function supports(callable $condition): self
    {
        $this->condition = $condition;

        return $this;
    }

    public function middleware(MiddlewareInterface|string|callable ...$middleware): self
    {
        if (!\is_array($middleware)) {
            $middleware = [$middleware];
        }

        $this->middleware = $middleware;

        return $this;
    }
}
