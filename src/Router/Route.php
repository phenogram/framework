<?php

declare(strict_types=1);

namespace Phenogram\Framework\Router;

use Phenogram\Bindings\Types\Update;
use Phenogram\Framework\Handler\UpdateHandlerInterface;
use Phenogram\Framework\Interface\RouteInterface;

final class Route implements RouteInterface
{
    use PipelineTrait;

    /** @var callable|null */
    private $condition;

    public function __construct(
        private UpdateHandlerInterface $handler,
        callable $condition = null,
    ) {
        $this->pipeline = new Pipeline();
        $this->condition = $condition;
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
