<?php

namespace Shanginn\TelegramBotApiFramework;

use Shanginn\TelegramBotApiBindings\TelegramBotApi;
use Shanginn\TelegramBotApiBindings\TelegramBotApiClientInterface;

class TelegramBot
{
    public TelegramBotApi $api;

    // TODO: remove?
    protected TelegramBotApiClientInterface $botClient;

    public function __construct(
        protected readonly string $token,
        TelegramBotApiClientInterface $botClient = null,
    ) {
        $this->botClient = $botClient ?? new TelegramBotApiClient($token);
        $this->api = new TelegramBotApi($this->botClient);
    }
}
