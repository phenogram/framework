<?php

declare(strict_types=1);

namespace Phenogram\Framework\Type;

class LocalFile implements LocalFileInterface
{
    public readonly string $filename;

    public function __construct(
        public readonly string $filepath,
        ?string $filename = null,
    ) {
        $this->filename = $filename ?? basename($filepath);
    }
}
