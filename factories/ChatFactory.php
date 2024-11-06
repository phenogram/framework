<?php

declare(strict_types=1);

namespace Phenogram\Framework\Factories;

use Phenogram\Bindings\Types\Chat;

class ChatFactory extends AbstractFactory
{
    public static function make(
        int $id = null,
        string $type = null,
        string $title = null,
        string $username = null,
        string $firstName = null,
        string $lastName = null,
        bool $isForum = null,
    ): Chat {
        return new Chat(
            id: $id ?? self::fake()->randomNumber(),
            type: $type ?? self::fake()->randomElement(['private', 'group', 'supergroup', 'channel']),
            title: $title ?? self::fake()->sentence(),
            username: $username ?? self::fake()->userName(),
            firstName: $firstName ?? self::fake()->firstName(),
            lastName: $lastName ?? self::fake()->lastName(),
            isForum: $isForum ?? self::fake()->boolean(),
        );
    }
}
