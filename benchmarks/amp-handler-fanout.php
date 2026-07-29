<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Amp\Future;
use Composer\InstalledVersions;
use Phenogram\Bindings\Factories\UpdateFactory;
use Phenogram\Framework\TelegramBot;
use Psr\Log\NullLogger;

use function Amp\delay;
use function Amp\Future\awaitAll;

/**
 * Retained Amp/Revolt baseline harness.
 *
 * Copy this file into a detached worktree at
 * 10d350ed55cc837fac644443bf5b09de815fc5fa before running it. It intentionally
 * targets the upstream Amp implementation and is not runnable against the
 * native True Async feature checkout.
 */

/**
 * @param list<float> $values
 *
 * @return array{median_ms: float, p95_ms: float, min_ms: float, max_ms: float, raw_ms: list<float>}
 */
function summarize(array $values): array
{
    $raw = array_map(static fn (float $value): float => round($value, 6), $values);
    sort($values, SORT_NUMERIC);
    $count = count($values);
    $rank = static fn (float $percentile): int => min(
        $count - 1,
        max(0, (int) ceil($percentile * $count) - 1),
    );

    return [
        'median_ms' => round($values[$rank(0.50)], 6),
        'p95_ms' => round($values[$rank(0.95)], 6),
        'min_ms' => round($values[0], 6),
        'max_ms' => round($values[$count - 1], 6),
        'raw_ms' => $raw,
    ];
}

/**
 * @return list<float>
 */
function sample(int $warmups, int $iterations, Closure $operation): array
{
    for ($iteration = 0; $iteration < $warmups; ++$iteration) {
        $operation();
    }

    $samples = [];
    for ($iteration = 0; $iteration < $iterations; ++$iteration) {
        $startedAt = hrtime(true);
        $operation();
        $samples[] = (hrtime(true) - $startedAt) / 1_000_000;
    }

    return $samples;
}

/**
 * @param list<Future> $tasks
 */
function awaitHandlers(array $tasks): void
{
    [$errors] = awaitAll($tasks);
    if ($errors !== []) {
        throw new RuntimeException(sprintf('Handler fan-out produced %d error(s): %s', count($errors), implode('; ', array_map(static fn (Throwable $error): string => $error->getMessage(), $errors))));
    }
}

$counts = [1, 100, 1000];
$cpuWarmups = 5;
$cpuSamples = 30;
$delayWarmups = 5;
$delaySamples = 25;
$delaySeconds = 0.001;
$update = UpdateFactory::make();

$results = [
    'runtime' => [
        'binary' => basename(PHP_BINARY),
        'version' => PHP_VERSION,
        'zts' => PHP_ZTS,
        'debug' => PHP_DEBUG,
        'true_async' => phpversion('true_async'),
        'amp' => InstalledVersions::getPrettyVersion('amphp/amp'),
        'revolt' => InstalledVersions::getPrettyVersion('revolt/event-loop'),
        'loaded_ini' => php_ini_loaded_file() ?: false,
        'opcache_cli' => ini_get('opcache.enable_cli'),
        'jit' => ini_get('opcache.jit'),
    ],
    'parameters' => [
        'counts' => $counts,
        'cpu_warmups' => $cpuWarmups,
        'cpu_samples' => $cpuSamples,
        'delay_ms' => 1,
        'delay_warmups' => $delayWarmups,
        'delay_samples' => $delaySamples,
    ],
    'noop_handler_fanout' => [],
    'delay_1ms_handler_fanout' => [],
];

foreach ($counts as $count) {
    $executed = 0;
    $bot = new TelegramBot(token: 'benchmark-token', logger: new NullLogger());

    for ($handler = 0; $handler < $count; ++$handler) {
        $bot->addHandler(static function () use (&$executed): void {
            ++$executed;
        });
    }

    $before = $executed;
    $samples = sample(
        $cpuWarmups,
        $cpuSamples,
        static fn () => awaitHandlers($bot->handleUpdate($update)),
    );
    $actual = $executed - $before;
    $expected = $count * ($cpuWarmups + $cpuSamples);
    if ($actual !== $expected) {
        throw new RuntimeException("No-op fan-out lost handlers: expected {$expected}, got {$actual}");
    }

    $results['noop_handler_fanout'][(string) $count] = summarize($samples);
}

foreach ($counts as $count) {
    $executed = 0;
    $bot = new TelegramBot(token: 'benchmark-token', logger: new NullLogger());

    for ($handler = 0; $handler < $count; ++$handler) {
        $bot->addHandler(static function () use (&$executed, $delaySeconds): void {
            delay($delaySeconds);
            ++$executed;
        });
    }

    $before = $executed;
    $samples = sample(
        $delayWarmups,
        $delaySamples,
        static fn () => awaitHandlers($bot->handleUpdate($update)),
    );
    $actual = $executed - $before;
    $expected = $count * ($delayWarmups + $delaySamples);
    if ($actual !== $expected) {
        throw new RuntimeException("Delayed fan-out lost handlers: expected {$expected}, got {$actual}");
    }

    $results['delay_1ms_handler_fanout'][(string) $count] = summarize($samples);
}

echo json_encode($results, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
