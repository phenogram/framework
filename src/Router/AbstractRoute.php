<?php

declare(strict_types=1);

namespace Shanginn\TelegramBotApiFramework\Router;

use Shanginn\TelegramBotApiBindings\Types\Update;
use Shanginn\TelegramBotApiFramework\Handler\UpdateHandlerInterface;
use Shanginn\TelegramBotApiFramework\Interface\RouteInterface;

abstract class AbstractRoute implements RouteInterface
{
    use PipelineTrait;

    public function __construct(
        private readonly UpdateHandlerInterface $handler,
    ) {
    }

    public function getHandler(): UpdateHandlerInterface
    {
        return $this->handler;
    }

    abstract public function supports(Update $update): bool;
}
