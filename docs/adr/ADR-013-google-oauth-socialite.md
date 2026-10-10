# ADR-013: Google OAuth through Socialite

## Status

Accepted / Implemented in Phase 2

## Decision

Use Laravel Socialite's Google provider behind GoogleOAuthClient. Request only openid,
profile and email scopes. Retain session-backed state validation; never use stateless.
Map Google's sub/email/name/avatar to GoogleIdentityData; reject absent sub/email and
explicitly unverified email. Use email when display name is absent. Do not store or
return tokens, authorization codes, raw provider responses or exception messages.

## Consequences

Provider configuration is external, timeouts are bounded, and failures expose only
safe codes on the configured frontend origin. Clear cached Socialite drivers between
requests so a previously cached provider user cannot bypass state validation.
Tests stub HTTP at the external boundary. Real Google consent remains unverified
without owner-supplied credentials and an interactive account flow.
