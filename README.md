# Atlas Agent CLI

[![Build](https://github.com/atlas-php/agent-cli/actions/workflows/tests.yml/badge.svg)](https://github.com/atlas-php/agent-cli/actions/workflows/tests.yml)
[![coverage](https://codecov.io/github/atlas-php/agent-cli/branch/main/graph/badge.svg)](https://codecov.io/github/atlas-php/agent-cli)
[![License](https://img.shields.io/github/license/atlas-php/agent-cli.svg)](LICENSE)

**Atlas Agent CLI** is a Laravel-ready wrapper around the Codex coding agent CLI. It exposes an Artisan command and service layer that stream Codex output, sanitize ANSI characters, persist JSONL logs, and report a summarized transcript in real time.

## Table of Contents
- [Overview](#overview)
- [Installation](#installation)
- [Usage](#usage)
  - [Artisan Command](#artisan-command)
  - [Service](#service)
- [Local Sandbox](#local-sandbox)
- [Testing](#testing)
- [Contributing](#contributing)
- [License](#license)

## Overview
Agent CLI mirrors the raw `codex` CLI while layering Laravel-native ergonomics: streaming output, ANSI sanitizing, JSON Lines logging, and command/service APIs for both interactive and headless sessions.

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

## Contributing
See the [Contributing Guide](./.github/CONTRIBUTING.md) and [Agents](./AGENTS.md).

## License
MIT — see [LICENSE](./LICENSE).
