<?php

declare(strict_types=1);

namespace Phenogram\Framework\Type;

use Amp\ByteStream\ReadableStream;
use Phenogram\Bindings\Types\Interfaces\InputFileInterface;

interface ReadableStreamFileInterface extends InputFileInterface
{
    public ReadableStream $stream { get; }
    public ?string $filename { get; }
}
