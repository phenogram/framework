<?php

declare(strict_types=1);

namespace Phenogram\Framework\Handler;

use Phenogram\Bindings\Types\Update;
use Phenogram\Framework\TelegramBot;

class CallableHandler implements UpdateHandlerInterface
{
    /** @var callable */
    private $callable;

    public function __construct(
        callable $callable
    ) {
        $this->callable = $callable;

        $this->validateCallable();
    }

    public function handle(Update $update, TelegramBot $bot)
    {
        return ($this->callable)($update, $bot);
    }

    protected function validateCallable(): void
    {
        if (!is_callable($this->callable)) {
            throw new \InvalidArgumentException('Callback must be callable');
        }

        $reflection = new \ReflectionFunction($this->callable);
        if ($reflection->getNumberOfParameters() > 2) {
            throw new \InvalidArgumentException(sprintf('Callable should be compatible with "%s::handle" method signature. It should accept at most 2 parameters, %d given.', static::class, $reflection->getNumberOfParameters()));
        }

        $thisClassReflection = new \ReflectionClass($this);
        $handleMethodReflection = $thisClassReflection->getMethod('handle');
        $handleParameters = $handleMethodReflection->getParameters();

        foreach ($handleParameters as $index => $param) {
            $callableParam = $reflection->getParameters()[$index];

            if ($callableParam === null) {
                continue;
            }

            if ($param->getName() !== $callableParam->getName()) {
                throw new \InvalidArgumentException(sprintf('CommandHandler callable should match "%s::handle" method signature.Parameter #%d should be named "%s", now it\'s named "%s".', static::class, $index, $param->getName(), $callableParam->getName()));
            }

            if ($param->getType()->getName() !== $callableParam->getType()?->getName()) {
                throw new \InvalidArgumentException(sprintf('CommandHandler callable should match "%s::handle" method signature. Parameter #%d should be of type "%s", now it\'s "%s".', static::class, $index, $param->getType()->getName(), $callableParam->getType()?->getName() ?? 'unknown'));
            }
        }
    }
}
