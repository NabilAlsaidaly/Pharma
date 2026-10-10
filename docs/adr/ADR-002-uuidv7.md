# ADR-002: UUIDv7

## Status

Accepted / Baseline

## Context

The supplied baseline selects UUIDv7 for the identifier strategy.

## Decision

Adopt UUIDv7 as the baseline identifier strategy.

## Consequences

Phase 1 uses Laravel's built-in `Illuminate\Support\Str::uuid7()` for request IDs,
verified by foundation tests. Future important application entities should use Laravel's
built-in `Illuminate\Database\Eloquent\Concerns\HasUuids`, whose default generation in
the installed Laravel 13 framework is UUIDv7. No custom UUID package or service is needed.

Entity scope, storage and schema details remain deferred to their owning modules.
The default `App\Models\User` and users migration remain unchanged; Identity persistence
is a Phase 2 decision. No demonstration model or migration is created.
