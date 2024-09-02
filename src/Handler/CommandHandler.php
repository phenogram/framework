<?php

namespace Shanginn\TelegramBotApiFramework\Handler;

use Phenogram\Bindings\Types\Update;
use Shanginn\TelegramBotApiFramework\Interface\RouteInterface;

class CommandHandler extends AbstractCommandHandler implements RouteInterface
{
    use CommandCallbackHandlerTrait;

    public function __construct(
        protected readonly string $command,
        callable $callback,
        protected readonly ?string $description = null,
        protected readonly ?string $usage = null,
    ) {
        $this->callback = $callback;

        $this->validateCallback();
    }

    public function supports(Update $update): bool
    {
        return $update->message?->entities !== null
            && $update->message->text !== null
            && in_array(
                $this->command,
                self::extractCommands(
                    $update->message->entities,
                    $update->message->text
                )
            );
    }

    public function getHandler(): UpdateHandlerInterface
    {
        return $this;
    }
}
