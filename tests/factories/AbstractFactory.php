<?php

declare(strict_types=1);

namespace Phenogram\Framework\Tests\factories;

use Faker\Factory;
use Faker\Generator;

abstract class AbstractFactory
{
    private static Generator $faker;

    protected static function fake(): Generator
    {
        if (!isset(static::$faker)) {
            static::$faker = Factory::create();
        }

        return static::$faker;
    }
}
