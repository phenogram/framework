<?php

declare(strict_types=1);

namespace Phenogram\Framework\Examples;

use Phenogram\Bindings\Types\Interfaces\UpdateInterface;
use Phenogram\Framework\Middleware\IsUserMiddleware;
use Phenogram\Framework\Router\Router;
use Phenogram\Framework\TelegramBot;

require_once dirname(__DIR__) . '/vendor/autoload.php';

function addPingRoute(TelegramBot $bot, int $allowedUserId): void
{
    $bot->defineHandlers(
        static function (Router $router) use ($allowedUserId): void {
            $group = $router
                ->addGroup()
                ->middleware(new IsUserMiddleware($allowedUserId));

            $group
                ->add()
                ->handler(
                    static fn (UpdateInterface $update, TelegramBot $bot) => $bot->api->sendMessage(
                        chatId: $update->message->chat->id,
                        text: 'pong',
                    ),
                )
                ->supports(
                    static fn (UpdateInterface $update): bool => $update->message?->text === '/ping'
                        && $update->message->from !== null,
                );
        },
    );
}
