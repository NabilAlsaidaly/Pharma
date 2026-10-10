# ADR-004: Google-only customer authentication

## Status

Accepted / Baseline

## Context

MVP customer authentication is explicitly Google only.

## Decision

Use Google authentication for customers; omit local passwords, username login, SMS OTP, phone verification and email/password registration.

## Consequences

Google integration belongs in Infrastructure. Authentication wiring and management authentication details are deferred.
