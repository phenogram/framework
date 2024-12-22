<?php

namespace Phenogram\Framework\Handler;

use Phenogram\Bindings\Types\Interfaces\UpdateInterface;
use Phenogram\Framework\TelegramBot;

interface UpdateHandlerInterface
{
    public function handle(UpdateInterface $update, TelegramBot $bot);
}
