# ADR-001: Modular monolith

## Status

Accepted / Baseline

## Context

The backend needs maintainable module boundaries without distributed-system complexity.

## Decision

Use one Laravel backend with domain-oriented modules.

## Consequences

Deployment stays unified; preserve module ownership and explicit dependencies.
