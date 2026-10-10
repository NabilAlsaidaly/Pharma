# ADR-014: Stateful cookie customer authentication

## Status

Accepted / Implemented in Phase 2

## Decision

Use Sanctum's first-party session authentication with guard customer. Do not create
personal_access_tokens or use HasApiTokens; bearer token extraction returns null.
Enable stateful API middleware, encrypted cookies and request-forgery checks.
Disable Sanctum's password-hash session authentication middleware because customers
have no passwords. Regenerate sessions on login; invalidate sessions and regenerate
CSRF tokens on logout or suspension. Use Secure, HttpOnly, SameSite=Lax session cookies.

## Consequences

Frontend/backend must use a planned same-site HTTPS topology. Trusted SPA hosts and
exact CORS origins are separate configuration lists. Credentials are allowed and
X-Request-ID exposed, without wildcard origins. A cross-site localhost frontend is
not declared compatible. File sessions/cache are local fallbacks until Redis is verified.
