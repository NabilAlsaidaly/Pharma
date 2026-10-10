# Sumaya Pharma Backend

Laravel 13 backend with **Phase 3: read-only Catalog foundation**.

Architecture: modular monolith with pragmatic hexagonal boundaries and domain-oriented modules. MySQL is the target database; Redis is planned for cache, queues, rate limiting and locks. Next.js belongs to a separate frontend project.

See [documentation](docs/README.md), [architecture](docs/architecture/README.md),
the [Phase 0 report](docs/reports/phase-00-foundation-report.md),
the [Phase 1 report](docs/reports/phase-01-runtime-foundation-report.md),
[API foundation](docs/api/foundation.md) and [CI/CD](docs/deployment/ci-cd.md).

Identity implements Google-only customer login, UUIDv7 customer records, Sanctum session
authentication, CSRF, exact-origin CORS and account suspension. See
[Identity architecture](docs/architecture/identity-and-authentication.md),
[customer authentication API](docs/api/customer-authentication.md) and the
[Phase 2 report](docs/reports/phase-02-identity-google-auth-report.md).
Redis remains planned; verified local fallbacks are file cache/session and sync queue.
Catalog now includes Brands, Products, Variants, Image metadata and Concerns with
eight public read endpoints, optional decimal prices and bounded filtering/pagination.
See [Catalog API](docs/api/catalog.md), [architecture](docs/architecture/catalog.md)
and [schema](docs/database/catalog-schema.md). Both management areas and purchasing remain deferred.

Local development uses Laravel Herd. Copy `.env.example` to `.env`, supply verified
MySQL credentials, and run `php artisan key:generate`. Never commit `.env`.
Use the setup instructions in the Identity architecture document, including separate
MySQL test credentials. Apply forward migrations with `php artisan migrate`; never
refresh the development database. Google OAuth requires your own client credentials.
Do not use the default `composer setup` script. See CI/CD documentation for quality checks.
