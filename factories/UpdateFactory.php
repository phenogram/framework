<?php

declare(strict_types=1);

namespace Phenogram\Framework\Factories;

use Phenogram\Bindings\Types\Message;
use Phenogram\Bindings\Types\Update;

class UpdateFactory extends AbstractFactory
{
    public static function make(
        int $updateId = null,
        Message $message = null,
    ): Update {
        return new Update(
            updateId: $updateId ?? self::fake()->randomNumber(5),
            message: $message ?? MessageFactory::make(),
        );
    }
}
