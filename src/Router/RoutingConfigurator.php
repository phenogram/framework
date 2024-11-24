<?php

declare(strict_types=1);

namespace Phenogram\Framework\Router;

class RoutingConfigurator
{
    public function __construct(
        public readonly RouteCollection $collection,
    ) {
    }

    public function add(string $name = null): RouteConfigurator
    {
        return new RouteConfigurator($this->collection, $name);
    }
}
