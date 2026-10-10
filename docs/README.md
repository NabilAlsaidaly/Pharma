# Sumaya Pharma Backend

Phase 2: customer identity and Google authentication. Other business modules remain deferred.

- Backend: Laravel 13.
- Architecture: modular monolith with pragmatic hexagonal boundaries.
- Database: MySQL; customers migration applied locally, separate MySQL test database.
- Planned infrastructure: Redis for cache, queues, rate limiting and locks.
- Frontend: a separate Next.js application, outside this repository.

Start with [architecture](architecture/README.md), [module boundaries](architecture/module-boundaries.md), [coding principles](architecture/coding-principles.md), [performance principles](architecture/performance-principles.md), and [architecture decisions](adr/).

The [Phase 0 report](reports/phase-00-foundation-report.md) records initialization.
The [Phase 1 report](reports/phase-01-runtime-foundation-report.md) records runtime setup
and validation. See [API foundation](api/foundation.md) and [CI/CD](deployment/ci-cd.md).
See [Identity architecture](architecture/identity-and-authentication.md),
[customer authentication API](api/customer-authentication.md),
[Phase 2 report](reports/phase-02-identity-google-auth-report.md) and ADRs 004, 012–014.
Live Google OAuth is not verified until real OAuth credentials and a consent flow are available.
