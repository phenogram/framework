<?php

namespace Shanginn\TelegramBotApiFramework\Handler;

use Phenogram\Bindings\Types\Update;
use React\Promise\PromiseInterface;
use Shanginn\TelegramBotApiFramework\TelegramBot;

trait CommandCallbackHandlerTrait
{
    protected $callback;

    protected function validateCallback(): void
    {
        if (!is_callable($this->callback)) {
            throw new \InvalidArgumentException('Callback must be callable');
        }

        $reflection = new \ReflectionFunction($this->callback);
        if ($reflection->getNumberOfParameters() !== 2) {
            throw new \InvalidArgumentException(sprintf('CommandHandler callback should match "%s::handle" method signature. It should accept exactly 2 parameters, %d given.', static::class, $reflection->getNumberOfParameters()));
        }

        $thisClassReflection = new \ReflectionClass($this);
        $handleMethodReflection = $thisClassReflection->getMethod('handle');
        $handleParameters = $handleMethodReflection->getParameters();

        foreach ($handleParameters as $index => $param) {
            $callbackParam = $reflection->getParameters()[$index];

            if ($callbackParam === null || $param->getName() !== $callbackParam->getName()) {
                throw new \InvalidArgumentException(sprintf('CommandHandler callback should match "%s::handle" method signature.Parameter #%d should be named "%s", now it\'s named "%s".', static::class, $index, $param->getName(), $callbackParam->getName()));
            }

            if ($param->getType()->getName() !== $callbackParam->getType()?->getName()) {
                throw new \InvalidArgumentException(sprintf('CommandHandler callback should match "%s::handle" method signature. Parameter #%d should be of type "%s", now it\'s "%s".', static::class, $index, $param->getType()->getName(), $callbackParam->getType()?->getName() ?? 'unknown'));
            }
        }

        $returnType = $reflection->getReturnType();

        if (!$returnType || !$returnType->isBuiltin() && $returnType->getName() !== PromiseInterface::class) {
            throw new \InvalidArgumentException('The provided callback must return an instance of PromiseInterface.');
        }
    }

    public function handle(Update $update, TelegramBot $bot): PromiseInterface
    {
        return ($this->callback)($update, $bot);
    }
}
