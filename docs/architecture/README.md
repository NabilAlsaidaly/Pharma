# Architecture

## Style and scope

Sumaya Pharma is an API-first modular monolith: one Laravel backend deployment with explicit domain-oriented module boundaries. Next.js is a separate frontend project. MySQL is the target database; Redis is planned infrastructure. Phase 0 creates directories and documentation only.

Laravel's standard app, bootstrap, config, database, resources, routes, storage and tests directories remain intact. Generated framework examples are not Sumaya implementations.

## Module layout and dependency direction

Every module under `app/Domains/<Module>/` currently has four empty boundaries:

- `Domain/`: business concepts, invariants and rules. Keep framework and external service concerns outside the core domain.
- `Application/`: future focused Actions / Use Cases that coordinate a business operation. Define DTOs and ports only when a real use case needs them.
- `Infrastructure/`: persistence and external adapters. Google integration belongs here rather than in Domain.
- `Interfaces/`: delivery adapters, such as HTTP and management entry points, translating input and output around application operations.

Interfaces invoke Application; Application depends on Domain. Infrastructure implements required inward-facing contracts. Domain does not depend on Infrastructure or Interfaces. Composition and framework wiring remain outside the domain core. Cross-module access should use explicit application boundaries rather than reaching into another module's internal implementation; concrete contracts are deferred until needed.

Deeper folders such as Entities, ValueObjects, Enums, Rules, Exceptions, Actions, DTOs, Ports, Persistence, Cache, Queue, Storage, ExternalServices, Http and Management may be added when implementation requires them. They are deliberately not repeated across all modules now.

## Pragmatic hexagonal architecture

Use ports and adapters where integration variability or testing justifies them. Simple CRUD may use Laravel and Eloquent directly in a focused application operation without artificial domain entities or a repository wrapper. Do not introduce full DDD, CQRS, event sourcing, interfaces everywhere, deep inheritance or microservices. KISS and clear ownership matter more than folder count.

## API organization

The future versioned contract is `/api/v1/`. `app/Http/Controllers/Api/V1`, `app/Http/Requests` and `app/Http/Resources` are empty organizational locations. Module-specific HTTP adapters may live under the corresponding Interfaces directory when needed. Decide concrete ownership during implementation, avoiding duplicate controllers. No custom route file, API registration, controller or endpoint is created in this phase.

## Shared technical support

`app/Support/` is reserved for genuinely shared technical helpers. Business behavior stays within its owning module. Do not turn Support into a miscellaneous dumping ground.

## System Console

`packages/system-console/` is a physically separate reusable Laravel package scaffold. It must remain independent of Product, Order, Gift, Customer, Brand and every Sumaya-specific business model. It has no service provider, package Composer manifest, autoload wiring or implementation yet. Package registration and integration are deferred.

## Performance and documentation

Performance by design means considering query counts, indexes, pagination, concurrency and algorithmic complexity while implementing future use cases. Measure before speculative optimization. See [performance principles](performance-principles.md).

`docs/architecture` contains architectural guidance; `docs/adr` records accepted baseline decisions; `docs/api`, `docs/database`, `docs/deployment` and `docs/operations` are reserved empty locations; `docs/reports` contains phase reports.
