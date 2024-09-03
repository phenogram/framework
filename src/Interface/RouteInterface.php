<?php

declare(strict_types=1);

namespace Phenogram\Framework\Interface;

use Phenogram\Bindings\Types\Update;
use Phenogram\Framework\Handler\UpdateHandlerInterface;

interface RouteInterface
{
    public function supports(Update $update): bool;

    public function getHandler(): UpdateHandlerInterface;
}
