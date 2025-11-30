# Atlas Agent CLI

[![Build](https://github.com/atlas-php/agent-cli/actions/workflows/tests.yml/badge.svg)](https://github.com/atlas-php/agent-cli/actions/workflows/tests.yml)
[![coverage](https://codecov.io/github/atlas-php/agent-cli/branch/main/graph/badge.svg)](https://codecov.io/github/atlas-php/agent-cli)
[![License](https://img.shields.io/github/license/atlas-php/agent-cli.svg)](LICENSE)

**Atlas Agent CLI** (coding agent) is a streamlined Laravel wrapper around the Codex CLI. It provides a unified command and service layer for running Codex tasks with clean streaming output, ANSI sanitization, structured JSONL logging, and optional headless or interactive execution.

## Table of Contents

* [Overview](#overview)
* [Features](#features)
* [Installation](#installation)
* [Usage](#usage)
    * [Artisan Command](#artisan-command)
    * [Service Layer](#service-layer)
* [Configuration](#configuration)
* [Local Sandbox](#local-sandbox)
* [Testing](#testing)
* [Contributing](#contributing)
* [License](#license)

## Overview

Atlas Agent CLI mirrors the native `codex` CLI while adding Laravel-native ergonomics. It standardizes how Codex sessions are started, streamed, logged, and resumed—making it easier to integrate code-generation workflows into Laravel applications or automated pipelines.

## Features

* Clean stream handling with automatic ANSI sanitization.
* JSON Lines transcript logging for every run.
* Transcript parsing helpers for inspecting sessions (todos, turns, full history).
* Resume support for Codex threads.
* Task and instruction templating.
* Per-run model, reasoning, approval, and workspace overrides.
* Laravel-ready command and service APIs.

## Installation

```bash
composer require atlas-php/agent-cli
```

The package auto-discovers its service provider. If necessary, register it manually:

```php
// bootstrap/app.php or config/app.php
Atlas\Agent\Providers\AgentCliServiceProvider::class,
```

## Usage

### Artisan Command

Run Codex through Laravel with streaming output and automatic logging:

```bash
php artisan agent:codex -- tasks:list --plan
```

Key options:

* `args*` – forwarded directly to the Codex CLI.
* `--interactive` – attach Codex to your terminal (no JSON logging).
* `--model=` – override the Codex model.
* `--reasoning=` – specify the reasoning effort.
* `--approval=` – override approval policy.
* `--instructions=` – prepend system instructions.
* `--template-task=` / `--template-instructions=` – override templates.
* `--meta=` – attach metadata recorded in the transcript.
* `--resume=` – resume an existing Codex thread.
* `--workspace=` – override the execution directory.

After completion, the command prints:

* Codex session/thread ID.
* Path to the JSONL transcript.
* Exit code from the Codex process.

### Service Layer

Use the service for programmatic orchestration:

```php
use Atlas\Agent\Services\CodexCliSessionService;

$result = app(CodexCliSessionService::class)->startSession([
    'tasks:list', '--plan'
]);
```

The service:

* Streams output to STDOUT/STDERR.
* Logs all events as JSONL.
* Sanitizes ANSI sequences.
* Supports workspace and template overrides.
* Records synthetic `thread.request`, `thread.resumed`, and `thread.terminated` events.

Parse a finished session transcript into actionable structures:

```php
use Atlas\Agent\Services\SessionTranscripts\SessionTranscriptService;

/** @var SessionTranscriptService $transcripts */
$transcripts = app(SessionTranscriptService::class);

$events = $transcripts->fullTranscript('codex', 'thread-123');
$todos = $transcripts->todoList('codex', 'thread-123');
$turns = $transcripts->turns('codex', 'thread-123');
$usage = $transcripts->usageTotals('codex', 'thread-123'); // sums input/output/cached across turns
```

## Configuration

Publish the config to customize storage paths, Codex defaults, and templates:

```bash
php artisan vendor:publish --tag=atlas-agent-cli-config
```

Configuration options include:

* `sessions.path` – base directory for JSONL logs.
* `workspace.path` – default Codex working directory.
* `providers.codex.*` – default model, reasoning, approval values.
* `template.*` – instruction and task templates.

## Local Sandbox

The package includes a minimal Laravel sandbox for local experimentation:

```bash
php sandbox/artisan agent:codex -- tasks:list --plan
```

Logs are stored at `sandbox/storage/app/sessions/codex`.

## Testing

```bash
composer lint
composer test
composer analyse
```

## Contributing

See the [Contributing Guide](./.github/CONTRIBUTING.md) and [Agents](./AGENTS.md).

## License

MIT — see [LICENSE](./LICENSE).
