<?php

namespace Phenogram\Framework\Tests\Mock;

use Phenogram\Bindings\ClientInterface;

use function Amp\delay;

class MockTelegramBotApiClient implements ClientInterface
{
    private const NONE_METHOD_KEY = '_none';
    public array $responses = [];

    public function __construct(
        public float $responseTimeout = 1.5,
        public ?string $defaultResponse = null,
    ) {
    }

    public function addResponse(string $response, string $method = null): self
    {
        $key = $method ?? self::NONE_METHOD_KEY;
        if (!isset($this->responses[$key])) {
            $this->responses[$key] = [];
        }

        $this->responses[$key][] = $response;

        return $this;
    }

    public function getResponse(string $method = null): string
    {
        $key = $method ?? self::NONE_METHOD_KEY;

        if (!isset($this->responses[$key]) || count($this->responses[$key]) === 0) {
            if ($this->defaultResponse !== null) {
                return $this->defaultResponse;
            }

            throw new \LogicException(sprintf("No responses for method '%s'", $method ?? 'none'));
        }

        return array_shift($this->responses[$key]);
    }

    public function sendRequest(string $method, string $json): string
    {
        delay($this->responseTimeout);

        return $this->getResponse($method);
    }
}
