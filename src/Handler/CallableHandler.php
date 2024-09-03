<?php

declare(strict_types=1);

namespace Phenogram\Framework\Handler;

use Phenogram\Bindings\Types\Update;
use Phenogram\Framework\TelegramBot;

class CallableHandler implements UpdateHandlerInterface
{
    /** @var callable */
    private $callable;

    public function __construct(
        callable $callable
    ) {
        $this->callable = $callable;
    }

    public function handle(Update $update, TelegramBot $bot)
    {
        return ($this->callable)($update, $bot);
    }
}
