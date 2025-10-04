<?php

declare(strict_types=1);

namespace Phenogram\Framework\Tests\mocks;

use Psr\Log\AbstractLogger;

class CapturingLogger extends AbstractLogger
{
    public array $messages = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        //        $this->messages[] = [
        //            'level' => $level,
        //            'message' => (string) $message,
        //            'context' => $context,
        //        ];

        //         dump(sprintf("[%s] %s %s\n", $level, $message, !empty($context) ? json_encode($context) : ''));
    }

    public function hasMessageContaining(string $level, string $substring): bool
    {
        foreach ($this->messages as $log) {
            if ($log['level'] === $level && str_contains($log['message'], $substring)) {
                return true;
            }
        }

        return false;
    }

    public function getMessages(): array
    {
        return $this->messages;
    }
}
