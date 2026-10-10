# ADR-007: Reusable System Console

## Status

Accepted / Baseline

## Context

Operational capabilities should be reusable outside Sumaya Pharma.

## Decision

Place System Console in a separate Laravel package skeleton at packages/system-console.

## Consequences

No dependency on Product, Order, Gift, Customer, Brand or other Sumaya models. Package wiring and implementation are deferred.
