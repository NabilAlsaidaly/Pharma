# Customer identity and authentication

Phase 6A adds [separate Owner authentication](owner-authentication.md) without changing this Google-only Customer contract. Existing Customer full-session invalidation also removes Owner authentication when both share one browser session; Owner-only actions preserve Customer keys.

Phase 2 implements Google-only customers. Catalog, management authentication and
System Console are outside this implementation.
The setup/verification notes below record the Phase 2 baseline. The owner subsequently
reported a successful real Google login before Phase 3, and local credentials are now
configured. Phase 3 preserves the mechanism/configuration and does not repeat that login.

## Boundaries and flow

Domain owns CustomerAccountStatus and four safe authentication exceptions.
Application owns GoogleIdentityData, CustomerAuthenticationData, the GoogleOAuthClient
and CustomerIdentityRepository ports, and AuthenticateGoogleCustomer. It has no
Eloquent, Socialite or HTTP dependency. AppServiceProvider binds the two adapters.
Infrastructure owns the authenticatable Customer, Eloquent persistence and Google
Socialite adapter. Interfaces owns the redirect/session controllers, safe resource,
frontend redirect validation, suspension middleware and error rendering.

The redirect endpoint stores a validated relative return_to in the web session and
Socialite generates state. The callback validates state before contacting Google,
maps only sub/name/email/avatar and authenticates by sub. Existing profile fields and
last_login_at are synchronized. Unique google_sub plus createOrFirst protect against
duplicate inserts; synchronization holds only a short DB transaction with a row lock.
No external call runs inside that transaction. A repeated normal login has four
customer queries: subject lookup, locked reload, update and guard retrieval. No N+1,
general repository layer, caching or invented domain entity is introduced.

The customer session guard logs in the persisted customer and regenerates the session.
Sanctum authenticates trusted SPA session requests on me/logout. Bearer tokens are
ignored. Suspended customers cannot log in; a suspended existing session is invalidated
and receives the Phase 1 error envelope with ACCOUNT_SUSPENDED and HTTP 403.
OAuth errors invalidate the session and redirect to /auth/error with one safe code.
Logs retain request_id, event, outcome and customer_id where known; never provider
tokens, code, google_sub, email, secret, raw response or exception message.

## Persistence

customers has only id char(36), google_sub varchar(255), name varchar(255), email
varchar(255), avatar_url nullable text, account_status varchar(255) default active,
last_login_at nullable timestamp, created_at and updated_at nullable timestamps.
Indexes: PRIMARY(id), UNIQUE customers_google_sub_unique(google_sub), non-unique
customers_email_index(email). HasUuids generates UUIDv7. No token, password, reset,
session, cache or jobs table is created. Framework migration metadata is separate.

## Local setup

1. Copy .env.example to .env and supply local MySQL credentials without committing them.
2. Use Herd PHP 8.4, composer install, and generate APP_KEY only on initial setup.
3. Secure only this Herd site: herd secure sumaiya_pharma. Verify /up at
   https://sumaiya_pharma.test before setting APP_URL to that HTTPS origin.
4. Configure FRONTEND_URL as a single trusted origin without path, query or user info.
   Configure CORS_ALLOWED_ORIGINS as exact scheme/host/port origins and
   SANCTUM_STATEFUL_DOMAINS as SPA host:port values without scheme. Never use wildcards.
5. Set GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET locally. Register exactly
   https://sumaiya_pharma.test/api/v1/auth/google/callback as the Google web-client
   redirect URI and set GOOGLE_REDIRECT_URI to that value. Configure the consent
   screen and test users as required by your Google project. Credentials remain absent
   in this checkout; no live Google login is claimed.
6. Use file session/cache and sync queue locally; do not switch to unverified Redis.
   Run only php artisan migrate against development, never migrate:fresh or db:wipe.
7. Create sumaya_pharma_test separately. Copy local configuration into ignored
   .env.testing with APP_ENV=testing and DB_DATABASE=sumaya_pharma_test, its own
   authorized DB credentials, and dummy Google credentials. Run php artisan test.

The actual local frontend value currently equals the backend origin as a placeholder;
no Next.js frontend is present. For a future frontend, prefer HTTPS sibling subdomains
under the same site, a shared parent SESSION_DOMAIN where needed, an explicit CORS
origin and matching Sanctum SPA host. Validate that topology in a browser before
claiming frontend integration. localhost on another site does not work merely by
adding CORS: SameSite=Lax and browser third-party-cookie policy still apply.

## Browser contract and security

Navigate to the Google redirect endpoint as a top-level navigation. Following callback,
bootstrap CSRF via /sanctum/csrf-cookie with credentials, send Accept: application/json,
and include X-XSRF-TOKEN from the decoded XSRF-TOKEN cookie on POST logout. Session
cookies are encrypted, Secure, HttpOnly and SameSite=Lax; XSRF-TOKEN is intentionally
readable for the header. Logout invalidates the old session and regenerates CSRF.

return_to defaults to /. It must be a single-slash relative path of at most 2048 bytes.
Repeated percent-decoding checks reject protocol-relative paths, backslashes, control
characters, whitespace and encoded bypasses. Only the configured frontend origin is
concatenated; caller-provided origins are never trusted. Google redirect/callback share
the IP limiter configured by GOOGLE_AUTH_RATE_LIMIT_PER_MINUTE (default 20).
CORS permits credentials and exposes X-Request-ID. With one origin, the library may
emit that configured origin to a different caller; browsers block the mismatched origin.

See [API contract](../api/customer-authentication.md) and ADRs 004, 012–014.
Socialite and Sanctum integration follows the official Laravel documentation:
https://laravel.com/framework/docs/socialite and https://laravel.com/framework/docs/sanctum.
