<?php

declare(strict_types=1);

namespace Phenogram\Framework\Tests\Support;

final class Environment
{
    public static function get(string $name): ?string
    {
        $processValue = getenv($name);
        if (is_string($processValue)) {
            return $processValue;
        }

        $value = $_SERVER[$name] ?? $_ENV[$name] ?? null;

        return is_string($value) ? $value : null;
    }
}
