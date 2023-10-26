<?php

namespace Shanginn\TelegramBotApiFramework\Handler;

use Shanginn\TelegramBotApiBindings\Types\Update;
use Shanginn\TelegramBotApiFramework\TelegramBot;

interface UpdateHandlerInterface
{
    public function supports(Update $update): bool;

    public function handle(Update $update, TelegramBot $bot);
}
