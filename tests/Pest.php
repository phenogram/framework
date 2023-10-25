<?php

use Shanginn\TelegramBotApiFramework\Tests\TestCase;

// uses(TestCase::class)->in('Feature');

$projectRoot = dirname(__DIR__);

Dotenv\Dotenv::createImmutable($projectRoot)->safeLoad();
