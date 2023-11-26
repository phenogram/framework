<?php

namespace Shanginn\TelegramBotApiFramework\Handler;

use Shanginn\TelegramBotApiBindings\Types\Update;

abstract class AbstractStartCommandHandler extends AbstractCommandHandler
{
    private string $command = '/start';

    /**
     * @var bool Handle /start if it is the only command in the message
     */
    protected bool $strict = true;

    public function supports(Update $update): bool
    {
        $message = $update->message;

        if ($message === null || $message->entities === null || $message->text === null) {
            return false;
        }

        $commands = $this->extractCommands(
            entities: $message->entities,
            text: $message->text
        );

        if ($this->strict) {
            return count($commands) === 1
                && str_starts_with($commands[0], $this->command);
        }

        foreach ($commands as $command) {
            if (str_starts_with($command, $this->command)) {
                return true;
            }
        }

        return false;
    }
}
