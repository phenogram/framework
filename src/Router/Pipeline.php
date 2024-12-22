<?php

declare(strict_types=1);

namespace Phenogram\Framework\Router;

use Phenogram\Bindings\Types\Interfaces\UpdateInterface;
use Phenogram\Framework\Exception\PipelineException;
use Phenogram\Framework\Handler\UpdateHandlerInterface;
use Phenogram\Framework\Middleware\MiddlewareInterface;
use Phenogram\Framework\TelegramBot;

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

    public function process(UpdateInterface $update, UpdateHandlerInterface $handler, TelegramBot $bot): void
    {
        $this->withHandler($handler)->handle($update, $bot);
    }

    public function handle(UpdateInterface $update, TelegramBot $bot): void
    {
        if ($this->handler === null) {
            throw new PipelineException('Unable to run pipeline, no handler given.');
        }

        $position = $this->position++;
        if (isset($this->middleware[$position])) {
            $middleware = $this->middleware[$position];

            $middleware->process($update, $this, $bot);

            return;
        }

        $this->handler->handle($update, $bot);
    }
}
