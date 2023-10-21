<?php

namespace Shanginn\TelegramBotApiFramework\Handler;

use React\Promise\PromiseInterface;
use Shanginn\TelegramBotApiBindings\Types\MessageEntity;
use Shanginn\TelegramBotApiBindings\Types\Update;
use Shanginn\TelegramBotApiFramework\TelegramBot;

class CommandHandler implements UpdateHandlerInterface
{
    protected $callback;

    public function __construct(
        protected readonly string $command,
        callable $callback,
        protected readonly ?string $description = null,
        protected readonly ?string $usage = null,
    ) {
        $this->validateCallback($callback);

        $this->callback = $callback;
    }

    protected function validateCallback(callable $callback): void
    {
        if (!is_callable($callback)) {
            throw new \InvalidArgumentException('Callback must be callable');
        }

        $reflection = new \ReflectionFunction($callback);
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

    protected function extractCommands(array $entities, string $text): array
    {
        $commands = [];

        $botCommands = array_filter(
            $entities,
            fn (MessageEntity $entity) => $entity->type === 'bot_command'
        );

        $bytes = unpack('C*', $text);

        foreach ($botCommands as $entity) {
            $byteOffset = $this->utf16OffsetToByteOffset($text, $entity->offset);

            $commandBytes = array_slice($bytes, $byteOffset, $entity->length);
            $commandStr = pack('C*', ...$commandBytes);
            $commands[] = $commandStr;
        }

        return $commands;
    }

    protected function utf16OffsetToByteOffset(string $text, int $utf16Offset): int
    {
        $byteOffset = 0;
        $utf16Counter = 0;

        for ($i = 0; $i < mb_strlen($text, 'UTF-8'); ++$i) {
            $char = mb_substr($text, $i, 1, 'UTF-8');
            $charCode = mb_ord($char, 'UTF-8');

            if ($charCode >= 0x10000) {
                $utf16Counter += 2;
            } else {
                ++$utf16Counter;
            }

            if ($utf16Counter > $utf16Offset) {
                break;
            }

            $byteOffset += strlen($char);
        }

        return $byteOffset;
    }

    public function supports(Update $update): bool
    {
        return $update->message?->entities !== null
            && $update->message->text !== null
            && in_array(
                $this->command,
                $this->extractCommands(
                    $update->message->entities,
                    $update->message->text
                )
            );
    }

    public function handle(Update $update, TelegramBot $bot): PromiseInterface
    {
        return ($this->callback)($update, $bot);
    }
}
