<?php

namespace Phenogram\Framework\Handler;

use Phenogram\Bindings\Types\Interfaces\UpdateInterface;

abstract class AbstractStartCommandHandler extends AbstractCommandHandler
{
    public static function supports(UpdateInterface $update): bool
    {
        $message = $update->message;

        if ($message === null || $message->entities === null || $message->text === null) {
            return false;
        }

        $commands = static::extractCommands($message);

        return count($commands) > 0 && str_starts_with($commands[0], '/start');
    }

    /**
     * @see https://core.telegram.org/bots/features#deep-linking
     */
    protected static function extractArguments(UpdateInterface $update): ?string
    {
        $text = $update->message->text;

        assert($text !== null);

        return explode(' ', $text, 2)[1] ?? null;
    }
}
