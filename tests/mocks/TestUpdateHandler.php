<?php

declare(strict_types=1);

namespace Phenogram\Framework\Tests\mocks;

use Phenogram\Bindings\Types\Interfaces\UpdateInterface;
use Phenogram\Framework\Handler\UpdateHandlerInterface;
use Phenogram\Framework\TelegramBot;

use function Amp\delay;

class TestUpdateHandler implements UpdateHandlerInterface
{
    public array $processedUpdateIds = [];

    public function __construct(
        public float $processingDelay = 0.01,
    ) {
    }

    public static function support(UpdateInterface $update): bool
    {
        return $update->message?->from?->id !== null;
    }

    public function handle(UpdateInterface $update, TelegramBot $bot)
    {
        if ($this->processingDelay > 0) {
            delay($this->processingDelay);
        }

        $bot->api->sendMessage($update->message->from->id, 'Hello, ' . $update->message->from->firstName ?? 'name');

        $this->processedUpdateIds[] = $update->updateId;
    }
}
