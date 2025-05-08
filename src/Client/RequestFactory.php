<?php

declare(strict_types=1);

namespace Phenogram\Framework\Client;

use Amp\Http\Client\Form;
use Amp\Http\Client\Request;
use Amp\Http\Client\StreamedContent;
use Phenogram\Framework\Exception\PhenogramException;
use Phenogram\Framework\Type\LocalFileInterface;
use Phenogram\Framework\Type\ReadableStreamFileInterface;

class RequestFactory implements RequestFactoryInterface
{
    public function __construct(
        private readonly string $token,
        private readonly string $apiUrl = 'https://api.telegram.org',
    ) {
    }

    public function createRequest(string $method, array $data): Request
    {
        $request = new Request(
            "{$this->apiUrl}/bot{$this->token}/{$method}",
            'POST'
        );

        $request->setBody(
            $this->constructBody($data)
        );

        $request->setTransferTimeout(0);
        $request->setInactivityTimeout(0);

        return $request;
    }

    protected function constructBody(array $data): Form
    {
        $body = new Form();
        foreach ($data as $key => $value) {
            if ($value instanceof ReadableStreamFileInterface) {
                $body->addStream($key, StreamedContent::fromStream($value->stream), $value->filename);
            } elseif ($value instanceof LocalFileInterface) {
                if (!file_exists($value->filepath)) {
                    throw new PhenogramException("File not found: {$value->filepath}");
                }

                $body->addStream($key, StreamedContent::fromFile($value->filepath), $value->filename);
            } else {
                $body->addField($key, is_array($value) ? json_encode($value) : $value);
            }
        }

        return $body;
    }
}
