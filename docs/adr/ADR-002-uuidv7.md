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
Phase 2 uses `HasUuids` on Identity's Customer, with a non-incrementing string primary
key stored as `char(36)`. MySQL integration tests verify generated UUID version 7.
The untouched default User scaffold is removed; no demonstration model is created.
