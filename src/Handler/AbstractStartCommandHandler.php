<?php

namespace Phenogram\Framework\Handler;

use Phenogram\Bindings\Types\Update;

abstract class AbstractStartCommandHandler extends AbstractCommandHandler
{
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

        return count($commands) > 0 && str_starts_with($commands[0], '/start');
    }

    /**
     * @see https://core.telegram.org/bots/features#deep-linking
     */
    protected static function extractArguments(Update $update): ?string
    {
        $text = $update->message->text;

        assert($text !== null);

        return explode(' ', $text, 2)[1] ?? null;
    }
}
