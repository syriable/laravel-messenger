# Contributing

Thanks for contributing to Laravel Messenger.

## Running the package test suite

```bash
composer install
composer test        # Pest
composer analyse     # PHPStan (level 5 + Larastan)
composer format      # Pint
```

The Pest suite runs against an in-memory SQLite database via Orchestra
Testbench and includes cross-model (User ↔ Agent) integration coverage and an
in-process concurrency stress test.

## Standalone QA harnesses

The `scripts/` directory holds optional harnesses that exercise the package the
way a **consuming application** would — booting a real Laravel app and, for the
stress harness, sharing a file-backed SQLite database across multiple OS
processes. They are not part of the default CI run; use them to validate
behaviour beyond what the in-process Pest suite can show.

### Consuming-app integration smoke test

```bash
php scripts/integration-qa.php
```

Boots a real app, installs the package, and checks cross-model morph messaging,
spam/unspam, the broadcast contract, attachments and the prune command. Prints
`PASS`/`FAIL` per check and exits non-zero on any failure.

### Multi-process first-message stress

```bash
php scripts/parallel-stress.php [workers]   # default 12
```

Spawns N separate PHP processes that all send the *first* message between the
same participant pair at once, against one shared SQLite file (WAL +
`busy_timeout`). It verifies that exactly one conversation is created and no
message is lost under real OS-level parallelism.

> **SQLite note:** SQLite serialises writers, so under heavy contention a write
> can return `database is locked`. The worker retries with backoff (as a real
> SQLite-backed app would). For production-like contention without that
> limitation, point the `qa` connection in `scripts/bootstrap.php` at MySQL or
> PostgreSQL.

## Pull requests

- Keep PRs focused and include tests.
- Ensure `composer test`, `composer analyse` and `composer format` all pass.
- Explain architectural and performance considerations in the description.
