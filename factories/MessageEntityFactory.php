<?php

declare(strict_types=1);

namespace Phenogram\Framework\Factories;

use Phenogram\Bindings\Types\MessageEntity;
use Phenogram\Bindings\Types\User;

class MessageEntityFactory extends AbstractFactory
{
    public static function make(
        string $type = null,
        int $offset = null,
        int $length = null,
        string $url = null,
        User $user = null,
        string $language = null,
        string $customEmojiId = null,
    ): MessageEntity {
        return new MessageEntity(
            type: $type ?? self::fake()->randomElement(['mention', 'hashtag', 'cashtag', 'bot_command', 'url', 'email', 'phone_number', 'bold', 'italic', 'underline', 'strikethrough', 'code', 'pre', 'text_link', 'text_mention', 'custom_emoji']),
            offset: $offset ?? self::fake()->randomNumber(),
            length: $length ?? self::fake()->randomNumber(),
            url: $url,
            user: $user,
            language: $language,
            customEmojiId: $customEmojiId,
        );
    }
}
