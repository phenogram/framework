<?php

declare(strict_types=1);

namespace Phenogram\Framework\Type;

use Amp\ByteStream\ReadableBuffer;

class BufferedFile implements ReadableStreamFileInterface
{
    public ReadableBuffer $stream {
        get {
            return new ReadableBuffer($this->content);
        }
    }

    public function __construct(
        private readonly string $content,
        public readonly string $filename,
    ) {
    }
}
