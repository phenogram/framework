<?php

declare(strict_types=1);

namespace Phenogram\Framework\Type;

use Amp\ByteStream\ReadableStream;

class ReadableStreamFile implements ReadableStreamFileInterface
{
    public function __construct(
        public readonly ReadableStream $stream,
        public readonly string $filename,
    ) {
    }
}
