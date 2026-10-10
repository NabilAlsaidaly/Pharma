# ADR-009: Pragmatic hexagonal architecture

## Status

Accepted / Baseline

## Context

Important business boundaries need clear responsibility without artificial enterprise layers.

## Decision

Use Domain, Application, Infrastructure and Interfaces boundaries; add ports and adapters only where they provide value.

## Consequences

Domain stays independent of external adapters. Simple CRUD may stay simple; no full DDD, CQRS or event sourcing.
