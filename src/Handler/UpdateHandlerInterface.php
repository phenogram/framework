<?php

namespace Shanginn\TelegramBotApiFramework\Handler;

use Phenogram\Bindings\Types\Update;
use Shanginn\TelegramBotApiFramework\TelegramBot;

interface UpdateHandlerInterface
{
    public function handle(Update $update, TelegramBot $bot);
}
