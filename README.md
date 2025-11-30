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
* `--model=` – override the Codex model for the current run.
* `--instructions=` – prepend additional system instructions ahead of the user task; stored in the JSON log as a `thread.request` event.
* `--template-task=` / `--template-instructions=` – override the configured task/instructions templates for a single run when you want to reshape the payload without editing config.
* `--meta=` – supply a JSON object of metadata (IDs, tags, etc.) that is recorded alongside the synthetic thread lifecycle log entry.
* `--resume=` – continue an existing Codex thread; the wrapper preserves the thread identifier, writes a `thread.resumed` log entry for the new task, and still forwards only your task arguments to Codex.
* `--workspace=` – override the working directory Codex uses for this run (falls back to the configured workspace when omitted). The command executes Codex from this path and logs it inside the `workspace` JSONL entry alongside the provider name.

Upon completion the command prints:

* Codex session/thread identifier.
* Absolute path to the JSONL log file stored at the configured sessions directory.
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
//     'json_file_path' => config('atlas-agent-cli.sessions.path').'/thread_abc123.jsonl',
//     'exit_code' => 0,
// ];
```

The service handles both interactive and headless runs, automatically sanitizes ANSI escape sequences, streams events to STDOUT/STDERR, and records every Codex JSON event to a JSON Lines log.

When invoking the service directly you may pass a workspace override and optional task/instruction templates as the final arguments (`startSession($args, $interactive, ..., $workspaceOverride, $templates)`), mirroring the `--workspace` and `--template-*` console options.

Each headless log now begins with a `workspace` entry that captures the provider (`codex`), the Codex workspace path, the platform path, the JSONL log directory, and the effective model for the run. This is followed by the synthetic `thread.request` (or `thread.resumed`) entry summarizing system instructions, the triggering task, and any metadata supplied via `--meta`, so downstream tooling can reconstruct the full prompt context. When resuming a thread via `--resume`, the log still records a `thread.resumed` entry containing the latest user task (plus metadata) without re-stating the original instructions.

## Configuration

Publish the configuration file to customize session storage and Codex defaults:

```bash
php artisan vendor:publish --tag=atlas-agent-cli-config

php sandbox/artisan codex:session --workspace="/Users/marois/Development/Atlasphp/Repo/agent-cli" "say hello"
```

The `config/atlas-agent-cli.php` file exposes:

* `sessions.path` – base directory for JSONL transcripts. Atlas Agent CLI stores Codex logs inside `<path>/codex`. Defaults to `storage/app/sessions`.
* `workspace.path` – the working directory Codex should execute within. Defaults to your Laravel application's base path but can be pointed at any detached workspace (override via `ATLAS_AGENT_CLI_WORKSPACE_PATH` or the `--workspace` command option for per-run overrides).
* `model.<provider>` – default model applied per provider unless overridden with `--model`. Codex defaults to `model.codex = gpt-5.1-codex-max` (set via `ATLAS_AGENT_CLI_MODEL_CODEX`). Available models are listed at [OpenAI Codex models](https://developers.openai.com/codex/models).
* `template.task` / `template.instructions` – string templates (defaults to `Task: {TASK}` and `Instructions: {INSTRUCTIONS}`) that shape how the task and instructions are combined before forwarding them to Codex. Workspace logs record the templates, and thread request/resume entries include the rendered messages (instructions first, then task) so you can see exactly what Codex receives.

## Local Sandbox

The repository ships with a minimal Laravel sandbox so you can run the `codex:session` command without installing the package into a real application. The sandbox has no database and only registers the Agent CLI service provider.

```bash
php sandbox/artisan codex:session -- tasks:list --plan
```

JSON transcripts are stored inside `sandbox/storage/app/sessions/codex`. Pass `--interactive` to talk directly to Codex without log files.

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
