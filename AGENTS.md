# Agents

This guide defines the conventions for contributors working on the **Atlas Agent CLI** Laravel package. Follow these rules exactly so that the package remains predictable, composable, and ready for installation in any Laravel application.

> For repository level contribution rules (testing, commits, validation), review the workspace root CONTRIBUTING notes shared with your assignment.

---

## Purpose

Agent CLI provides reusable services and Artisan commands that wrap external coding agents (Codex CLI). All code here must remain **package-scoped**, application-agnostic, and free of assumptions about consuming projects.

Every Agent must treat the provided Product Requirement Documents (PRDs) as the single source of truth. When a PRD defines naming, behavior, or workflows you must implement that exact behavior without deviation.

---

## Core Principles

1. Enforce **PSR-12**, **Laravel Pint**, and **strict_types** across all PHP files.
2. Target **PHP 8.2+** and **Laravel 10/11/12** compatibility.
3. Keep implementations deterministic, stateless, and free of application-specific state sharing.
4. Never invent new terminology or behaviors beyond what the PRD or task specifies.
5. **Every class (including tests) must include a top-level PHPDoc block** summarizing its purpose and usage so consumers immediately understand the intent.
6. All services must expose explicit APIs and avoid hidden magic.

---

## Structure

```
agent-cli/
├── AGENTS.md
├── composer.json
├── src/
│   ├── Console/Commands
│   ├── Providers/
│   ├── Services/
│   ├── Contracts/ (if needed)
│   └── Support/
├── config/ (optional)
├── database/ (optional)
├── tests/
│   ├── Feature/
│   ├── Support/
│   └── TestCase.php
├── README.md
├── CHANGELOG.md
└── LICENSE
```

Group services under `src/Services/<Domain>` when a feature grows larger than a single file. Keep console commands inside `src/Console/Commands` and bind them through the service provider.

---

## Service Provider Rules

* Provide a dedicated `AgentCliServiceProvider` for bindings, command registration, and configuration publishing.
* Auto-discovery must be enabled through `composer.json`.
* Never place business logic in the provider—use it strictly for wiring and configuration hooks.

---

## Testing Requirements

1. Use **PHPUnit** with **Orchestra Testbench** for framework integration.
2. Provide feature coverage for both successful and failure paths.
3. Prefer stubs/fakes over brittle runtime assumptions when interacting with external CLIs or processes.
4. Keep assertions focused on observable behavior (output, files, responses).

Before you submit a change you must:

1. Run `composer lint` (Laravel Pint).
2. Run `composer test` (PHPUnit).
3. Run `composer analyse` (Larastan/phpstan).
4. Ensure all new files are autoloadable and documented.

---

## Documentation

* Maintain `README.md` with installation, configuration, and usage instructions.
* Update `CHANGELOG.md` whenever behavior changes.
* Keep doc blocks current with actual behavior.

---

## Enforcement

Any contribution that violates this guide, the PRD, or the user task will be rejected. When unsure, stop and request clarification before writing code.
