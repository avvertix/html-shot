# Contributing to html-shot

Thank you for your interest in contributing! This document covers how to get set up, how to submit
changes, and the conventions we follow.

## Table of Contents

- [Prerequisites](#prerequisites)
- [Repository Structure](#repository-structure)
- [Development Setup](#development-setup)
- [How to Contribute](#how-to-contribute)
- [Development Guidelines](#development-guidelines)
- [Commit Message Guidelines](#commit-message-guidelines)

---

## Prerequisites

- PHP 8.1+ with `ext-ffi` enabled (`ffi.enable = true` in `php.ini`)
- Rust toolchain (latest stable)
- Composer

---

## Repository Structure

```
html-shot/
├── src/                    # PHP source code
│   └── HtmlShot/           # Namespace root
├── rust/                   # Rust cdylib
│   └── src/
│       ├── ffi/            # FFI interface layer
│       └── internal/       # HTML parsing and helpers
├── tests/                  # PHPUnit tests
├── examples/               # Runnable PHP examples
├── lib/                    # Compiled native libraries (platform-specific, gitignored)
└── include/                # Generated C header (html_shot.h)
```

---

## Development Setup

```bash
# 1. Fork and clone
git clone https://github.com/avvertix/html-shot.git
cd html-shot

# 2. Install PHP dependencies
composer install

# 3. Build the Rust library
cd rust && cargo build --release && cd ..

# 4. Run tests to verify setup
composer test

# 5. Run static analysis
composer lint
```

---

## How to Contribute

### Reporting Bugs

Before opening an issue:

1. Check if it has already been reported.
2. Try to reproduce with the latest version.

Please include:

- Clear description and steps to reproduce
- Expected vs actual behaviour
- PHP version (`php -v`), platform, and html-shot version
- Error messages or stack traces

### Suggesting Features

Open an issue explaining the use case and why it would be valuable. If you have an implementation
idea, sketch it out — that makes the discussion faster.

### Pull Requests

1. Create a branch from `main`:
   ```bash
   git checkout -b feature/my-feature
   # or
   git checkout -b fix/bug-description
   ```

2. Make your changes following the guidelines below.

3. Verify everything passes:
   ```bash
   composer test
   composer lint
   ```

4. Open a PR with a clear title, description, and reference to any related issues.

---

## Development Guidelines

### PHP

- Follow PSR-12 coding standards
- Use type hints for all parameters and return types
- Keep methods focused (single responsibility)
- 4-space indentation, 120-character soft line limit

### Rust

- Follow standard Rust idioms
- Add `# Safety` documentation for every `unsafe` block
- Document all `pub extern "C"` FFI functions
- Add tests in `rust/tests/` for new FFI functions
- Prefer safe Rust; reach for `unsafe` only at the C boundary

### Tests

All PRs must include tests.

```bash
composer test          # Run all tests
./vendor/bin/phpunit --filter testName   # Single test
```

---

## Commit Message Guidelines

We use [Conventional Commits](https://www.conventionalcommits.org/):

```
<type>(<scope>): <subject>

[optional body]

[optional footer]
```

**Types:** `feat` · `fix` · `docs` · `style` · `refactor` · `perf` · `test` · `chore`

**Scopes:** `php` · `rust` · `ffi` · `docs` · `tests`

Examples:

```
feat(php): add stylesheet support to Renderer
fix(rust): prevent panic on empty HTML input
docs: add FFI setup instructions for Windows
test(php): cover devicePixelRatio scaling
```

## Questions?

- Open an [Issue](https://github.com/avvertix/html-shot/issues) for questions
- Join [Discussions](https://github.com/avvertix/html-shot/discussions) for ideas
- Check [Documentation](./docs) for usage help

Thank you for contributing to NDArray PHP! 🎉

