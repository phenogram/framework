<?php

declare(strict_types=1);

namespace Shanginn\TelegramBotApiFramework\Interface;

use Psr\Container\ContainerInterface;

interface ContainerizedInterface
{
    /**
     * Associated route with given container.
     */
    public function withContainer(ContainerInterface $container): self;

    /**
     * Indicates that route has associated container.
     */
    public function hasContainer(): bool;
}
