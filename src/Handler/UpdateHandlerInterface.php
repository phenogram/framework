<?php

namespace Phenogram\Framework\Handler;

use Phenogram\Bindings\Types\Update;
use Phenogram\Framework\TelegramBot;

interface UpdateHandlerInterface
{
    public function handle(Update $update, TelegramBot $bot);
}
