<?php

declare(strict_types=1);

namespace Phenogram\Framework\Factories;

use Phenogram\Bindings\Types\ChatMemberAdministrator;
use Phenogram\Bindings\Types\ChatMemberBanned;
use Phenogram\Bindings\Types\ChatMemberLeft;
use Phenogram\Bindings\Types\ChatMemberMember;
use Phenogram\Bindings\Types\ChatMemberOwner;
use Phenogram\Bindings\Types\ChatMemberRestricted;
use Phenogram\Bindings\Types\Interfaces\ChatMemberInterface;
use Phenogram\Bindings\Types\Interfaces\ChatMemberOwnerInterface;
use Phenogram\Bindings\Types\Interfaces\UserInterface;

class ChatMemberFactory extends AbstractFactory
{
    public static function make(
        ?string $status = null,
        ?UserInterface $user = null,
    ): ChatMemberInterface {
        $allowedStatuses = [
            'creator',
            'administrator',
            'member',
            'restricted',
            'left',
            'kicked',
        ];

        $status ??= $allowedStatuses[array_rand($allowedStatuses)];

        return match ($status) {
            'creator' => self::makeChatMemberOwner($user),
            'administrator' => self::makeChatMemberAdministrator($user),
            'member' => self::makeChatMemberMember($user),
            'restricted' => self::makeChatMemberRestricted($user),
            'left' => self::makeChatMemberLeft($user),
            'kicked' => self::makeChatMemberBanned($user),
            default => throw new \InvalidArgumentException(sprintf('Invalid status value: %s', $status)),
        };
    }

    public static function makeChatMemberOwner(
        ?UserInterface $user,
        ?bool $isAnonymous = null,
        ?string $customTitle = null,
    ): ChatMemberOwnerInterface {
        return new ChatMemberOwner(
            status: 'creator',
            user: $user ?? UserFactory::make(),
            isAnonymous: $isAnonymous ?? self::fake()->boolean(),
            customTitle: $customTitle,
        );
    }

    public static function makeChatMemberAdministrator(
        ?UserInterface $user = null,
        ?bool $isAnonymous = null,
        ?string $customTitle = null,
        ?bool $canBeEdited = null,
        ?bool $canManageChat = null,
        ?bool $canDeleteMessages = null,
        ?bool $canManageVideoChats = null,
        ?bool $canRestrictMembers = null,
        ?bool $canPromoteMembers = null,
        ?bool $canChangeInfo = null,
        ?bool $canInviteUsers = null,
        ?bool $canPostStories = null,
        ?bool $canEditStories = null,
        ?bool $canDeleteStories = null,
        ?bool $canPostMessages = null,
        ?bool $canEditMessages = null,
        ?bool $canPinMessages = null,
        ?bool $canManageTopics = null,
    ): ChatMemberAdministrator {
        return new ChatMemberAdministrator(
            status: 'administrator',
            user: $user ?? UserFactory::make(),
            isAnonymous: $isAnonymous ?? self::fake()->boolean(),
            canBeEdited: $canBeEdited ?? self::fake()->boolean(),
            canManageChat: $canManageChat ?? self::fake()->boolean(),
            canDeleteMessages: $canDeleteMessages ?? self::fake()->boolean(),
            canManageVideoChats: $canManageVideoChats ?? self::fake()->boolean(),
            canRestrictMembers: $canRestrictMembers ?? self::fake()->boolean(),
            canPromoteMembers: $canPromoteMembers ?? self::fake()->boolean(),
            canChangeInfo: $canChangeInfo ?? self::fake()->boolean(),
            canInviteUsers: $canInviteUsers ?? self::fake()->boolean(),
            canPostStories: $canPostStories ?? self::fake()->boolean(),
            canEditStories: $canEditStories ?? self::fake()->boolean(),
            canDeleteStories: $canDeleteStories ?? self::fake()->boolean(),
            canPostMessages: $canPostMessages,
            canEditMessages: $canEditMessages,
            canPinMessages: $canPinMessages,
            canManageTopics: $canManageTopics,
            customTitle: $customTitle,
        );
    }

    public static function makeChatMemberMember(
        ?UserInterface $user = null,
        ?int $untilDate = null,
    ): ChatMemberMember {
        return new ChatMemberMember(
            status: 'member',
            user: $user ?? UserFactory::make(),
            untilDate: $untilDate,
        );
    }

    public static function makeChatMemberRestricted(
        ?UserInterface $user = null,
        ?bool $isMember = null,
        ?int $untilDate = null,
        ?bool $canSendMessages = null,
        ?bool $canSendAudios = null,
        ?bool $canSendDocuments = null,
        ?bool $canSendPhotos = null,
        ?bool $canSendVideos = null,
        ?bool $canSendVideoNotes = null,
        ?bool $canSendVoiceNotes = null,
        ?bool $canSendPolls = null,
        ?bool $canSendOtherMessages = null,
        ?bool $canAddWebPagePreviews = null,
        ?bool $canChangeInfo = null,
        ?bool $canInviteUsers = null,
        ?bool $canPinMessages = null,
        ?bool $canManageTopics = null,
    ): ChatMemberRestricted {
        return new ChatMemberRestricted(
            status: 'restricted',
            user: $user ?? UserFactory::make(),
            isMember: $isMember ?? self::fake()->boolean(),
            untilDate: $untilDate ?? self::fake()->dateTime()->getTimestamp(),
            canSendMessages: $canSendMessages ?? self::fake()->boolean(),
            canSendAudios: $canSendAudios ?? self::fake()->boolean(),
            canSendDocuments: $canSendDocuments ?? self::fake()->boolean(),
            canSendPhotos: $canSendPhotos ?? self::fake()->boolean(),
            canSendVideos: $canSendVideos ?? self::fake()->boolean(),
            canSendVideoNotes: $canSendVideoNotes ?? self::fake()->boolean(),
            canSendVoiceNotes: $canSendVoiceNotes ?? self::fake()->boolean(),
            canSendPolls: $canSendPolls ?? self::fake()->boolean(),
            canSendOtherMessages: $canSendOtherMessages ?? self::fake()->boolean(),
            canAddWebPagePreviews: $canAddWebPagePreviews ?? self::fake()->boolean(),
            canChangeInfo: $canChangeInfo ?? self::fake()->boolean(),
            canInviteUsers: $canInviteUsers ?? self::fake()->boolean(),
            canPinMessages: $canPinMessages ?? self::fake()->boolean(),
            canManageTopics: $canManageTopics ?? self::fake()->boolean(),
        );
    }

    public static function makeChatMemberLeft(
        ?UserInterface $user = null,
    ): ChatMemberLeft {
        return new ChatMemberLeft(
            status: 'left',
            user: $user ?? UserFactory::make(),
        );
    }

    public static function makeChatMemberBanned(
        ?UserInterface $user = null,
        ?int $untilDate = null,
    ): ChatMemberBanned {
        return new ChatMemberBanned(
            status: 'kicked',
            user: $user ?? UserFactory::make(),
            untilDate: $untilDate ?? self::fake()->dateTime()->getTimestamp(),
        );
    }
}
