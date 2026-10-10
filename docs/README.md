# Sumaya Pharma Backend

Phase 1: runtime and engineering foundation; business implementation remains deferred.

- Backend: Laravel 13.
- Architecture: modular monolith with pragmatic hexagonal boundaries.
- Database target: MySQL; local connectivity is verified without running migrations.
- Planned infrastructure: Redis for cache, queues, rate limiting and locks.
- Frontend: a separate Next.js application, outside this repository.

Start with [architecture](architecture/README.md), [module boundaries](architecture/module-boundaries.md), [coding principles](architecture/coding-principles.md), [performance principles](architecture/performance-principles.md), and [architecture decisions](adr/).

The [Phase 0 report](reports/phase-00-foundation-report.md) records initialization.
The [Phase 1 report](reports/phase-01-runtime-foundation-report.md) records runtime setup
and validation. See [API foundation](api/foundation.md) and [CI/CD](deployment/ci-cd.md).
No business features have been implemented.
