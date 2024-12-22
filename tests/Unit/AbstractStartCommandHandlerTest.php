<?php

declare(strict_types=1);

namespace Phenogram\Framework\Tests\Unit;

use Phenogram\Bindings\Types\Interfaces\UpdateInterface;
use Phenogram\Framework\Factories\MessageEntityFactory;
use Phenogram\Framework\Factories\MessageFactory;
use Phenogram\Framework\Factories\UpdateFactory;
use Phenogram\Framework\Handler\AbstractStartCommandHandler;
use Phenogram\Framework\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class AbstractStartCommandHandlerTest extends TestCase
{
    public static function supportsDataProvider(): \Generator
    {
        yield 'supports' => [
            'update' => UpdateFactory::make(
                message: MessageFactory::make(
                    entities: [
                        MessageEntityFactory::make(
                            offset: 0,
                            length: 6,
                            type: 'bot_command'
                        ),
                    ],
                    text: '/start',
                ),
            ),
            'expected' => true,
        ];

        yield 'do not support random command' => [
            'update' => UpdateFactory::make(
                message: MessageFactory::make(
                    text: $command = '/' . self::fake()->word(),
                    entities: [
                        MessageEntityFactory::make(
                            offset: 0,
                            length: mb_strlen($command),
                            type: 'bot_command'
                        ),
                    ],
                ),
            ),
            'expected' => false,
        ];

        yield 'do not support random text' => [
            'update' => UpdateFactory::make(
                message: MessageFactory::make(
                    text: self::fake()->sentence(),
                ),
            ),
            'expected' => false,
        ];
    }

    #[DataProvider('supportsDataProvider')]
    public function testSupports(UpdateInterface $update, bool $expected): void
    {
        $this->assertSame($expected, AbstractStartCommandHandler::supports($update));
    }

    public static function extractArgumentsDataProvider(): \Generator
    {
        yield 'extract arguments' => [
            'update' => UpdateFactory::make(
                message: MessageFactory::make(
                    text: '/start ' . ($argument = self::fake()->word()),
                    entities: [
                        MessageEntityFactory::make(
                            offset: 0,
                            length: 6,
                            type: 'bot_command'
                        ),
                    ],
                ),
            ),
            'expected' => $argument,
        ];

        yield 'no arguments' => [
            'update' => UpdateFactory::make(
                message: MessageFactory::make(
                    text: '/start',
                    entities: [
                        MessageEntityFactory::make(
                            offset: 0,
                            length: 6,
                            type: 'bot_command'
                        ),
                    ],
                ),
            ),
            'expected' => null,
        ];
    }

    #[DataProvider('extractArgumentsDataProvider')]
    public function testExtractArguments(UpdateInterface $update, ?string $expected): void
    {
        $reflection = new \ReflectionClass(AbstractStartCommandHandler::class);
        $extractArguments = $reflection->getMethod('extractArguments');
        $extractArguments->setAccessible(true);

        $this->assertSame($expected, $extractArguments->invoke(null, $update));
    }
}
