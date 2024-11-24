<?php

declare(strict_types=1);

namespace Phenogram\Framework\Router;

use Phenogram\Framework\Interface\RouteInterface;
use Phenogram\Framework\Middleware\MiddlewareInterface;

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
     * @param MiddlewareType ...$middleware
     */
    public function withMiddleware(MiddlewareInterface|string ...$middleware): RouteInterface
    {
        $route = clone $this;

        // array fallback
        if (\count($middleware) === 1 && \is_array($middleware[0])) {
            $middleware = $middleware[0];
        }

        /** @var MiddlewareType[] $middleware */
        foreach ($middleware as $item) {
            $route->pipeline->pushMiddleware($item);
        }

        return $route;
    }

    public function withPipeline(Pipeline $pipeline): static
    {
        $route = clone $this;

        $route->pipeline = $pipeline;

        return $route;
    }
}
