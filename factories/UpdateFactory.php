<?php

declare(strict_types=1);

namespace Phenogram\Framework\Factories;

use Phenogram\Bindings\Types\Interfaces\ChatMemberUpdatedInterface;
use Phenogram\Bindings\Types\Interfaces\MessageInterface;
use Phenogram\Bindings\Types\Interfaces\UpdateInterface;
use Phenogram\Bindings\Types\Update;

class UpdateFactory extends AbstractFactory
{
    public static function make(
        ?int $updateId = null,
        ?MessageInterface $message = null,
        ?ChatMemberUpdatedInterface $chatMember = null,
    ): UpdateInterface {
        return new Update(
            updateId: $updateId ?? self::fake()->randomNumber(5),
            message: $message,
            chatMember: $chatMember,
        );
    }
}
