<?php

declare(strict_types=1);

namespace Phenogram\Framework\Type;

use Phenogram\Framework\Exception\PhenogramException;

class ReadableStreamFile implements ReadableStreamFileInterface
{
    /**
     * @var resource
     */
    public readonly mixed $stream;

    /**
     * @param resource $stream
     */
    public function __construct(
        mixed $stream,
        public readonly string $filename,
    ) {
        if (!is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw new \TypeError('ReadableStreamFile expects a PHP stream resource');
        }

        $this->stream = $stream;
    }

    public function writeTo(mixed $destination): void
    {
        if (!is_resource($destination) || get_resource_type($destination) !== 'stream') {
            throw new \TypeError('ReadableStreamFile::writeTo() expects a PHP stream resource');
        }

        $metadata = stream_get_meta_data($this->stream);
        if (($metadata['blocked'] ?? true) === false) {
            throw new PhenogramException(sprintf('Readable stream for %s must be in blocking mode', $this->filename));
        }

        $copied = stream_copy_to_stream($this->stream, $destination);
        $metadata = stream_get_meta_data($this->stream);

        if (
            $copied === false
            || ($metadata['timed_out'] ?? false)
        ) {
            throw new PhenogramException(sprintf('Could not read stream for %s', $this->filename));
        }
    }
}
