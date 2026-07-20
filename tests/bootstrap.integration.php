<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);

require $projectRoot . '/vendor/autoload.php';

Dotenv\Dotenv::createImmutable($projectRoot)->safeLoad();
