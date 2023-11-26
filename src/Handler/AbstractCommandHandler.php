<?php

namespace Shanginn\TelegramBotApiFramework\Handler;

use Shanginn\TelegramBotApiBindings\Types\MessageEntity;

abstract class AbstractCommandHandler implements UpdateHandlerInterface
{
    /**
     * @param array<MessageEntity> $entities
     *
     * @return array<string>
     */
    protected function extractCommands(array $entities, string $text): array
    {
        $commands = [];

        $botCommands = array_filter(
            $entities,
            fn (MessageEntity $entity) => $entity->type === 'bot_command'
        );

        $bytes = unpack('C*', $text);

        foreach ($botCommands as $entity) {
            $byteOffset = $this->utf16OffsetToByteOffset($text, $entity->offset);

            $commandBytes = array_slice($bytes, $byteOffset, $entity->length);
            $commandStr = pack('C*', ...$commandBytes);
            $commands[] = $commandStr;
        }

        return $commands;
    }

    protected function utf16OffsetToByteOffset(string $text, int $utf16Offset): int
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
}
