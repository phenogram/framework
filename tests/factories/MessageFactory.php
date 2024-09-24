<?php

declare(strict_types=1);

namespace Phenogram\Framework\Tests\factories;

use Phenogram\Bindings\Types\Chat;
use Phenogram\Bindings\Types\Message;

class MessageFactory extends AbstractFactory
{
    public static function make(
        int $messageId = null,
        int $date = null,
        Chat $chat = null,
        string $text = null,
        array $entities = null,
    ): Message {
        return new Message(
            messageId: $messageId ?? self::fake()->randomNumber(),
            date: $date ?? self::fake()->unixTime(),
            chat: $chat ?? ChatFactory::make(),
            text: $text,
            entities: $entities,
        );
    }
}
