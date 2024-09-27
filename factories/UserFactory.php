<?php

declare(strict_types=1);

namespace Phenogram\Framework\Factories;

use Phenogram\Bindings\Types\User;

class UserFactory extends AbstractFactory
{
    public static function make(
        int $id = null,
        bool $isBot = null,
        string $firstName = null,
        string $lastName = null,
        string $username = null,
        string $languageCode = null,
        bool $isPremium = null,
        bool $addedToAttachmentMenu = null,
        bool $canJoinGroups = null,
        bool $canReadAllGroupMessages = null,
        bool $supportsInlineQueries = null,
        bool $canConnectToBusiness = null,
        bool $hasMainWebApp = null,
    ): User {
        return new User(
            id: $id ?? self::fake()->randomNumber(),
            isBot: $isBot ?? self::fake()->boolean(),
            firstName: $firstName ?? self::fake()->firstName(),
            lastName: $lastName ?? self::fake()->boolean() ? null : self::fake()->lastName(),
            username: $username ?? self::fake()->boolean() ? null : self::fake()->userName(),
            languageCode: $languageCode ?? self::fake()->boolean() ? null : self::fake()->languageCode(),
            isPremium: $isPremium ?? self::fake()->boolean() ? null : self::fake()->boolean(),
            addedToAttachmentMenu: $addedToAttachmentMenu ?? self::fake()->boolean() ? null : self::fake()->boolean(),
            canJoinGroups: $canJoinGroups ?? self::fake()->boolean() ? null : self::fake()->boolean(),
            canReadAllGroupMessages: $canReadAllGroupMessages ?? self::fake()->boolean() ? null : self::fake()->boolean(),
            supportsInlineQueries: $supportsInlineQueries ?? self::fake()->boolean() ? null : self::fake()->boolean(),
            canConnectToBusiness: $canConnectToBusiness ?? self::fake()->boolean() ? null : self::fake()->boolean(),
            hasMainWebApp: $hasMainWebApp ?? self::fake()->boolean() ? null : self::fake()->boolean(),
        );
    }
}
