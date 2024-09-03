<?php

declare(strict_types=1);

namespace Phenogram\Framework\Command;

#[\Attribute(\Attribute::TARGET_METHOD)]
final readonly class AsTelegramBotCommand
{
    public function __construct(
        public string $command,
        public string $description,
    ) {
    }
}
