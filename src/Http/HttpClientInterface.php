<?php

declare(strict_types=1);

namespace Phenogram\Framework\Http;

interface HttpClientInterface
{
    /**
     * @param array<string, string|\CURLFile|\CURLStringFile> $fields
     */
    public function post(string $url, array $fields, int $timeoutMilliseconds): HttpResponse;
}
