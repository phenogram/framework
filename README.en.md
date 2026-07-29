**English** | [Русский](README.md)

# Phenogram Framework

[![CI](https://github.com/phenogram/framework/actions/workflows/ci.yaml/badge.svg)](https://github.com/phenogram/framework/actions/workflows/ci.yaml)
[![PHP 8.6](https://img.shields.io/badge/PHP-8.6-777BB4.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

A typed application framework for Telegram bots on True Async PHP 8.6.

Phenogram Framework adds long polling, routes, middleware, concurrent handlers, logging, and file uploads to [Phenogram Bindings](https://github.com/phenogram/bindings).

> [!WARNING]
> Version 7 and True Async PHP are under active development. Evaluate the package before you use them in production.

## Compatibility

| Framework | PHP | Bindings | Telegram Bot API model |
| --- | --- | --- | --- |
| 7.0.x-dev | `^8.6` + `ext-true_async:^0.8.2` | `^7` | 9.6 |

Framework 7 requires `phenogram/bindings:^7` and a True Async PHP 8.6 build. Bindings 7 contains the generated model for Telegram Bot API 9.6. This statement does not claim support for later Bindings major versions or later Telegram Bot API versions.

Do not install Bindings 8 or 9 with Framework 7 unless a new Framework release declares that support.

## Package scope

Use this package when you need an application layer for a Telegram bot.

The package provides:

- a coroutine-aware cURL client for Telegram Bot API requests;
- long polling with `getUpdates`;
- routes and route conditions;
- middleware and route groups;
- concurrent update handlers with True Async coroutines;
- PSR-3 logging;
- local-file, stream, and buffered-file uploads.

Use [Phenogram Bindings](https://github.com/phenogram/bindings) without this package when you only need typed API methods, Telegram types, serialization, and deserialization.

This package does not provide a webhook server, data storage, a queue, or a deployment platform.

## Requirements

- True Async PHP `^8.6` with `true_async:^0.8.2`;
- the PHP `curl` extension;
- Composer 2;
- a Telegram bot token for live use.

The offline examples and the default test suite do not need a token.

## Installation

```bash
composer require phenogram/framework
```

## Examples

The repository contains complete example files. The offline test suite loads these files directly. The tests use an in-memory Telegram client and do not use the network.

The commands below require a source checkout.
Prepare the checkout before you run them:

```bash
git clone https://github.com/phenogram/framework.git
cd framework
composer install
composer tools:install
```

Run all example tests:

```bash
composer test:examples
```

### Echo bot

[`examples/echo-bot.php`](examples/echo-bot.php) creates a bot that repeats each text message.

The route condition rejects updates that do not contain text. The handler can then read the message and chat fields.

Run the bot:

The token below is an intentionally invalid documentation example.

```bash
export TELEGRAM_BOT_TOKEN='7245389610:AAFHBDYMKpWxYu5JrSnTlQRD9bvPz0OgHkLf'
php examples/echo-bot.php
```

The command uses Telegram and needs network access. Stop the bot with `Ctrl+C`.

### Route group and middleware

[`examples/route-group.php`](examples/route-group.php) adds a `/ping` route for one Telegram user.

The route condition selects `/ping` text messages. `IsUserMiddleware` permits only the configured user. The handler sends `pong`.

Call `addPingRoute($bot, $allowedUserId)` before you call `$bot->run()`.

Keep each `RouteConfigurator` chain in one expression. Do not store an unfinished configurator. The framework registers the route when it releases the configurator.

### File uploads

[`examples/send-files.php`](examples/send-files.php) sends one file in three forms.

| Input | Class | Use |
| --- | --- | --- |
| Local path | `LocalFile` | Let the HTTP client open a file from a path. |
| Readable stream | `ReadableStreamFile` | Read data from a regular PHP stream resource. |
| String buffer | `BufferedFile` | Send data that is already in memory. |

For a Telegram file ID or a public URL, pass the string directly to the applicable Bindings API method.

Run the live file example:

```bash
export TELEGRAM_BOT_TOKEN='your-token'
export TELEGRAM_CHAT_ID='123456789'
php examples/send-files.php
```

This command sends three copies of the default Russian README file to the selected chat.

## Core API

### Create a bot

`TelegramBot` accepts a token, an optional `ApiInterface`, and an optional PSR-3 logger.

The public `$bot->api` property has the `ApiInterface` type. You can inject a compatible API implementation for tests or for a custom transport.

If you do not inject an API implementation, the framework creates:

- `TelegramBotApiClient` as the HTTP transport;
- `Phenogram\Bindings\Serializer` as the serializer;
- `Phenogram\Bindings\Api` as the typed API.

### Add handlers

Use `$bot->addHandler(...)` for one route. Add `->supports(...)` when the handler must accept only specific updates.

Use `$bot->defineHandlers(...)` when you need a `Router`, route groups, or shared middleware.

A handler can accept these parameters:

1. `UpdateInterface $update`
2. `TelegramBot $bot`

A handler can also accept fewer parameters. The framework schedules all supported handlers as `Async\Coroutine` instances.

### Process one update

Use `$bot->handleUpdate($update)` when another component supplies the update. The method returns the handler coroutines. Use `Async\await_all()` when the caller must know that processing is complete.

This method is useful for tests and for a separate webhook adapter.

### Start long polling

Call `$bot->run()` to start `getUpdates` long polling.

The method blocks until the bot stops. Call `$bot->stop()` from application code when you must stop the polling loop.

The `allowedUpdates` argument accepts `UpdateType` values. The `limit` value must follow the Telegram Bot API limits.

### Handle errors

The framework sends log records to an available PSR-3 logger. It uses `EchoLogger` when discovery does not find a logger.

Set `$bot->errorHandler` when the application needs custom error reporting. The callback receives the error and the bot instance.

Do not log the bot token. Treat every token as a secret.

## Tests and quality checks

Install the project and the isolated style tool:

```bash
composer install
composer tools:install
```

Run the same offline checks as CI:

```bash
composer check
```

The command validates Composer metadata, checks code style, and runs the offline PHPUnit suite.

PHP-CS-Fixer does not support running on PHP 8.6 yet. The `style` and `fix`
scripts automatically look for a separate PHP 8.4/8.5 binary; set
`PHP_CS_FIXER_PHP=/path/to/php` when auto-detection is not enough.

You can also run one check:

```bash
composer test
composer test:examples
composer style
composer fix
```

The default PHPUnit configuration:

- does not load `.env`;
- does not use Telegram credentials;
- does not make network requests;
- excludes `tests/Integration`.

### Benchmark

Run the concurrent-handler benchmark:

```bash
composer benchmark
```

The methodology, comparison with the original Amp/Revolt implementation, and
raw results are in [`benchmarks/README.md`](benchmarks/README.md).

## Live integration tests

Live tests are separate from the default suite. They call Telegram and can send files to a real chat.

Set an explicit gate and the required credentials:

```bash
export RUN_TELEGRAM_INTEGRATION=1
export TELEGRAM_BOT_TOKEN='your-token'
export TEST_CHAT_ID='123456789'
composer test:integration
```

The integration bootstrap can also read these values from a local `.env` file. The repository ignores `.env`.
Values from the process environment take precedence over values from `.env`.

Use a dedicated test bot and a dedicated test chat. Do not run live tests in CI with production credentials.

## Security

- Keep the bot token outside source control.
- Use environment variables or a secret manager.
- Rotate a token immediately if it appears in a log, commit, issue, or chat.
- Review dependencies before each release.

## Documentation style

English documentation uses ASD-STE100-style controlled English.

- Use short sentences.
- Give one instruction in each sentence.
- Use one term for one meaning.
- Explain an abbreviation before you use it.
- Use active voice when possible.

## Contributing

Open an issue before a large change. Keep changes small. Add an offline test for each behavior change. Update both README files when public behavior changes.

Do not add a new Bindings major version without a compatibility review.

## License

Phenogram Framework is available under the [MIT License](LICENSE).
