<?php

namespace Shanginn\TelegramBotApiFramework;

use React\EventLoop\Loop;
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

    public function poolUpdates(
        int $offset = null,
        ?int $limit = 100,
        int $timeout = null,
        array $allowedUpdates = null,
    ): \Generator {
        $offset = $offset ?? 1;
        $timeout = $timeout ?? 15;

        $i = 0;
        while ($i++ < 5) {
            $updates = $this->api->getUpdates(
                offset: $offset,
                limit: $limit,
                timeout: $timeout,
                allowedUpdates: $allowedUpdates,
            );

            foreach ($updates as $update) {
                yield $update;

                $offset = max($offset, $update->updateId + 1);
            }
        }
    }
}
