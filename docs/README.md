# Sumaya Pharma Backend

Phase 6B adds the read-only custom Arabic Owner dashboard: [architecture](architecture/owner-dashboard.md), [web queries/routes](api/owner-dashboard-web.md), [privacy/access](security/owner-dashboard-access.md), [ADR-021](adr/ADR-021-custom-owner-dashboard.md) and [report](reports/phase-06b-owner-dashboard-report.md). Real aggregates/search/inspection only; visitor analytics and business mutations remain deferred.

Phase 6A adds the custom Arabic Owner identity foundation: [architecture](architecture/owner-authentication.md), [secure access operations](security/owner-access.md), [web interface](api/owner-web-interface.md), [ADR-020](adr/ADR-020-owner-authentication-foundation.md) and [evidence](reports/phase-06a-owner-authentication-report.md). Customer Google identity remains separate. Schema rollout and real owner provisioning are deferred; business management is not implemented.

Phase 5A.2 adds [preliminary checkout requests](api/preliminary-checkout-requests.md), [architecture](architecture/preliminary-checkout-requests.md), [ADR-019](adr/ADR-019-preliminary-checkout-requests.md) and [implementation evidence](reports/phase-05a2-preliminary-checkout-requests-report.md). Requests remain submitted facts for owner review; no confirmed order, payment or reservation is introduced. Development schema rollout is deferred.

Phase 4: customer Identity, read-only Catalog, persistent Favorites and Cart foundation. Other business modules remain deferred.

Phase 5A adds only [non-binding Checkout evaluation](api/checkout-evaluation.md), with [architecture](architecture/checkout-evaluation.md), [ADR-018](adr/ADR-018-checkout-evaluation-foundation.md) and [implementation evidence](reports/phase-05a-checkout-evaluation-foundation-report.md). The preserved [Phase 5 design gate and owner clarifications](reports/phase-05-checkout-payments-design-gate.md) do not authorize the remaining commercial workflow.

- Backend: Laravel 13.
- Architecture: modular monolith with pragmatic hexagonal boundaries.
- Database: MySQL; customers migration applied locally, separate MySQL test database.
- Planned infrastructure: Redis for cache, queues, rate limiting and locks.
- Customer frontend: a separate Next.js application, outside this repository. Owner identity interface: custom Laravel Blade in this repository.

Start with [architecture](architecture/README.md), [module boundaries](architecture/module-boundaries.md), [coding principles](architecture/coding-principles.md), [performance principles](architecture/performance-principles.md), and [architecture decisions](adr/).

The [Phase 0 report](reports/phase-00-foundation-report.md) records initialization.
The [Phase 1 report](reports/phase-01-runtime-foundation-report.md) records runtime setup
and validation. See [API foundation](api/foundation.md) and [CI/CD](deployment/ci-cd.md).
See [Identity architecture](architecture/identity-and-authentication.md),
[customer authentication API](api/customer-authentication.md),
[Phase 2 report](reports/phase-02-identity-google-auth-report.md) and ADRs 004, 012–014.
The owner reports successful live Google login after Phase 2; that is manual owner
evidence, not independently repeated automated OAuth verification.
See [Catalog architecture](architecture/catalog.md), [API](api/catalog.md),
[database schema](database/catalog-schema.md), [query review](architecture/catalog-query-design.md),
[approved design gate](reports/phase-03-catalog-design-gate.md), ADR-016 and
[PAY-001](architecture/payment-before-order-rule.md).

The [Phase 3 implementation report](reports/phase-03-catalog-foundation-report.md) records schema, API, tests, query measurements, CI and customer preservation evidence.

The [Phase 4 design gate](reports/phase-04-favorites-cart-design-gate.md) is owner-approved through phase4.1.md; ADR-017 records D1-D6. See [Favorites architecture](architecture/favorites.md), [Cart architecture](architecture/cart.md), [Catalog integration](architecture/cart-catalog-integration.md), [Checkout compatibility](architecture/cart-checkout-compatibility.md), [Favorites API](api/favorites.md), [Cart API](api/cart.md), [schema](database/favorites-cart-schema.md) and [Phase 4 implementation report](reports/phase-04-favorites-cart-foundation-report.md).

Phase 6C: [Catalog administration](architecture/catalog-administration.md), [web forms](api/owner-catalog-web.md), [schema/audit](database/catalog-soft-delete-and-audit.md), [security](security/catalog-administration.md), [ADR-022](adr/ADR-022-catalog-administration.md), [write design gate](reports/phase-06c-catalog-write-design-gate.md), [implementation report](reports/phase-06c-catalog-administration-report.md).

Phase 6D: [Request management](architecture/preliminary-request-management.md), [Owner web contract](api/owner-preliminary-request-web.md), [schema](database/preliminary-request-review-schema.md), [privacy](security/preliminary-request-management.md), [ADR023](adr/ADR-023-preliminary-request-review.md), [approved workflow gate](reports/phase-06d-review-workflow-design-gate.md), [report](reports/phase-06d-preliminary-request-management-report.md).
