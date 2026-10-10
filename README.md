# Sumaya Pharma Backend

Laravel 13 backend with **Phase 1: runtime and engineering foundation**.

Architecture: modular monolith with pragmatic hexagonal boundaries and domain-oriented modules. MySQL is the target database; Redis is planned for cache, queues, rate limiting and locks. Next.js belongs to a separate frontend project.

See [documentation](docs/README.md), [architecture](docs/architecture/README.md),
the [Phase 0 report](docs/reports/phase-00-foundation-report.md),
the [Phase 1 report](docs/reports/phase-01-runtime-foundation-report.md),
[API foundation](docs/api/foundation.md) and [CI/CD](docs/deployment/ci-cd.md).

No business features or business API endpoints are implemented. The runtime foundation
includes MySQL configuration, server-generated UUIDv7 request IDs, safe API errors,
log context, foundation tests, Larastan and GitHub Actions CI. Redis remains the approved
cache/queue/rate-limit/lock infrastructure; enable it only after verifying connectivity.
Identity, Google authentication and both management areas remain deferred.

Local development uses Laravel Herd. Copy `.env.example` to `.env`, supply verified
MySQL credentials, and run `php artisan key:generate`. Never commit `.env`.
No migrations have been run. Do not use the default `composer setup` script for this phase.
See CI/CD documentation for the local quality commands and the future deployment flow.
