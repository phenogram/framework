<?php

declare(strict_types=1);

namespace Shanginn\TelegramBotApiFramework\Middleware;

use Phenogram\Bindings\Types\Update;
use Shanginn\TelegramBotApiFramework\Handler\UpdateHandlerInterface;
use Shanginn\TelegramBotApiFramework\TelegramBot;

readonly class IsUserMiddleware implements MiddlewareInterface
{
    public function __construct(
        private int $userId
    ) {
    }

    public function process(Update $update, UpdateHandlerInterface $handler, TelegramBot $bot): void
    {
        if ($update->message->from->id === $this->userId) {
            $handler->handle($update, $bot);
        }
    }
}
