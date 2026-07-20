<?php

declare(strict_types=1);

namespace Phenogram\Framework\Tests\Unit;

use Phenogram\Bindings\ApiInterface;
use Phenogram\Framework\TelegramBot;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class TelegramBotTest extends TestCase
{
    public function testApiPropertyAcceptsAnyApiInterfaceImplementation(): void
    {
        $api = $this->createStub(ApiInterface::class);

        $bot = new TelegramBot(
            token: 'test-token',
            api: $api,
            logger: new NullLogger(),
        );

        self::assertSame($api, $bot->api);
    }
}
