<?php

namespace Phenogram\Framework\Tests\mocks;

use Phenogram\Bindings\ClientInterface;
use Phenogram\Bindings\Serializer;
use Phenogram\Bindings\SerializerInterface;
use Phenogram\Bindings\Types\Interfaces\ResponseInterface;
use Phenogram\Bindings\Types\Interfaces\TypeInterface;
use Phenogram\Bindings\Types\Response;

use function Amp\delay;

class MockTelegramBotApiClient implements ClientInterface
{
    private const string NONE_METHOD_KEY = '_none';
    private SerializerInterface $serializer;
    public array $responses = [];

    public function __construct(
        public float $responseTimeout = 0.00,
        public ?ResponseInterface $defaultResponse = null,
    ) {
        $this->serializer = new Serializer();
    }

    /**
     * @param string|TypeInterface|array<TypeInterface> $response
     *
     * @return $this
     */
    public function addResponse(
        string|TypeInterface|array|\Throwable $response,
        ?string $method = null,
    ): self {
        $key = $method ?? self::NONE_METHOD_KEY;
        if (!isset($this->responses[$key])) {
            $this->responses[$key] = [];
        }

        if ($response instanceof \Throwable) {
            $this->responses[$key][] = new Response(
                ok: false,
                description: $response->getMessage(),
                errorCode: $response->getCode(),
            );

            return $this;
        }

        if ($response instanceof TypeInterface) {
            $response = $this->serializer->serialize(get_object_vars($response));
        } elseif (is_array($response)) {
            $response = array_map(
                fn (TypeInterface $item) => $this->serializer->serialize(get_object_vars($item)),
                $response
            );
        } else {
            $response = json_decode($response, true);
        }

        $this->responses[$key][] = new Response(
            ok: true,
            result: $response
        );

        return $this;
    }

    public function getResponse(?string $method = null): Response
    {
        $key = $method ?? self::NONE_METHOD_KEY;

        if (!isset($this->responses[$key]) || count($this->responses[$key]) === 0) {
            if ($this->defaultResponse === null) {
                throw new \LogicException(sprintf("No responses for method '%s'", $method ?? 'none'));
            }

            return $this->defaultResponse;
        }

        return array_shift($this->responses[$key]);
    }

    public function sendRequest(string $method, array $data): Response
    {
        delay($this->responseTimeout);

        return $this->getResponse($method);
    }
}
