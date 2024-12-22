<?php

declare(strict_types=1);

namespace Phenogram\Framework\Factories;

use Phenogram\Bindings\Types\Chat;
use Phenogram\Bindings\Types\Interfaces\MessageInterface;
use Phenogram\Bindings\Types\Message;
use Phenogram\Bindings\Types\User;

class MessageFactory extends AbstractFactory
{
    public static function make(
        ?int $messageId = null,
        ?int $date = null,
        ?Chat $chat = null,
        ?string $text = null,
        ?array $entities = null,
        ?User $from = null,
    ): MessageInterface {
        return new Message(
            messageId: $messageId ?? self::fake()->randomNumber(),
            date: $date ?? self::fake()->unixTime(),
            chat: $chat ?? ChatFactory::make(),
            text: $text,
            entities: $entities,
            from: $from,
        );
    }
}
