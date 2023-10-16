<?php

namespace Shanginn\TelegramBotApiFramework;

use React\Promise\PromiseInterface;
use Shanginn\TelegramBotApiBindings\Types\Update;

interface UpdateHandlerInterface
{
    public function supports(Update $update): bool;

    public function handle(Update $update, TelegramBot $bot): PromiseInterface;
}
