<?php

declare(strict_types=1);

namespace Phenogram\Framework\Client;

use Amp\Http\Client\Request;

interface RequestFactoryInterface
{
    /**
     * Creates a base Request object for the given Telegram API method and URI.
     * Implementations can customize headers, timeouts, etc., on the returned Request object.
     * The body will be set by the TelegramBotApiClient after this method returns.
     *
     * @param string $method the fully constructed URI for the request
     * @param array  $data   The raw data array that will be used to build the request body.
     *                       This is provided for context; the factory might use it to make decisions
     *                       (e.g., set different timeouts for file uploads), but it's not
     *                       responsible for building the Form body from it.
     *
     * @return Request the Request object, without the body set
     */
    public function createRequest(string $method, array $data): Request;
}
