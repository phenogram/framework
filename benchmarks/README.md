# Handler fan-out benchmark

`handler-fanout.php` measures the real `TelegramBot::handleUpdate()` path. It
creates 1, 100, or 1,000 matching handlers and measures two workloads:

- a no-op handler, which emphasizes routing and coroutine scheduling;
- a handler that calls `Async\delay(1)`, which emphasizes timer fan-out.

Bot construction, route registration, and update construction are outside the
timed section. Each workload has five warm-ups. No-op workloads have 30 measured
iterations; delayed workloads have 25. Every expected handler invocation is
validated. Median and p95 use nearest-rank percentiles.

Run one native True Async replica:

```bash
composer benchmark
```

The comparison used three fresh processes for every runtime with INI loading
disabled and CLI opcache disabled:

```bash
/path/to/php -n -d opcache.enable_cli=0 benchmarks/handler-fanout.php
```

The native harness belongs to this feature checkout. The retained
`amp-handler-fanout.php` harness targets the Amp/Revolt implementation at
upstream commit `10d350ed55cc837fac644443bf5b09de815fc5fa`; copy it into a
detached worktree at that commit rather than running it in this checkout.

## Reproduce all three runtimes

Run these commands from the feature checkout after building the isolated True
Async runtime described by the workspace README. They install the pinned base
dependencies in a detached worktree, verify both lockfiles, and write the three
fresh-process replicas to a new temporary output directory.

```bash
set -euo pipefail

FEATURE_REPO="$(git rev-parse --show-toplevel)"
WORKSPACE_ROOT="$(dirname "$FEATURE_REPO")"
AMP_BASE="10d350ed55cc837fac644443bf5b09de815fc5fa"
AMP_TEMP="$(mktemp -d "${TMPDIR:-/tmp}/phenogram-amp-benchmark.XXXXXX")"
AMP_WORKTREE="$AMP_TEMP/worktree"
OUTPUT_DIR="$(mktemp -d "${TMPDIR:-/tmp}/phenogram-benchmark-output.XXXXXX")"
STOCK_PHP="/opt/homebrew/bin/php"
TRUE_ASYNC_PHP="$WORKSPACE_ROOT/.local/php/bin/php"
COMPOSER="$WORKSPACE_ROOT/.local/php/bin/composer"

cleanup() {
    git -C "$FEATURE_REPO" worktree remove --force "$AMP_WORKTREE" \
        >/dev/null 2>&1 || true
    rmdir "$AMP_TEMP" 2>/dev/null || true
}
trap cleanup EXIT

git -C "$FEATURE_REPO" worktree add --detach "$AMP_WORKTREE" "$AMP_BASE"
mkdir -p "$AMP_WORKTREE/benchmarks"
cp "$FEATURE_REPO/benchmarks/amp-handler-fanout.php" "$AMP_WORKTREE/benchmarks/"

(
    cd "$AMP_WORKTREE"
    test "$(shasum -a 256 composer.lock | awk '{print $1}')" = \
        "23af435aa01c9d8f34566a749fa6a4bbdc87052c6c01c8f7cecb13bd329204e1"
    "$STOCK_PHP" "$COMPOSER" install --no-interaction --prefer-dist
)

(
    cd "$FEATURE_REPO"
    test "$(shasum -a 256 composer.lock | awk '{print $1}')" = \
        "f4b9e07401dddef37477f81d3a3e353da8f5ae56148dbcf5286422bed5117dec"
    "$TRUE_ASYNC_PHP" "$COMPOSER" install --no-interaction --prefer-dist
)

for replica in 1 2 3; do
    "$STOCK_PHP" -n -d opcache.enable_cli=0 \
        "$AMP_WORKTREE/benchmarks/amp-handler-fanout.php" \
        > "$OUTPUT_DIR/amp-stock-$replica.json"
    "$TRUE_ASYNC_PHP" -n -d opcache.enable_cli=0 \
        "$AMP_WORKTREE/benchmarks/amp-handler-fanout.php" \
        > "$OUTPUT_DIR/amp-true-async-$replica.json"
    "$TRUE_ASYNC_PHP" -n -d opcache.enable_cli=0 \
        "$FEATURE_REPO/benchmarks/handler-fanout.php" \
        > "$OUTPUT_DIR/native-true-async-$replica.json"
done

cleanup
trap - EXIT
printf 'Raw benchmark JSON: %s\n' "$OUTPUT_DIR"
```

The recorded environment was:

- Mac model `Mac14,6`, Apple M2 Max, 12 CPU cores, 96 GB memory;
- macOS 27.0 build `26A5388g` (Darwin 27);
- Homebrew PHP 8.4.7 release/NTS for the stock runtime;
- True Async PHP 8.6.0-dev debug/ZTS with `true_async` 0.8.2;
- `php-src` commit `d76e04bb803c790faaee168d2ce23d93bcf88abd`;
- `php-async` commit `454791a3b71515deb8801b4b05ef9aa97117d857`;
- libuv 1.52.1;
- Amp framework commit `10d350ed55cc837fac644443bf5b09de815fc5fa`;
- native implementation and recorded-result commit
  `f6ff19384d47289c452e3e10cda4f1ab5e01ef9c`;
- Amp lockfile SHA-256
  `23af435aa01c9d8f34566a749fa6a4bbdc87052c6c01c8f7cecb13bd329204e1`;
- native feature lockfile SHA-256
  `f4b9e07401dddef37477f81d3a3e353da8f5ae56148dbcf5286422bed5117dec`.

## Results

Measured on 2026-07-29 on Apple Silicon. Values are the median of three
independent process medians, in milliseconds.

| Workload | Handlers | Amp, stock PHP 8.4 | Amp, True Async PHP 8.6 | Native True Async | Native vs stock | Native vs Amp on same PHP |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| No-op | 1 | 0.009 | 0.052 | 0.007 | 27% faster | 7.9× faster |
| No-op | 100 | 0.426 | 2.611 | 0.363 | 15% faster | 7.2× faster |
| No-op | 1,000 | 5.131 | 27.319 | 4.898 | 5% faster | 5.6× faster |
| Delay 1 ms | 1 | 1.295 | 1.271 | 1.168 | 10% faster | 1.1× faster |
| Delay 1 ms | 100 | 2.627 | 8.064 | 2.116 | 19% faster | 3.8× faster |
| Delay 1 ms | 1,000 | 23.243 | 84.979 | 12.967 | 44% faster | 6.6× faster |

The strongest like-for-like signal is the comparison between Amp and native
True Async on the same PHP 8.6 debug/ZTS binary. The stock comparison is useful
but not perfectly controlled: stock PHP is 8.4.7 release/NTS, while the True
Async runtime is PHP 8.6.0-dev debug/ZTS.

The raw replica medians and p95 values are in
`results/2026-07-29-handler-fanout.csv`.
