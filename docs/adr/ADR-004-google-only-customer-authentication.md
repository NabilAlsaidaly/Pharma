# ADR-004: Google-only customer authentication

## Status

Accepted / Implemented in Phase 2

## Context

MVP customer authentication is explicitly Google only.

## Decision

Use Google authentication for customers; omit local passwords, username login, SMS OTP, phone verification and email/password registration.

## Consequences

Google integration uses Socialite in Infrastructure with session-backed OAuth state.
Customers are identified only by Google's stable `sub`; email never links accounts.
Persist profile data only, never provider tokens. Sanctum uses the dedicated customer
session guard, without bearer tokens, password broker or remember tokens.
See ADRs 012–014 and the Identity architecture document. Management authentication is deferred.
