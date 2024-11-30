<?php

declare(strict_types=1);

namespace Phenogram\Framework\Router;

use Phenogram\Bindings\Types\Update;
use Phenogram\Framework\Handler\UpdateHandlerInterface;
use Phenogram\Framework\Interface\RouteInterface;

class Route implements RouteInterface
{
    use PipelineTrait;

    public function __construct(
        private readonly UpdateHandlerInterface $handler,
        private readonly \Closure|null $condition = null,
    ) {
        $this->pipeline = new Pipeline();
    }

    public function supports(Update $update): bool
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
