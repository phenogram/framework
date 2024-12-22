<?php

declare(strict_types=1);

namespace Phenogram\Framework\Router;

use Phenogram\Bindings\Types\Interfaces\UpdateInterface;
use Phenogram\Framework\Handler\UpdateHandlerInterface;
use Phenogram\Framework\Interface\RouteInterface;

class Route implements RouteInterface
{
    use PipelineTrait;

    public function __construct(
        private readonly UpdateHandlerInterface $handler,
        private readonly ?\Closure $condition = null,
    ) {
        $this->pipeline = new Pipeline();
    }

    public function supports(UpdateInterface $update): bool
    {
        if ($this->condition === null) {
            return true;
        }

        return ($this->condition)($update);
    }

    public function getHandler(): UpdateHandlerInterface
    {
        return $this->pipeline->withHandler($this->handler);
    }
}
