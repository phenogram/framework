<?php

declare(strict_types=1);

namespace Phenogram\Framework\Middleware;

use Phenogram\Bindings\Types\Update;
use Phenogram\Framework\Handler\UpdateHandlerInterface;
use Phenogram\Framework\TelegramBot;

interface MiddlewareInterface
{
    public function process(Update $update, UpdateHandlerInterface $handler, TelegramBot $bot): void;
}
