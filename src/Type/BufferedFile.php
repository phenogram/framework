<?php

declare(strict_types=1);

namespace Phenogram\Framework\Type;

use Phenogram\Framework\Exception\PhenogramException;

class BufferedFile implements ReadableStreamFileInterface
{
    public function __construct(
        private readonly string $content,
        public readonly string $filename,
    ) {
    }

    /**
     * @throws PhenogramException
     */
    public static function openFile(string $path): self
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new PhenogramException(sprintf('Could not read file: %s', $path));
        }

        return new self(
            content: $content,
            filename: basename($path),
        );
    }

    public function read(): string
    {
        return $this->content;
    }

    public function writeTo(mixed $destination): void
    {
        if (!is_resource($destination) || get_resource_type($destination) !== 'stream') {
            throw new \TypeError('BufferedFile::writeTo() expects a PHP stream resource');
        }

        $offset = 0;
        $length = strlen($this->content);

        while ($offset < $length) {
            $written = fwrite($destination, substr($this->content, $offset));
            if ($written === false || $written === 0) {
                throw new PhenogramException(sprintf('Could not write buffered file %s', $this->filename));
            }

            $offset += $written;
        }
    }
}
