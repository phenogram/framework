<?php

declare(strict_types=1);

namespace Phenogram\Framework\Middleware;

use Phenogram\Bindings\Types\Interfaces\UpdateInterface;
use Phenogram\Framework\Handler\UpdateHandlerInterface;
use Phenogram\Framework\TelegramBot;

class CallableMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly \Closure $callable,
    ) {
    }

    public function process(UpdateInterface $update, UpdateHandlerInterface $handler, TelegramBot $bot): void
    {
        ($this->callable)($update, $handler, $bot);
    }
}
