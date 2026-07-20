<?php

declare(strict_types=1);

namespace Phenogram\Framework\Examples;

use Phenogram\Bindings\ApiInterface;
use Phenogram\Bindings\Types\Interfaces\UpdateInterface;
use Phenogram\Framework\TelegramBot;
use Psr\Log\LoggerInterface;

require_once dirname(__DIR__) . '/vendor/autoload.php';

function createEchoBot(
    string $token,
    ?ApiInterface $api = null,
    ?LoggerInterface $logger = null,
): TelegramBot {
    $bot = new TelegramBot(
        token: $token,
        api: $api,
        logger: $logger,
    );

    $bot
        ->addHandler(
            static fn (UpdateInterface $update, TelegramBot $bot) => $bot->api->sendMessage(
                chatId: $update->message->chat->id,
                text: $update->message->text,
            ),
        )
        ->supports(
            static fn (UpdateInterface $update): bool => $update->message?->text !== null,
        );

    return $bot;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $token = getenv('TELEGRAM_BOT_TOKEN');

    if (!is_string($token) || $token === '') {
        fwrite(STDERR, "Set TELEGRAM_BOT_TOKEN before you run this example.\n");
        exit(1);
    }

    createEchoBot($token)->run();
}
