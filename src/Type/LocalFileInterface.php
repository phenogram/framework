<?php

declare(strict_types=1);

namespace Phenogram\Framework\Type;

use Phenogram\Bindings\Types\Interfaces\InputFileInterface;

interface LocalFileInterface extends InputFileInterface
{
    public string $filepath { get; }
    public string $filename { get; }
}
