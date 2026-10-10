# Phase 2 — Customer Identity & Google Authentication

Verification date: 2026-10-10. Scope: customer Identity only.

## 1. Executive Summary

Implemented Google-only customer identity, UUIDv7 persistence, Socialite OAuth with
session-backed state, dedicated customer guard, Sanctum session authentication,
CSRF, credentialed exact-origin CORS, safe return redirects, IP rate limiting,
account suspension, safe logging and error contracts. Applied the forward customers
migration to development. All 38 tests pass with 243 assertions on real MySQL.
Live Google OAuth is NOT VERIFIED: real client credentials are absent.

## 2. Starting State

- Laravel 13.35.0; PHP 8.4.26; Composer 2.10.2; Herd CLI 1.30.0 local runtime.
- MySQL97 service, server 9.7.0. Development sumaya_pharma initially had zero tables.
- Redis connectivity/service not verified; retained file cache/session and sync queue.
- main was clean at fbe073062d5253fce0fdbcb912b6e4d3cdee3117, the Phase 1 report commit.
- Phase 1 implementation: 7b7426c103093feebc2c2faf1867b11ed2b9695a.
- Phase 1 CI verified success: run 38039378762 for the starting HEAD.
- Origin: https://github.com/NabilAlsaidaly/Pharma.git.
- Default User/factory/migrations were unchanged from scaffold; only default references
  existed. Socialite/Sanctum and Google credentials were absent.
- Herd HTTPS was enabled only for sumaiya_pharma; unrelated sites were preserved.
  /up returned 200 with certificate validation enabled. Windows local certificates
  require curl's --ssl-revoke-best-effort because they have no revocation endpoint;
  no --insecure bypass was used. APP_URL now uses https://sumaiya_pharma.test.

## 3. Dependencies

Added laravel/socialite v5.31.0 for the Google adapter and laravel/sanctum v4.3.3
for first-party session authentication, pinned to their minor lines in composer.json.
Targeted resolution added six packages, updated zero and removed zero. Transitives:
firebase/php-jwt v7.2.1, league/oauth1-client v1.12.0,
paragonie/constant_time_encoding v3.1.3, phpseclib/phpseclib 4.0.2.
The JWT package is Socialite's provider dependency; the application issues no JWTs.
No Laravel or unrelated dependency upgrade. Composer strict validation and platform
requirements pass. A Windows temporary extraction lock interrupted the first install;
a sequential composer install from the same lock succeeded. Dry-run audit was stopped
after resolution; the final independent composer audit completed successfully.

## 4. Laravel Scaffold Cleanup

Removed verified untouched User model, UserFactory and the three default migrations
listed in section 23. Those migrations would introduce users, password reset tokens,
sessions, cache/locks and jobs/batches/failed_jobs outside Phase 2. They had never run
in development. DatabaseSeeder remains, with its default User factory call removed.
No existing business table or custom model was removed.

## 5. Customer Data Model

| Column | MySQL type | Nullable | Default / constraint |
| --- | --- | --- | --- |
| id | char(36) | no | UUIDv7; non-incrementing string primary key |
| google_sub | varchar(255) | no | unique; sole external identity lookup |
| name | varchar(255) | no | profile |
| email | varchar(255) | no | indexed, explicitly non-unique |
| avatar_url | text | yes | null |
| account_status | varchar(255) | no | active; CustomerAccountStatus active/suspended cast |
| last_login_at | timestamp | yes | null |
| created_at | timestamp | yes | null |
| updated_at | timestamp | yes | null |

Actual MySQL metadata: PRIMARY(id), customers_google_sub_unique(google_sub) UNIQUE,
customers_email_index(email) non-unique; all BTREE. Exactly nine columns and three
indexes. No password, remember_token, phone, deleted_at, verification or token fields.
Laravel HasUuids generates version 7; tests verify key type, version and uniqueness.
Raw model serialization hides google_sub; HTTP uses an explicit six-field resource.

## 6. Identity Architecture

All production classes live under app/Domains/Identity; AppServiceProvider composes
the adapters. No module-specific provider or external dependency in Application.

