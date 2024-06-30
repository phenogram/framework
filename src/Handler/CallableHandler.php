<?php

declare(strict_types=1);

namespace Shanginn\TelegramBotApiFramework\Handler;

use Shanginn\TelegramBotApiBindings\Types\Update;
use Shanginn\TelegramBotApiFramework\TelegramBot;

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
