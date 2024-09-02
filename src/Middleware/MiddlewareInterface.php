<?php

declare(strict_types=1);

namespace Shanginn\TelegramBotApiFramework\Middleware;

use Phenogram\Bindings\Types\Update;
use Shanginn\TelegramBotApiFramework\Handler\UpdateHandlerInterface;
use Shanginn\TelegramBotApiFramework\TelegramBot;

interface MiddlewareInterface
{
    public function process(Update $update, UpdateHandlerInterface $handler, TelegramBot $bot): void;
}
