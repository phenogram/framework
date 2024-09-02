<?php

declare(strict_types=1);

namespace Shanginn\TelegramBotApiFramework\Interface;

use Phenogram\Bindings\Types\Update;
use Shanginn\TelegramBotApiFramework\Handler\UpdateHandlerInterface;

interface RouteInterface
{
    public function supports(Update $update): bool;

    public function getHandler(): UpdateHandlerInterface;
}
