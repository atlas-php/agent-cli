# Atlas Agent CLI

Atlas Agent CLI is a Laravel-ready wrapper around the Codex coding agent CLI. It provides a reusable service plus an Artisan command that streams Codex output, strips ANSI escape characters, records event logs to JSONL files, and displays a summarized transcript in real time.

## Installation

```bash
composer require atlas-php/agent-cli
```

The package registers itself automatically through Laravel's package auto-discovery. When running in Lumen or if auto-discovery is disabled, register the provider manually:

```php
// bootstrap/app.php or config/app.php
Atlas\Agent\Providers\AgentCliServiceProvider::class,
```

## Usage

### Artisan Command

The command mirrors the raw `codex` CLI but adds streaming and JSON logging:

```bash
php artisan codex:session -- tasks:list --plan
```

Options:

* `args*` – the exact arguments to forward to the Codex CLI.
* `--interactive` – run Codex directly attached to your terminal (no JSON logging).

Upon completion the command prints:

* Codex session/thread identifier.
* Absolute path to the JSONL log file stored at `storage/app/codex_sessions`.
* Exit code emitted by the Codex CLI process.

### Service

Consume the `Atlas\Agent\Services\CodexCliSessionService` directly when you need custom orchestration:

```php
use Atlas\Agent\Services\CodexCliSessionService;

$result = app(CodexCliSessionService::class)->startSession([
    'tasks:list',
    '--plan',
]);

// $result = [
//     'session_id' => 'thread_abc123',
//     'json_file_path' => storage_path('app/codex_sessions/thread_abc123.jsonl'),
//     'exit_code' => 0,
// ];
```

The service handles both interactive and headless runs, automatically sanitizes ANSI escape sequences, streams events to STDOUT/STDERR, and records every Codex JSON event to a JSON Lines log.

## Local Sandbox

The repository ships with a minimal Laravel sandbox so you can run the `codex:session` command without installing the package into a real application. The sandbox has no database and only registers the Agent CLI service provider.

```bash
php sandbox/artisan codex:session -- tasks:list --plan
```

JSON transcripts are stored inside `sandbox/storage/app/codex_sessions`. Pass `--interactive` to talk directly to Codex without log files.

## Testing

Run Pint, PHPUnit, and Larastan from the package root:

```bash
composer lint
composer test
composer analyse
```

## License

The Atlas Agent CLI package is open-sourced software licensed under the [MIT license](LICENSE).
