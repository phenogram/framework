<?php

declare(strict_types=1);

namespace Phenogram\Framework\Interface;

use Phenogram\Bindings\Types\Interfaces\UpdateInterface;
use Phenogram\Framework\Handler\UpdateHandlerInterface;

interface RouteInterface
{
    public function supports(UpdateInterface $update): bool;

    public function getHandler(): UpdateHandlerInterface;
}
