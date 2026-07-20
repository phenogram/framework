<?php

declare(strict_types=1);

namespace Phenogram\Framework\Tests\Unit;

use Phenogram\Framework\Tests\Support\Environment;
use PHPUnit\Framework\TestCase;

final class EnvironmentTest extends TestCase
{
    public function testProcessEnvironmentHasPrecedenceOverLoadedValues(): void
    {
        $name = 'PHENOGRAM_TEST_ENV_PRECEDENCE';
        $_ENV[$name] = 'dotenv-value';
        $_SERVER[$name] = 'server-value';
        putenv($name . '=process-value');

        try {
            self::assertSame('process-value', Environment::get($name));
        } finally {
            putenv($name);
            unset($_ENV[$name], $_SERVER[$name]);
        }
    }
}
