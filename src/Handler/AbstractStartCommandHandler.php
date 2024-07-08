<?php

namespace Shanginn\TelegramBotApiFramework\Handler;

use Shanginn\TelegramBotApiBindings\Types\Update;

abstract class AbstractStartCommandHandler extends AbstractCommandHandler
{
    /**
     * @var bool Handle /start if it is the only command in the message
     */
    protected static bool $strict = true;

    public static function supports(Update $update): bool
    {
        $message = $update->message;

        if ($message === null || $message->entities === null || $message->text === null) {
            return false;
        }

        $commands = static::extractCommands(
            entities: $message->entities,
            text: $message->text
        );

        if (static::$strict) {
            return count($commands) === 1
                && str_starts_with($commands[0], '/start');
        }

        foreach ($commands as $command) {
            if (str_starts_with($command, '/start')) {
                return true;
            }
        }

        return false;
    }
}
