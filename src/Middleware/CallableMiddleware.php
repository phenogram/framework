<?php

declare(strict_types=1);

namespace Phenogram\Framework\Middleware;

use Phenogram\Bindings\Types\Update;
use Phenogram\Framework\Handler\UpdateHandlerInterface;
use Phenogram\Framework\TelegramBot;

class CallableMiddleware implements MiddlewareInterface
{
    /** @var callable */
    private $callable;

    public function __construct(
        callable $callable
    ) {
        $this->callable = $callable;
    }

    public function process(Update $update, UpdateHandlerInterface $handler, TelegramBot $bot): void
    {
        ($this->callable)($update, $handler, $bot);
    }
}
