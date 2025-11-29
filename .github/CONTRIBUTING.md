# Contributing

This document defines the minimum checks, commit format, and versioning rules required for contributions across all Atlas PHP packages.

All coding standards, architecture rules, and naming conventions are defined in **[AGENTS.md](../AGENTS.md)**.

## Table of Contents
- [Validation Requirements](#validation-requirements)
- [Commit Style](#commit-style)
- [Version Control](#version-control)

## Validation Requirements

All contributions must pass **every** validation check before submission.

Run:

```bash
composer lint
composer analyse
composer test
```

### Definition of Done
- **Pint:** no pending diffs after running.
- **PHPStan:** level 8 with 0 errors.
- **Tests:** all tests pass deterministically (no retries or flakiness).

If any check fails, the contribution is **not complete**.

## Commit Style

All commits must follow **Conventional Commits 1.0.0**:

```
<type>[optional scope]: <short description>

[optional body]

[optional footer(s)]
```

### Common Types
- `feat` — new feature
- `fix` — bug fix
- `docs` — documentation only
- `style` — formatting, no behavioral changes
- `refactor` — behavior-preserving code change
- `perf` — performance improvement
- `test` — add/update tests
- `chore` — tooling/build changes

Keep commits focused and descriptive.

## Version Control

This project uses **Semantic Versioning (SemVer)** in the format:

```
MAJOR.MINOR.PATCH
```

### Rules
- **MAJOR:** breaking changes, removed functionality, or incompatible API adjustments.
- **MINOR:** new features, enhancements, or additions that do not break existing behavior.
- **PATCH:** bug fixes, internal improvements, or non-breaking changes.
