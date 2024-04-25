<?php

declare(strict_types=1);

namespace Shanginn\TelegramBotApiFramework\Interface;

use Shanginn\TelegramBotApiBindings\Types\Update;
use Shanginn\TelegramBotApiFramework\Handler\UpdateHandlerInterface;

interface RouteInterface extends UpdateHandlerInterface
{
    public function supports(Update $update): bool;
}