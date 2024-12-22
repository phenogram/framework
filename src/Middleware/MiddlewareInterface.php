<?php

declare(strict_types=1);

namespace Phenogram\Framework\Middleware;

use Phenogram\Bindings\Types\Interfaces\UpdateInterface;
use Phenogram\Framework\Handler\UpdateHandlerInterface;
use Phenogram\Framework\TelegramBot;

interface MiddlewareInterface
{
    public function process(UpdateInterface $update, UpdateHandlerInterface $handler, TelegramBot $bot): void;
}
