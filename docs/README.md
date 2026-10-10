# Sumaya Pharma Backend

Phase 0: foundation and architecture scaffold only.

- Backend: Laravel 13.
- Architecture: modular monolith with pragmatic hexagonal boundaries.
- Database target: MySQL; configuration is deferred.
- Planned infrastructure: Redis for cache, queues, rate limiting and locks.
- Frontend: a separate Next.js application, outside this repository.

Start with [architecture](architecture/README.md), [module boundaries](architecture/module-boundaries.md), [coding principles](architecture/coding-principles.md), [performance principles](architecture/performance-principles.md), and [architecture decisions](adr/).

The [Phase 0 report](reports/phase-00-foundation-report.md) records initialization, validation and the final directory tree. No business features have been implemented.
