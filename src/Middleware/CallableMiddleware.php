<?php

declare(strict_types=1);

namespace Shanginn\TelegramBotApiFramework\Middleware;

use Shanginn\TelegramBotApiBindings\Types\Update;
use Shanginn\TelegramBotApiFramework\Handler\UpdateHandlerInterface;
use Shanginn\TelegramBotApiFramework\TelegramBot;

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
