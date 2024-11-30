<?php

declare(strict_types=1);

namespace Phenogram\Framework\Tests\Router;

use Phenogram\Bindings\Types\Update;
use Phenogram\Framework\Factories\UpdateFactory;
use Phenogram\Framework\Handler\UpdateHandlerInterface;
use Phenogram\Framework\Interface\RouteInterface;
use Phenogram\Framework\Middleware\MiddlewareInterface;
use Phenogram\Framework\Router\Router;
use Phenogram\Framework\TelegramBot;
use PHPUnit\Framework\TestCase;

use function Amp\Future\await;

class DefineRoutesTest extends TestCase
{
    public function testSetRoute()
    {
        $router = new Router();

        $echoHandler = new class() implements UpdateHandlerInterface {
            public function handle(Update $update, TelegramBot $bot): void
            {
                echo $update->message->text;
            }
        };

        $echoHandlerRoute = new class($echoHandler) implements RouteInterface {
            public function __construct(private UpdateHandlerInterface $handler)
            {
            }

            public function supports(Update $update): bool
            {
                return true;
            }

            public function getHandler(): UpdateHandlerInterface
            {
                return $this->handler;
            }
        };

        $router->registerRoute($echoHandlerRoute);

        $supportedHandlers = iterator_to_array($router->supportedHandlers(UpdateFactory::make()));

        $this->assertCount(1, $supportedHandlers);
        $this->assertEquals($echoHandler, $supportedHandlers[0]);
    }

    public function testDefineRouteCollection()
    {
        $router = new Router();

        $counter = new class() {
            public int $count = 0;
        };

        $router
            ->add()
            ->handler(fn (Update $update, TelegramBot $bot) => $counter->count++);

        $callableHandlerClass = new class($counter) {
            public function __construct(
                private $counter
            ) {
            }

            public function incrementCounter(Update $update, TelegramBot $bot)
            {
                ++$this->counter->count;
            }
        };

        $router
            ->add()
            ->handler($callableHandlerClass->incrementCounter(...));

        $router
            ->add()
            ->handler(new class($counter) implements UpdateHandlerInterface {
                public function __construct(
                    private $counter
                ) {
                }

                public function handle(Update $update, TelegramBot $bot): void
                {
                    ++$this->counter->count;
                }
            });

        $bot = new TelegramBot('token');
        foreach ($router->supportedHandlers(UpdateFactory::make()) as $handler) {
            $handler->handle(UpdateFactory::make(), $bot);
        }

        $this->assertEquals(3, $counter->count);
    }

    public function testDefineRoutesInBot(): void
    {
        $bot = new TelegramBot('token');
        $counter = new class() {
            public int $count = 0;
        };

        $middleware = new class($counter) implements MiddlewareInterface {
            public function __construct(
                private $counter
            ) {
            }

            public function process(Update $update, UpdateHandlerInterface $handler, TelegramBot $bot): void
            {
                ++$this->counter->count;
                $handler->handle($update, $bot);
            }
        };

        $bot->defineRoutes(function (Router $router) use ($counter, $middleware) {
            $group = $router->addGroup()->middleware($middleware);
            $group->add()->handler(fn (Update $update, TelegramBot $bot) => $counter->count++);
            $group->add()->handler(fn (Update $update, TelegramBot $bot) => $counter->count++);
        });

        await($bot->handleUpdate(UpdateFactory::make()));

        $this->assertEquals(4, $counter->count);
    }
}
