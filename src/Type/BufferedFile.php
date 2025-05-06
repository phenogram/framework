<?php

declare(strict_types=1);

namespace Phenogram\Framework\Type;

use Amp\ByteStream\ReadableBuffer;
use Amp\File\FilesystemException;

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

    /**
     * @throws FilesystemException
     */
    public static function openFile(string $path): self
    {
        $file = \Amp\File\openFile($path, 'r');
        $content = '';

        while (null !== $chunk = $file->read()) {
            $content .= $chunk;
        }

        return new self(
            content: $content,
            filename: basename($path),
        );
    }
}
