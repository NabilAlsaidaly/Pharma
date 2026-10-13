# Owner authentication foundation

Phase 6A implements ManagementIdentity separately from Google-only Customer Identity. See [ADR-020](../adr/ADR-020-owner-authentication-foundation.md), [operations/security](../security/owner-access.md), [web contract](../api/owner-web-interface.md) and [evidence](../reports/phase-06a-owner-authentication-report.md).

## Boundaries

`app/Domains/ManagementIdentity` owns provisioning, email/password authentication and the personal account. Interfaces contains thin controllers, request validation, route registration, access middleware and the interactive command. Application coordinates transactions; Infrastructure owns Eloquent persistence, audit events and HMAC rate-limit keys. There is no cross-module Customer lookup or email matching. Framework validation lives in Application, not a framework-dependent domain entity. No repository abstraction is introduced for these small local operations.

The default guard remains `customer`; `owner` is an explicit session guard whose `management_users` Eloquent provider uses ManagementUser. Sanctum remains Customer session-only. A management session never grants Customer API access. The reusable `packages/system-console` is untouched and has no dependency on ManagementIdentity or business models.

## Persistence and provisioning

One additive migration creates `management_users`: UUIDv7 primary key, display_name(150), unique normalized email(254), native password hash, role(32), active/disabled status, positive unsigned BIGINT credential_version, nullable last_login_at and timestamps. A nullable generated `owner_slot` equals 1 only for role=owner, with a unique index. This protects the single owner invariant under actual simultaneous inserts, including a disabled owner. The application checks existence inside a transaction and treats a duplicate key safely; the unique index is authoritative. There are no foreign keys into business tables, recovery tokens, remember token or password confirmation column.

Only the interactive `owner:provision` command exposes provisioning, with hidden password and confirmation, no credential command options, no environment password and no seed. The application validates before insertion and never creates a partial account. The command refuses missing schema, noninteractive terminals and another existing owner. The model rejects owner demotion/deletion and guards mass assignment. This is an application boundary, not a database trigger preventing a trusted DBA from changing rows. No web management user lifecycle endpoints exist.

## Login and authorization

Routes are registered only when OWNER_PORTAL_PREFIX passes the startup validation. Missing or invalid configuration logs a safe diagnostic and creates no Owner routes. Validated configuration supports cached routes and cached config; changes require rebuilding both. Prefix secrecy reduces discovery, while authentication, CSRF and state/version checks enforce access independently.

Login normalizes email, applies HMAC identity/IP quotas before body validation and checks a native password hash under a row lock. A random native dummy hash is generated on each attempt and checked for missing accounts, avoiding the obvious missing-account fast path. Wrong, unknown, disabled and non-owner credentials share one message. Successful login updates last_login_at, logs into owner without remember-me, rotates session ID and CSRF, stores the credential version as a decimal string and the Owner activity timestamp.

Every protected request loads current owner state from MySQL even if the guard already cached its user. Access requires the exact owner role, active status, matching credential_version and a valid activity timestamp less than 30 minutes old. Rejection clears Owner authentication, rotates session ID/CSRF and returns safe 403 HTML. Anonymous visitors and Customers redirect to the configured Owner login. No role enumeration, staff management or wildcard policy is introduced.

## Shared browser session semantics

All Owner security transitions use `regenerate(true)`: destroy the old server-side session record, transfer retained data to the fresh ID, then rotate CSRF. Merely calling the default `regenerate()` retains the old record and is insufficient for secure logout; old-cookie replay regression verifies the corrected behavior.

Both guards use independent authentication keys in one existing Laravel session cookie. Owner login, logout, idle expiry and password change rotate the session and CSRF but preserve unrelated Customer session data. Owner logout removes only Owner keys; it never authenticates or creates a Customer. The existing Customer logout, suspension and failed OAuth paths invalidate the entire shared session, so they also remove Owner login on the next independent request. This intentionally preserves existing Customer security behavior. Other tabs sharing the cookie see the rotated ID and need the new CSRF token; stale forms receive 419. Separate browser profiles can be used operationally.

## Password change and revocation

The request requires current password, a distinct new password and matching confirmation. The policy is minimum 12 Unicode characters, maximum 72 bytes and no NUL, compatible with the configured native bcrypt hasher without truncation. There are no arbitrary character classes. Secrets are never redisplayed or flashed.

Within one transaction, password change locks the owner row, repeats role/status/version checks, verifies the current hash, rejects reuse, saves the new native hash and increments credential_version exactly once. Unsigned overflow fails closed. Competing changes starting from one version produce one successful version increment and one 403. The current browser reauthenticates the updated model and rotates ID/CSRF with the new version. Other sessions are logically revoked on their next protected request; no persistent session table, Redis or mass session deletion is required. Already completed operations and requests that passed authorization before a revocation cannot be retroactively withdrawn. Account mutations repeat checks under the row lock.

## Future boundaries

6B may introduce authorized dashboard/search/table foundations after approval; 6C catalog editing, bulk operations and safe soft delete/restore; 6D preliminary request review. This phase creates none of those. Future payments, owner-configured methods/deposits and verification remain under PAY-001 and the preserved Phase 5 gate. Email change, verification, recovery, 2FA, trusted devices and granular staff roles require separate approved designs.
