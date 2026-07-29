<?php

declare(strict_types=1);

namespace Phenogram\Framework\Type;

use Phenogram\Bindings\Types\Interfaces\InputFileInterface;

interface ReadableStreamFileInterface extends InputFileInterface
{
    public ?string $filename { get; }

    /**
     * @param resource $destination
     */
    public function writeTo(mixed $destination): void;
}