| Class | Layer / responsibility |
| --- | --- |
| CustomerAccountStatus | Domain enum for active/suspended |
| AccountSuspended | Domain refusal for suspended customer |
| AuthenticationFailed | Domain generic OAuth failure |
| AuthProviderUnavailable | Domain unavailable configuration/network |
| InvalidExternalIdentity | Domain unusable provider identity |
| GoogleIdentityData | Application readonly sub/name/email/avatar DTO |
| CustomerAuthenticationData | Application readonly persisted safe profile/status DTO |
| GoogleOAuthClient | Application port for authorization URL and identity |
| CustomerIdentityRepository | Application port for subject lookup/create/profile synchronization |
| AuthenticateGoogleCustomer | Application orchestration; lookup by sub, reject suspension, synchronize |
| Customer | Infrastructure authenticatable Eloquent model with HasUuids and casts |
| EloquentCustomerIdentityRepository | Infrastructure MySQL persistence and duplicate-insert recovery |
| SocialiteGoogleOAuthClient | Infrastructure stateful Google adapter and identity validation |
| GoogleAuthenticationController | Interfaces redirect/callback, session login and safe failure redirect |
| CustomerSessionController | Interfaces me/logout |
| CustomerResource | Interfaces explicit safe six-field response |
| FrontendRedirect | Interfaces trusted origin and relative return path validation |
| EnsureActiveCustomer | Interfaces suspension check and session revocation |
| IdentityErrorResponse | Interfaces ACCOUNT_SUSPENDED 403 envelope |

Persistence uses createOrFirst and DB uniqueness, not email matching. Normal repeated
login performs subject lookup, locked reload, profile update and guard retrieval.
The short synchronization transaction rechecks suspension with a row lock; no Google
network request runs inside it. No speculative caching or general repository framework.

## 7. Google OAuth

The two Google routes run through web/session middleware. Socialite generates and
validates state, with scopes exactly openid/profile/email; no stateless flow. The
adapter clears cached drivers to avoid reusing a previously resolved provider user
across requests. Tests verify missing/mismatched state and replay rejection before
unnecessary HTTP. The callback requires raw sub and valid email, rejects explicit
email_verified=false, and falls back to email for a missing display name.
Only the four identity fields enter Application. Access/refresh/ID tokens, expiry,
authorization code and raw provider responses are neither persisted nor returned.
Timeouts: connect 5 seconds, total 10 seconds. Typed failures become safe frontend
codes; unexpected exceptions become AUTHENTICATION_FAILED. Logs contain safe event,
request_id, outcome, customer_id where known and exception type, never raw messages,
email, subject, codes, tokens or secrets.

## 8. Authentication Guard

customer is the default session guard, using customers Eloquent provider and the
Identity Customer model. Sanctum guard list is ['customer']; stateful middleware is
enabled. No HasApiTokens, token table, bearer authentication or password broker.
Bearer extraction explicitly returns null, so unsolicited tokens do not query a
nonexistent table. Sanctum's password-hash middleware is disabled for passwordless
customers; encrypted-cookie and request-forgery middleware remain enabled.

## 9. Session Security

Callback regenerates the session. Logout logs out the customer guard, invalidates
the session and regenerates CSRF; old session cookies cannot authenticate in tests.
Suspension and OAuth failure also invalidate sessions. Cookie policy: Secure,
HttpOnly, SameSite=Lax, local file session driver. Framework /sanctum/csrf-cookie
bootstraps CSRF; POST logout requires the token. The dedicated test overrides only
Laravel's PHPUnit forgery bypass to prove missing token 419 and valid token success.
XSRF-TOKEN is intentionally readable; the session cookie is HttpOnly.

## 10. CORS

