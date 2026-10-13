# ADR-020: Separate Owner authentication foundation

## Status

Implemented in Phase 6A feature branch, pending owner review and merge.

## Context

ADR-004/012/014 preserve Google-only Customer identity and session authentication. ADR-006 calls for a custom management panel; ADR-007 keeps the reusable System Console independent. The approved phase6A contract authorizes only secure Owner identity and personal account access.

## Decision

Create ManagementIdentity with its own ManagementUser table/provider and explicit owner session guard. Keep customer as the default and preserve Sanctum behavior. Use custom Blade/Tailwind/Vite Arabic RTL views, no packaged administration framework or Next.js management app. Provision one owner through a trusted interactive hidden-input command with a database-generated unique owner slot. Use a configurable fail-closed prefix without a fallback.

Require exact active Owner authorization on every request, native credential hashing, CSRF, HMAC identity/IP quotas, no-store HTML and safe structured logs. Password changes lock/revalidate the row, atomically increment a string-safe unsigned credential version and preserve the rotated current session while rejecting old versions on subsequent access. Retain existing shared-cookie Customer behavior; Owner actions preserve Customer keys, while existing Customer full-session invalidation clears both guards.

## Consequences

There is no default account, public setup or automated production/development schema rollout. Trusted DBA privileges remain outside application enforcement. No recovery/verification/2FA or staff lifecycle is available; operational recovery requires separately approved work. Prefix/config cache changes require cache rebuilds, and multi-instance deployments require a shared limiter store. Business management starts only in later approved phases. Real independent-connection races and MySQL checks accompany the implementation; existing Customer/business tests remain required.

See [architecture](../architecture/owner-authentication.md) and [Phase 6A report](../reports/phase-06a-owner-authentication-report.md).
