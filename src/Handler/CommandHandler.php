<?php

namespace Phenogram\Framework\Handler;

use Phenogram\Bindings\Types\Interfaces\UpdateInterface;
use Phenogram\Framework\Interface\RouteInterface;
use Phenogram\Framework\TelegramBot;

class CommandHandler extends AbstractCommandHandler implements RouteInterface
{
    protected UpdateHandlerInterface $handler;

    public function __construct(
        protected readonly string $command,
        callable $callback,
        protected readonly ?string $description = null,
        protected readonly ?string $usage = null,
    ) {
        $this->handler = new CallableHandler($callback);
    }

    public function supports(UpdateInterface $update): bool
    {
        return self::hasCommand($update, $this->command);
    }

    // TODO: хммм... и getHandler() и handle(). Нужны оба?
    public function getHandler(): UpdateHandlerInterface
    {
        return $this->handler;
    }

    public function handle(UpdateInterface $update, TelegramBot $bot)
    {
        $this->handler->handle($update, $bot);
    }
}
