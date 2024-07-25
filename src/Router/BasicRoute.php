<?php

declare(strict_types=1);

namespace Shanginn\TelegramBotApiFramework\Router;

use Shanginn\TelegramBotApiBindings\Types\Update;
use Shanginn\TelegramBotApiFramework\Handler\UpdateHandlerInterface;
use Shanginn\TelegramBotApiFramework\Interface\RouteInterface;

final class BasicRoute implements RouteInterface
{
    use PipelineTrait;

    /** @var callable|null */
    private $condition;

    public function __construct(
        private UpdateHandlerInterface $handler,
        private array $middlewares = [],
        callable $condition = null,
    ) {
        $this->pipeline = new Pipeline();;

        foreach ($middlewares as $middleware) {
            $this->pipeline->pushMiddleware($middleware);
        }

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
