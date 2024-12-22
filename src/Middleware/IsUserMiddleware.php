<?php

declare(strict_types=1);

namespace Phenogram\Framework\Middleware;

use Phenogram\Bindings\Types\Interfaces\UpdateInterface;
use Phenogram\Framework\Handler\UpdateHandlerInterface;
use Phenogram\Framework\TelegramBot;

readonly class IsUserMiddleware implements MiddlewareInterface
{
    public function __construct(
        private int $userId,
    ) {
    }

    public function process(UpdateInterface $update, UpdateHandlerInterface $handler, TelegramBot $bot): void
    {
        if ($update->message->from->id === $this->userId) {
            $handler->handle($update, $bot);
        }
    }
}
