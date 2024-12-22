<?php

declare(strict_types=1);

namespace Phenogram\Framework\Factories;

use Phenogram\Bindings\Types\ChatMemberUpdated;
use Phenogram\Bindings\Types\Interfaces\ChatInterface;
use Phenogram\Bindings\Types\Interfaces\ChatMemberInterface;
use Phenogram\Bindings\Types\Interfaces\ChatMemberUpdatedInterface;
use Phenogram\Bindings\Types\Interfaces\UserInterface;

class ChatMemberUpdatedFactory extends AbstractFactory
{
    public static function make(
        ?ChatInterface $chat = null,
        ?UserInterface $from = null,
        ?int $date = null,
        ?ChatMemberInterface $oldChatMember = null,
        ?ChatMemberInterface $newChatMember = null,
        ?string $inviteLink = null,
        ?bool $viaJoinRequest = null,
        ?bool $viaChatFolderInviteLink = null,
    ): ChatMemberUpdatedInterface {
        return new ChatMemberUpdated(
            chat: $chat ?? ChatFactory::make(),
            from: $from ?? UserFactory::make(),
            date: $date ?? self::fake()->unixTime(),
            oldChatMember: $oldChatMember ?? ChatMemberFactory::make(),
            newChatMember: $newChatMember ?? ChatMemberFactory::make(),
            inviteLink: $inviteLink,
            viaJoinRequest: $viaJoinRequest,
            viaChatFolderInviteLink: $viaChatFolderInviteLink,
        );
    }
}
