# Sumaya Pharma Backend

Laravel 13 backend in **Phase 0: foundation / architecture scaffold**.

Architecture: modular monolith with pragmatic hexagonal boundaries and domain-oriented modules. MySQL is the target database; Redis is planned for cache, queues, rate limiting and locks. Next.js belongs to a separate frontend project.

See [documentation](docs/README.md), [architecture](docs/architecture/README.md) and the [Phase 0 report](docs/reports/phase-00-foundation-report.md).

No custom business features or API endpoints are implemented. Environment setup, database and Redis configuration, UUIDv7, authentication, error contracts, logging, testing foundation and CI are reserved for the next explicitly approved phase.

Composer scripts were disabled during initialization to avoid automatic migrations. Validation uses `php artisan --version`, `php artisan about` and `composer validate --strict`.
