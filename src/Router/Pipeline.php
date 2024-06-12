<?php

declare(strict_types=1);

namespace Shanginn\TelegramBotApiFramework\Middleware;

use Shanginn\TelegramBotApiBindings\Types\Update;
use Shanginn\TelegramBotApiFramework\Exception\PipelineException;
use Shanginn\TelegramBotApiFramework\Handler\UpdateHandlerInterface;
use Shanginn\TelegramBotApiFramework\TelegramBot;

/**
 * Pipeline used to pass request and response thought the chain of middleware.
 */
final class Pipeline implements UpdateHandlerInterface, MiddlewareInterface
{
    use MiddlewareTrait;

    private int $position = 0;
    private ?UpdateHandlerInterface $handler = null;

    public function withHandler(UpdateHandlerInterface $handler): self
    {
        $pipeline = clone $this;
        $pipeline->handler = $handler;
        $pipeline->position = 0;

        return $pipeline;
    }

    public function process(Update $update, UpdateHandlerInterface $handler, TelegramBot $bot)
    {
        return $this->withHandler($handler)->handle($update, $bot);
    }

    public function handle(Update $update, TelegramBot $bot)
    {
        if ($this->handler === null) {
            throw new PipelineException('Unable to run pipeline, no handler given.');
        }

        $position = $this->position++;
        if (isset($this->middleware[$position])) {
            $middleware = $this->middleware[$position];

            return $middleware->process($update, $this, $bot);
        }

        return $this->handler->handle($update, $bot);
    }
}
