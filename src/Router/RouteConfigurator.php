<?php

declare(strict_types=1);

namespace Shanginn\TelegramBotApiFramework\Router;

use Shanginn\TelegramBotApiFramework\Handler\UpdateHandlerInterface;
use Shanginn\TelegramBotApiFramework\Middleware\MiddlewareInterface;
use Shanginn\TelegramBotApiFramework\Trait\ContainerTrait;

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
        private readonly Router $router
    ) {
    }

    public function __destruct()
    {
        $this->router->configureRoute($this);
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

    public function middleware(MiddlewareInterface|string|callable|array $middleware): self
    {
        if (!\is_array($middleware)) {
            $middleware = [$middleware];
        }

        $this->middleware = $middleware;

        return $this;
    }
}
