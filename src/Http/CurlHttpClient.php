<?php

declare(strict_types=1);

namespace Phenogram\Framework\Http;

use Phenogram\Framework\Exception\HttpTransportException;

final class CurlHttpClient implements HttpClientInterface
{
    public function post(string $url, array $fields, int $timeoutMilliseconds): HttpResponse
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new HttpTransportException('Could not initialize cURL');
        }

        $configured = curl_setopt_array($handle, [
            CURLOPT_CONNECTTIMEOUT_MS => $timeoutMilliseconds,
            CURLOPT_LOW_SPEED_LIMIT => 1,
            CURLOPT_LOW_SPEED_TIME => max(1, (int) ceil($timeoutMilliseconds / 1000)),
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $fields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS => $timeoutMilliseconds,
        ]);

        if (!$configured) {
            throw new HttpTransportException('Could not configure cURL request');
        }

        $body = curl_exec($handle);
        if ($body === false) {
            throw new HttpTransportException(sprintf('cURL request failed (%d): %s', curl_errno($handle), curl_error($handle)));
        }

        return new HttpResponse(
            status: curl_getinfo($handle, CURLINFO_RESPONSE_CODE),
            body: $body,
        );
    }
}
