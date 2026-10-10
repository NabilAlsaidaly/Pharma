# ADR-012: Customer identity and guard

## Status

Accepted / Implemented in Phase 2

## Decision

Identity owns Customer under Infrastructure/Persistence/Models. Use UUIDv7 through
Laravel HasUuids, a unique required google_sub, a non-unique indexed email and an
active/suspended enum cast. Customer has no password, remember token or soft deletion.
Authentication uses the customer session guard and customers Eloquent provider.
Remove only the verified untouched default User, factory and default migrations;
keep DatabaseSeeder as an empty framework entry point.

## Consequences

No email-based account merging. createOrFirst recovers concurrent duplicate inserts
through MySQL uniqueness; a short locked DB transaction rechecks suspension before
profile synchronization. Application receives DTOs through an explicit repository port.
Management models and guards are independent future decisions.
