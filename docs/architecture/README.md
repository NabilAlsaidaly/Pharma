# Architecture

Phase 6B: [ManagementDashboard](owner-dashboard.md) is a protected read-side presentation module over current business data, with custom Arabic Blade navigation, bounded search/statistics and read-only lists/details.

Phase 6A: [Owner authentication](owner-authentication.md) implements ManagementIdentity and a custom Blade personal account interface separately from Customer Identity and System Console.

Phase 5A.2: [preliminary checkout request persistence](preliminary-checkout-requests.md) extends Orders with owned, idempotent submission and immutable history; see [ADR-019](../adr/ADR-019-preliminary-checkout-requests.md). Payment, confirmation and reservation remain deferred.

## Style and scope

Sumaya Pharma is an API-first modular monolith: one Laravel backend deployment with explicit domain-oriented module boundaries. Next.js is a separate frontend project. MySQL is the target database; Redis is planned infrastructure. Phase 0 creates directories and documentation only.

Laravel's standard app, bootstrap, config, database, resources, routes, storage and tests directories remain intact. Generated framework examples are not Sumaya implementations.

## Module layout and dependency direction

Every module under `app/Domains/<Module>/` has four boundaries. Identity, Catalog, Favorites, Cart, Orders, ManagementIdentity, ManagementDashboard, Settings and Content implement relevant parts; other modules remain empty:

- `Domain/`: business concepts, invariants and rules. Keep framework and external service concerns outside the core domain.
- `Application/`: future focused Actions / Use Cases that coordinate a business operation. Define DTOs and ports only when a real use case needs them.
- `Infrastructure/`: persistence and external adapters. Google integration belongs here rather than in Domain.
- `Interfaces/`: delivery adapters, such as HTTP and management entry points, translating input and output around application operations.

Interfaces invoke Application; Application depends on Domain. Infrastructure implements required inward-facing contracts. Domain does not depend on Infrastructure or Interfaces. Composition and framework wiring remain outside the domain core. Cross-module access should use explicit application boundaries rather than reaching into another module's internal implementation; concrete contracts are deferred until needed.

Deeper folders such as Entities, ValueObjects, Enums, Rules, Exceptions, Actions, DTOs, Ports, Persistence, Cache, Queue, Storage, ExternalServices, Http and Management may be added when implementation requires them. They are deliberately not repeated across all modules now.

## Pragmatic hexagonal architecture

Use ports and adapters where integration variability or testing justifies them. Simple CRUD may use Laravel and Eloquent directly in a focused application operation without artificial domain entities or a repository wrapper. Do not introduce full DDD, CQRS, event sourcing, interfaces everywhere, deep inheritance or microservices. KISS and clear ownership matter more than folder count.

## API organization

The versioned contract is `/api/v1/`. Phase 1 registers the empty `routes/api.php`
with this prefix and adds global request IDs and generic API exception handling.
`app/Http/Requests` contains shared personal collection validation; module controllers/resources live in their HTTP adapters. Module-specific HTTP adapters may live under the corresponding
Interfaces directory when needed. Decide concrete ownership during implementation,
avoiding duplicate controllers. Phase 2 implements the four customer authentication
routes in Identity's HTTP adapters; Google routes use the web session middleware.

## Shared technical support

`app/Support/` is reserved for genuinely shared technical helpers. Business behavior stays within its owning module. Do not turn Support into a miscellaneous dumping ground.

## System Console

`packages/system-console/` is a physically separate reusable Laravel package scaffold. It must remain independent of Product, Order, Gift, Customer, Brand and every Sumaya-specific business model. It has no service provider, package Composer manifest, autoload wiring or implementation yet. Package registration and integration are deferred.

## Performance and documentation

The Sumaya-specific Business Management Panel and generic reusable System Console are
separate concerns. Phase 6A implements only Owner identity and account pages using a separate guard/model; System Console remains an independent unwired skeleton. Phase 2 removes the untouched default User scaffold
and uses a dedicated Customer model. See [Identity architecture](identity-and-authentication.md).

Performance by design means considering query counts, indexes, pagination, concurrency and algorithmic complexity while implementing future use cases. Measure before speculative optimization. See [performance principles](performance-principles.md).

`docs/architecture` contains architectural guidance; `docs/adr` records accepted baseline
decisions; `docs/api` documents the API foundation; `docs/deployment` documents CI/CD;
`docs/database` documents the implemented Catalog and Favorites/Cart schemas; `docs/operations` remains reserved; `docs/reports` contains
phase reports.

Phase 4: [Favorites](favorites.md), [Cart](cart.md), [Catalog boundary](cart-catalog-integration.md), [Checkout boundary](cart-checkout-compatibility.md).

Phase 6C: [Catalog administration](catalog-administration.md) extends the existing private Owner shell, with Catalog-owned transactional writes and audited archival. [ADR-022](../adr/ADR-022-catalog-administration.md).

Phase 6D: [Preliminary request management](preliminary-request-management.md) implements the Owner-approved internal review workflow separately from immutable Customer submissions and PAY001.

Phase 6E: [Business settings](business-settings.md), [Content publication](storefront-content-management.md), [commercial preparation](commercial-policy-preparation.md), [ADR024](../adr/ADR-024-business-settings-and-storefront-content.md), [approved gate](../reports/phase-06e-business-configuration-design-gate.md). Payment/delivery execution and intake pausing remain deferred.