Explicit comma-separated CORS_ALLOWED_ORIGINS, no origin patterns or wildcard default.
Credentials enabled, methods GET/POST/OPTIONS, explicit CSRF/request headers and
X-Request-ID exposed. Applies to api/* and sanctum/csrf-cookie. Local origin currently
equals https://sumaiya_pharma.test. A single-origin CORS response may emit that fixed
origin even to another caller; browsers reject the origin mismatch. Tests verify
the configured origin is never replaced with the attacker's origin.
SANCTUM_STATEFUL_DOMAINS uses host:port without scheme. No separate frontend exists;
same-site HTTPS frontend topology still needs browser verification.

## 11. Return-to Security

Only a relative single-slash path of at most 2048 bytes is retained. Default /.
Reject absolute/protocol-relative URLs, backslashes, control/whitespace characters,
CRLF, encoded bypasses and excessive decoding nesting. Destination is always the
validated configured FRONTEND_URL origin without credentials/path/query/fragment.
Caller values cannot select an origin. Malicious-path tests cover these cases.

## 12. Account Suspension

New OAuth login for a suspended subject fails with ACCOUNT_SUSPENDED and no
authenticated session, without updating the blocked profile. An existing session
for a subsequently suspended customer receives 403 ACCOUNT_SUSPENDED with matching
request_id/X-Request-ID, is invalidated, and subsequent requests receive 401.
No suspension administration interface is introduced.

## 13. API Endpoints

- GET /api/v1/auth/google/redirect — stateful Google redirect, optional return_to.
- GET /api/v1/auth/google/callback — state validation, identity synchronization, login.
- GET /api/v1/auth/me — auth:sanctum and active customer; safe six-field response.
- POST /api/v1/auth/logout — auth:sanctum, active customer and CSRF; session revocation.

Framework GET /sanctum/csrf-cookie is separate. See docs/api/customer-authentication.md.
Google endpoints share an IP limiter, default 20/minute, configurable through
GOOGLE_AUTH_RATE_LIMIT_PER_MINUTE. APIs retain the Phase 1 generic error envelope.

## 14. Database

Development: sumaya_pharma, MySQL 9.7.0, forward php artisan migrate --no-interaction
succeeded; migration 2026_10_10_000001_create_customers_table is Ran, batch 1.
Table count exactly 2: customers and migrations; customer row count 0 after verification.
No migrate:fresh, db:wipe or destructive development reset was executed.
Test: sumaya_pharma_test, same local MySQL service, exactly customers/migrations.
phpunit.xml forces mysql and that database name; IdentityTestCase checks both plus
testing environment before RefreshDatabase. Ignored .env.testing holds local
connection settings, with dummy provider configuration. All persistence tests used
real MySQL. CI tests use the official MySQL 8.4 service. No credentials are recorded.
Herd HTTPS runtime checks: /up 200, unauthenticated /api/v1/auth/me 401 with safe JSON,
/sanctum/csrf-cookie 204, missing Google config redirect 302 to safe unavailable code.

## 15. Tests

Full suite: 38 passed, 243 assertions; 28 new Identity tests and 10 existing tests.
MySQL constraints/schema are tested against the separate test database. The Google
port fake is used for HTTP/session feature tests; real Socialite adapter tests use
Guzzle MockHandler. Neither category is live Google verification. The duplicate
insert test exercises createOrFirst's database collision recovery; it is not a
parallel multi-process load test. Every Identity test method follows:

**CustomerAuthenticationTest**

- test_google_redirect_retains_safe_return_path_and_request_id
- test_callback_creates_customer_and_establishes_session_with_regenerated_id
- test_repeated_callback_reuses_subject_and_updates_profile
- test_untrusted_return_paths_cannot_create_external_redirect
- test_suspended_customer_cannot_receive_authenticated_session
- test_unauthenticated_and_bearer_only_requests_use_401_error_contract
- test_current_customer_exposes_only_safe_fields
- test_logout_invalidates_session_and_old_cookie_cannot_authenticate
- test_suspension_revokes_existing_authenticated_session
- test_provider_failure_redirect_and_logs_exclude_sensitive_details
- test_unavailable_provider_returns_only_safe_error_code
- test_google_auth_rate_limit_is_shared_by_redirect_and_callback_for_client_ip
- test_stateful_logout_requires_csrf_token_and_accepts_valid_token
- test_csrf_bootstrap_and_credentialed_cors_allow_only_configured_origin

**CustomerPersistenceTest**

- test_mysql_schema_contains_only_identity_columns_and_required_indexes
- test_customer_uses_uuidv7_status_cast_and_hides_google_identity
- test_mysql_enforces_google_sub_uniqueness
- test_create_or_first_recovers_duplicate_insert_without_creating_second_customer
- test_same_email_never_links_different_google_subjects
- test_existing_subject_updates_profile_and_login_time_without_changing_identity
- test_suspended_subject_is_rejected_without_profile_or_status_changes

**GoogleOAuthAdapterTest**

- test_authorization_url_has_only_identity_scopes_and_session_backed_state
- test_mismatched_or_missing_state_fails_before_any_provider_request
- test_valid_identity_is_mapped_without_tokens_and_callback_state_cannot_be_replayed
- test_missing_subject_email_or_explicitly_unverified_email_is_rejected
- test_missing_credentials_is_provider_unavailable
- test_provider_network_failure_is_sanitized
- test_provider_denial_is_authentication_failure_without_network_call


Initial local validation exposed unauthenticated APIs trying the nonexistent login
route; fixed the guest redirect callback to preserve JSON 401 without Accept headers.
Corrected schema/CORS assertions to match BTREE metadata and single-origin browser
enforcement. The final complete suite passes; no failing assertion was skipped.

## 16. Static Analysis

php vendor/bin/phpstan analyse --no-progress: [OK] No errors under the existing
Larastan configuration. Model cast property documentation is explicit; no new
baseline, ignore rule or reduced analysis level.

## 17. Formatting

php vendor/bin/pint --test: passed. Staged git diff --check: passed.

## 18. Security Audit

composer audit: No security vulnerability advisories found. Strict Composer
metadata validation and platform requirement checks also passed.

## 19. CI

The existing workflow retains metadata/platform/Pint/PHPStan/tests/audit checks.
Added official mysql:8.4 with a health check and disposable sumaya_pharma_test,
dummy CI-only credentials and Google configuration; no production secrets required.
GitHub run [38042700437](https://github.com/NabilAlsaidaly/Pharma/actions/runs/38042700437)
for implementation SHA 138355ffef9f0bdc3f97ff11af9614f9ae83dc8d completed with
conclusion success. This verifies the suite against CI MySQL 8.4 as well as local
MySQL 9.7. The report-only follow-up commit uses the same workflow and implementation.

## 20. Google Live Verification

NOT VERIFIED

EXPECTED: real Google browser consent and callback authenticate a customer.
ACTUAL: client ID/secret are absent; local initiation safely returns
AUTH_PROVIDER_UNAVAILABLE. Adapter/session tests pass using test doubles.
CAUSE: owner-controlled OAuth client configuration is not supplied.
WHAT WAS NOT CHANGED: no Google project, consent screen, credentials or frontend
was invented; no real account was used and no provider token was stored.
SAFE NEXT ACTION: enter real credentials in ignored .env, register the documented
HTTPS callback, configure consent/test users, then verify login/me/CSRF/logout
with the intended same-site frontend in a browser. Do not call mocked tests live.

## 21. Files Created

- app/Domains/Identity/Application/Actions/AuthenticateGoogleCustomer.php
- app/Domains/Identity/Application/DTOs/CustomerAuthenticationData.php
- app/Domains/Identity/Application/DTOs/GoogleIdentityData.php
- app/Domains/Identity/Application/Ports/CustomerIdentityRepository.php
- app/Domains/Identity/Application/Ports/GoogleOAuthClient.php
- app/Domains/Identity/Domain/Enums/CustomerAccountStatus.php
- app/Domains/Identity/Domain/Exceptions/AccountSuspended.php
- app/Domains/Identity/Domain/Exceptions/AuthProviderUnavailable.php
- app/Domains/Identity/Domain/Exceptions/AuthenticationFailed.php
- app/Domains/Identity/Domain/Exceptions/InvalidExternalIdentity.php
- app/Domains/Identity/Infrastructure/ExternalServices/Google/SocialiteGoogleOAuthClient.php
- app/Domains/Identity/Infrastructure/Persistence/EloquentCustomerIdentityRepository.php
- app/Domains/Identity/Infrastructure/Persistence/Models/Customer.php
- app/Domains/Identity/Interfaces/Http/Controllers/CustomerSessionController.php
- app/Domains/Identity/Interfaces/Http/Controllers/GoogleAuthenticationController.php
- app/Domains/Identity/Interfaces/Http/FrontendRedirect.php
- app/Domains/Identity/Interfaces/Http/IdentityErrorResponse.php
- app/Domains/Identity/Interfaces/Http/Middleware/EnsureActiveCustomer.php
- app/Domains/Identity/Interfaces/Http/Resources/CustomerResource.php
- config/cors.php
- config/identity.php
- config/sanctum.php
- database/migrations/2026_10_10_000001_create_customers_table.php
- docs/adr/ADR-012-customer-identity-and-guard.md
- docs/adr/ADR-013-google-oauth-socialite.md
- docs/adr/ADR-014-stateful-cookie-authentication.md
- docs/api/customer-authentication.md
- docs/architecture/identity-and-authentication.md
- tests/Fakes/FakeGoogleOAuthClient.php
- tests/Feature/Identity/CustomerAuthenticationTest.php
- tests/Feature/Identity/CustomerPersistenceTest.php
- tests/Feature/Identity/GoogleOAuthAdapterTest.php
- tests/IdentityTestCase.php
- docs/reports/phase-02-identity-google-auth-report.md (report commit)

Local ignored file created: .env.testing (no content published). External test
database and Herd site certificate are local configuration, not repository files.

## 22. Files Modified

- .env.example
- .github/workflows/ci.yml
- .gitignore
- README.md
- app/Providers/AppServiceProvider.php
- bootstrap/app.php
- composer.json
- composer.lock
- config/auth.php
- config/services.php
- database/seeders/DatabaseSeeder.php
- docs/README.md
- docs/adr/ADR-002-uuidv7.md
- docs/adr/ADR-004-google-only-customer-authentication.md
- docs/architecture/README.md
- docs/architecture/module-boundaries.md
- docs/deployment/ci-cd.md
- phpunit.xml
- routes/api.php
- routes/web.php

Local ignored .env updated with HTTPS/customer/session configuration; secrets remain
local. Prior phase reports and phase source documents were not rewritten.

## 23. Files Deleted

- app/Models/User.php
- database/factories/UserFactory.php
- database/migrations/0001_01_01_000000_create_users_table.php
- database/migrations/0001_01_01_000001_create_cache_table.php
- database/migrations/0001_01_01_000002_create_jobs_table.php

## 24. Git

Implementation commit: 138355ffef9f0bdc3f97ff11af9614f9ae83dc8d.
Push to origin/main succeeded. Branch main; origin
https://github.com/NabilAlsaidaly/Pharma.git. Working tree was clean after the
implementation push. This report is published in a subsequent documentation commit;
its exact SHA is available from git log for this path, avoiding a self-referencing SHA.
Only this report changes after the implementation checks; final status is checked
after its commit/push. .env and .env.testing remain ignored and unstaged.

## 25. Architecture Verification

System Console and Business Management Panel untouched. No Admin authentication,
Catalog, Cart or Orders implementation. No customer passwords, OTP or application
JWT authentication. No Google tokens stored. Socialite's transitive JWT package
supports its provider internals and does not add application JWT login. No frontend,
Redis installation, module provider, CQRS, event sourcing or unrelated feature.

## 26. Risks / Blockers

Real Google OAuth is unverified until credentials/consent are available. No separate
frontend exists, so browser integration and a future same-site cookie domain must
be verified after its origin is selected; localhost cross-site is not declared ready.
Local MySQL 9.7 differs from CI MySQL 8.4; both are covered by local/CI validation.
Redis remains unverified; local file/sync fallbacks are retained.
Windows sandbox restricts PHP temporary files and certificate access; the required
checks were rerun with approved access and completed. No unresolved local test,
analysis, migration or package-install failure remains.

## 27. Recommended Phase 3

Catalog Foundation:
Brands, Products, Variants, Images and Concerns
