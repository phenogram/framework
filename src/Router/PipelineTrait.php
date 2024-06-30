<?php

declare(strict_types=1);

namespace Shanginn\TelegramBotApiFramework\Router;

use Shanginn\TelegramBotApiFramework\Interface\RouteInterface;
use Shanginn\TelegramBotApiFramework\Middleware\MiddlewareInterface;

/**
 * @psalm-type MiddlewareType = MiddlewareInterface|class-string<MiddlewareInterface>|non-empty-string
 */
trait PipelineTrait
{
    protected ?Pipeline $pipeline = null;

    /** @psalm-var array<array-key, MiddlewareType> */
    protected array $middleware = [];

    /**
     * Associated middleware with route. New instance of route will be returned.
     *
     * Example:
     * $route->withMiddleware(new CacheMiddleware(100));
     * $route->withMiddleware(ProxyMiddleware::class);
     * $route->withMiddleware(ProxyMiddleware::class, OtherMiddleware::class);
     * $route->withMiddleware([ProxyMiddleware::class, OtherMiddleware::class]);
     *
     * @param MiddlewareType|array{0:MiddlewareType[]} ...$middleware
     *
     * @return RouteInterface|$this
     */
    public function withMiddleware(...$middleware): RouteInterface
    {
        $route = clone $this;

        // array fallback
        if (\count($middleware) === 1 && \is_array($middleware[0])) {
            $middleware = $middleware[0];
        }

        /** @var MiddlewareType[] $middleware */
        foreach ($middleware as $item) {
            $route->middleware[] = $item;
        }

        return $route;
    }

    public function withPipeline(Pipeline $pipeline): static
    {
        $route = clone $this;

        $route->middleware = [$pipeline];
        $route->pipeline = $pipeline;

        return $route;
    }
}
