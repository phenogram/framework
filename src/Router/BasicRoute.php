<?php

declare(strict_types=1);

namespace Shanginn\TelegramBotApiFramework\Router;

use Shanginn\TelegramBotApiBindings\Types\Update;
use Shanginn\TelegramBotApiFramework\Handler\UpdateHandlerInterface;

final class BasicRoute extends AbstractRoute
{
    /** @var callable|null */
    private $condition;

    public function __construct(
        UpdateHandlerInterface $handler,
        callable $condition = null,
    ) {
        $this->pipeline = new Pipeline();
        $this->condition = $condition;

        parent::__construct($handler);

        // TODO: check middleware with container
        $this->middleware = $this->config->middleware ?? [];
    }

    public function supports(Update $update): bool
    {
        if ($this->condition === null) {
            return true;
        }

        return ($this->condition)($update);
    }
}
