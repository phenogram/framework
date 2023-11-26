<?php

namespace Shanginn\TelegramBotApiFramework\Handler;

use Shanginn\TelegramBotApiBindings\Types\Update;

class CommandHandler extends AbstractCommandHandler
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
                $this->extractCommands(
                    $update->message->entities,
                    $update->message->text
                )
            );
    }
}
