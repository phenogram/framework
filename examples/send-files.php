<?php

declare(strict_types=1);

namespace Phenogram\Framework\Examples;

use Phenogram\Bindings\ApiInterface;
use Phenogram\Bindings\Types\Interfaces\MessageInterface;
use Phenogram\Framework\TelegramBot;
use Phenogram\Framework\Type\BufferedFile;
use Phenogram\Framework\Type\LocalFile;
use Phenogram\Framework\Type\ReadableStreamFile;

require_once dirname(__DIR__) . '/vendor/autoload.php';

/**
 * @return list<MessageInterface>
 */
function sendExampleFiles(
    ApiInterface $api,
    int|string $chatId,
    string $path,
): array {
    $contents = file_get_contents($path);

    if ($contents === false) {
        throw new \RuntimeException(sprintf('Cannot read file: %s', $path));
    }

    $filename = basename($path);
    $stream = fopen('php://memory', 'w+b');
    if ($stream === false) {
        throw new \RuntimeException('Cannot open an in-memory stream');
    }

    try {
        fwrite($stream, $contents);
        rewind($stream);

        return [
            $api->sendDocument(
                chatId: $chatId,
                document: new LocalFile($path),
            ),
            $api->sendDocument(
                chatId: $chatId,
                document: new ReadableStreamFile(
                    stream: $stream,
                    filename: $filename,
                ),
            ),
            $api->sendDocument(
                chatId: $chatId,
                document: new BufferedFile(
                    content: $contents,
                    filename: $filename,
                ),
            ),
        ];
    } finally {
        fclose($stream);
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $token = getenv('TELEGRAM_BOT_TOKEN');
    $chatId = getenv('TELEGRAM_CHAT_ID');

    if (!is_string($token) || $token === '' || !is_string($chatId) || $chatId === '') {
        fwrite(
            STDERR,
            "Set TELEGRAM_BOT_TOKEN and TELEGRAM_CHAT_ID before you run this example.\n",
        );
        exit(1);
    }

    $bot = new TelegramBot($token);
    sendExampleFiles($bot->api, $chatId, dirname(__DIR__) . '/README.md');
}
