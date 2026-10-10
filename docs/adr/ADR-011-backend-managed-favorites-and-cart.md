# ADR-011: Backend-managed favorites and cart

## Status

Accepted / Baseline

## Context

Favorites and cart require authenticated customers.

## Decision

Persist and manage favorites and cart in the backend.

## Consequences

Browser localStorage is not the primary store; persistence and endpoint implementation are deferred.
