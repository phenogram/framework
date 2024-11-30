<?php

declare(strict_types=1);

namespace Phenogram\Framework\Router;

use Closure;
use Phenogram\Framework\Exception\RouteException;
use Phenogram\Framework\Handler\UpdateHandlerInterface;
use Phenogram\Framework\Middleware\MiddlewareInterface;
use Phenogram\Framework\Trait\ContainerTrait;

final class RouteConfigurator
{
    use ContainerTrait;

    /** @var MiddlewareInterface[]|string[]|callable[]|null */
    private(set) ?array $middleware = null;

    /**
     * @var null|Closure the condition to match the route
     */
    private(set) ?Closure $condition = null;

    /** @var string|Closure|callable|UpdateHandlerInterface|null */
    private(set) mixed $handler = null;

    public function __construct(
        private readonly Router $router,
    ) {
    }

    public function __destruct()
    {
        if ($this->handler === null) {
            throw new RouteException(
                'Route has no defined handler. You need to call the handler method.'
            );
        }

        $name = $this->generateName();

        try {
            $route = $this->router->configureRoute($this);
        } catch (RouteException $e) {
            throw new RouteException(
                sprintf('Unable to configure route `%s`: %s', $name, $e->getMessage()),
                $e->getCode(),
                $e
            );
        }

        $this->router->registerRoute($route);
    }

    private function generateName(): string
    {
        $callableName = null;
        if (is_string($this->handler)) {
            return $this->handler;
        } elseif ($this->handler instanceof UpdateHandlerInterface) {
            return $this->handler::class;
        } elseif (is_callable($this->handler, callable_name: $callableName)) {
            return $callableName ?? 'unnamed callable';
        }

        return 'unnamed';
    }

    public function handler(UpdateHandlerInterface|Closure|string $handler): self
    {
        $this->handler = $handler;

        return $this;
    }

    public function supports(callable $condition): self
    {
        $this->condition = $condition;

        return $this;
    }

    public function middleware(MiddlewareInterface|string|Closure ...$middleware): self
    {
        if (!is_array($middleware)) {
            $middleware = [$middleware];
        }

        $this->middleware = $middleware;

        return $this;
    }
}
