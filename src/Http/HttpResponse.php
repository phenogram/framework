<?php

declare(strict_types=1);

namespace Phenogram\Framework\Http;

final readonly class HttpResponse
{
    public function __construct(
        public int $status,
        public string $body,
    ) {
    }
}
