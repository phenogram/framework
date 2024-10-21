<?php

namespace Phenogram\Framework\Handler;

use Phenogram\Bindings\Types\Message;
use Phenogram\Bindings\Types\MessageEntity;
use Phenogram\Bindings\Types\Update;

abstract class AbstractCommandHandler implements UpdateHandlerInterface
{
    /**
     * @return array<string>
     */
    public static function extractCommands(Message $message): array
    {
        $entities = $message->entities;
        $text = $message->text;

        if ($entities === null || $text === null) {
            return [];
        }

        $commands = [];

        $botCommands = array_filter(
            $entities,
            fn (MessageEntity $entity) => $entity->type === 'bot_command'
        );

        $bytes = unpack('C*', $text);

        foreach ($botCommands as $entity) {
            $byteOffset = self::utf16OffsetToByteOffset($text, $entity->offset);

            $commandBytes = array_slice($bytes, $byteOffset, $entity->length);
            $commandStr = pack('C*', ...$commandBytes);
            $commands[] = $commandStr;
        }

        return $commands;
    }

    private static function utf16OffsetToByteOffset(string $text, int $utf16Offset): int
    {
        $byteOffset = 0;
        $utf16Counter = 0;

        for ($i = 0; $i < mb_strlen($text, 'UTF-8'); ++$i) {
            $char = mb_substr($text, $i, 1, 'UTF-8');
            $charCode = mb_ord($char, 'UTF-8');

            if ($charCode >= 0x10000) {
                $utf16Counter += 2;
            } else {
                ++$utf16Counter;
            }

            if ($utf16Counter > $utf16Offset) {
                break;
            }

            $byteOffset += strlen($char);
        }

        return $byteOffset;
    }

    public static function hasCommand(Update $update, string $command): bool
    {
        return $update->message !== null
            && in_array(
                $command,
                self::extractCommands($update->message),
                strict: true
            );
    }
}
