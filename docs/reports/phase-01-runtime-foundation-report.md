# Phase 1 — Laravel Runtime & Engineering Foundation

Date: 2026-10-10 (Asia/Damascus).

## 1. Executive Summary

Implemented the Laravel technical foundation only: local Herd environment, verified
MySQL connection and empty database, UUIDv7 request IDs, API v1 registration, safe generic
API errors, lightweight logging context, eight foundation tests, Larastan/PHPStan,
GitHub Actions CI and documented future CD. Redis connectivity is blocked; no Redis
server or alternate client was installed. No business implementation was added.

## 2. Starting State

- Laravel Framework 13.35.0; PHP 8.4.26; Composer 2.10.2.
- PHP and Composer resolve through `C:/Users/nabil/.config/herd/bin/`.
- Herd CLI is installed; HerdHelper is running. This project was neither linked nor parked.
- Git branch main, clean working tree, origin already configured.
- Phase 0 commit already existed: `d0e72da7e89faea82810808739b7960732dd92d1`.
- No `.env` existed; domains and System Console were empty scaffolds.

The supplied file described an uncommitted repository without a remote. Actual repository
state differed: preserved the existing commit and remote, stopped the conditional baseline
creation steps, and inspected remote state rather than recreating or rewriting history.
`git ls-remote origin` succeeded with no refs; remote was empty before the baseline push.
Local Phase 1 was safe to continue. No architectural conflict was found.

## 3. Git / GitHub

- Origin: `https://github.com/NabilAlsaidaly/Pharma.git`.
- Phase 0 SHA: `d0e72da7e89faea82810808739b7960732dd92d1`.
- `git push -u origin main`: succeeded; created remote main and configured tracking.
- Phase 1 implementation SHA: `7b7426c103093feebc2c2faf1867b11ed2b9695a`.
- Commit: `chore: establish Laravel runtime and CI foundation` (19 source files).
- `git push origin main`: succeeded (`d0e72da..7b7426c main -> main`).
- A subsequent documentation-only commit records these post-commit results without
  amending the implementation commit. Its SHA is available in `git log`.
- No force push, amend, reinitialization or history rewrite was performed.

## 4. Local Environment

Verified `herd --help`, `herd list --raw`, `herd help link`, `herd links` and `herd parked`.
Added only the project link using `herd link sumaiya_pharma`. Confirmed URL:
`http://sumaiya_pharma.test`. Existing links remained intact. No global PHP configuration,
PATH, other Herd sites, Docker or runtime installation was changed.

Created ignored `.env` from `.env.example`; APP_ENV=local, APP_DEBUG=true,
APP_NAME="Sumaya Pharma", APP_LOCALE=ar, APP_FALLBACK_LOCALE=en. Generated APP_KEY
with `php artisan key:generate --no-ansi`; the key is present and never included here.
Sessions use file storage to avoid requiring the untouched default users/session schema.

## 5. MySQL

MySQL97 service is running. User supplied credentials; direct PDO connection succeeded
before writing local environment settings. Host 127.0.0.1, port 3306, database sumaya_pharma,
Laravel driver mysql / pdo_mysql. Created the empty database with utf8mb4 and
utf8mb4_unicode_ci only after verifying the account.

Verified through Laravel DB connection: `SELECT DATABASE()` returned sumaya_pharma;
`SHOW TABLES` returned zero tables. No migrations or seeders were executed. Existing
Laravel migrations are framework scaffolding, not finalized Sumaya schema. Passwords
remain in ignored local `.env` only; `.env.example` contains blank credentials.

## 6. Redis

Native phpredis extension is installed. Direct connection to 127.0.0.1:6379 with a
two-second timeout failed: connection attempt timed out / target did not respond.
No Redis service was found in the local service inspection. Herd `services:list` reports
that Herd Pro is required. Cache uses file and queue uses sync as documented local
fallbacks; Redis is not falsely represented as operational. Session driver remains file.
No Predis, jobs, cache keys, rate limits, locks or Redis infrastructure were implemented.

## 7. UUIDv7 Strategy

Use Laravel `Str::uuid7()` directly for request IDs. Future important Eloquent entities
should use the built-in HasUuids trait, verified in the installed framework to call
Str::uuid7(). ADR-002 documents this convention. Tests verify UUID version 7 and RFC variant.
No custom UUID dependency/service, demonstration entity or schema conversion was added.
The default User model and users migration remain unchanged; Phase 2 owns Identity
persistence decisions.

## 8. API Foundation

- Created empty `routes/api.php`, registered using Application::configure()->withRouting().
- Prefix: api/v1; API middleware group is applied by Laravel.
- Created `app/Http/Middleware/AssignRequestId.php` and `app/Support/ApiErrorResponse.php`.
- Bootstrap prepends the global request-ID middleware and registers exception rendering.
- `/up` is retained without duplication. No production test or business endpoint exists.

## 9. Request ID

Generate a fresh UUIDv7 on every HTTP request; ignore caller-provided X-Request-ID.
Store it in request attributes as request_id, attach it to logging context, and return
X-Request-ID on responses. API errors include the identical request_id. Middleware is
first in the global stack, including maintenance responses. Context keys are cleared
in finally after response handling to avoid carrying request data into subsequent requests.

## 10. Error Contract

API paths `/api` and `/api/*` receive success=false, error.code, error.message,
error.fields and request_id, even without an Accept header. Normal web errors retain
Laravel behavior. Validation fields are populated only for ValidationException; otherwise
fields is an empty JSON object. Internal errors have a generic Arabic message regardless
of debug mode. Exception messages, traces, SQL, paths and secrets are not copied to API
responses. Retry-After / Allow headers are preserved.

| Exception / framework status | HTTP | Code |
| --- | --- | --- |
| ValidationException | 422 | VALIDATION_FAILED |
| AuthenticationException | 401 | UNAUTHENTICATED |
| AuthorizationException converted to AccessDeniedHttpException | 403 | FORBIDDEN |
| ModelNotFoundException converted to NotFoundHttpException; NotFoundHttpException | 404 | RESOURCE_NOT_FOUND |
| HttpException 409 | 409 | RESOURCE_CONFLICT |
| ThrottleRequestsException | 429 | RATE_LIMITED |
| HttpException 503 | 503 | SERVICE_UNAVAILABLE |
| HTTP 400 / 405 / 419 | same | BAD_REQUEST / METHOD_NOT_ALLOWED / SESSION_EXPIRED |
| Unexpected Throwable | 500 | INTERNAL_ERROR |

Laravel preserves explicit authorization HTTP statuses (e.g. deny-as-not-found).
No business-specific errors or success-wrapping abstraction was implemented.

## 11. Logging Foundation

Context: request_id, method, path without query string, environment. Existing stack/single
logging is preserved. No request bodies, passwords, headers, cookies or query values are
added. Future log calls must avoid secrets and redact sensitive exception/integration
data. No permanent request recorder, Sentry or OpenTelemetry is installed.

## 12. Testing

Eight new PHPUnit methods in RuntimeFoundationTest:

1. test_api_not_found_uses_error_contract_without_accept_header
2. test_request_ids_are_distinct_uuidv7_and_ignore_caller_input
3. test_web_errors_keep_framework_html_behavior
4. test_internal_errors_are_safe_even_with_debug_enabled
5. test_validation_errors_include_field_messages
6. test_framework_errors_preserve_status_codes_and_protocol_headers
7. test_api_method_not_allowed_preserves_allow_header
8. test_request_context_is_logged_and_cleared_after_response

Temporary routes exist only in tests. Full suite: **10 passed, 80 assertions**, including
two untouched skeleton tests. Tests are database-independent and require no Redis service.
The stock phpunit.xml SQLite defaults remain unchanged; future business integration tests
must target isolated MySQL behavior, not declare SQLite the production-compatible strategy.

## 13. Static Analysis

Larastan 3.13.0 / PHPStan 2.3.1, level 5, using phpstan.neon. Analyze app and
packages/system-console/src (currently no PHP code). Final result: `[OK] No errors`.
No baseline, mass ignores, Laravel downgrade, PHP downgrade or unrelated package upgrade.
Compatibility verified by Composer dry run, installed package manifest and
[Larastan's official manifest](https://github.com/larastan/larastan/blob/3.x/composer.json).

## 14. Formatting

`php vendor/bin/pint` and `php vendor/bin/pint --test`: passed.

## 15. Security Audit

`composer audit`: `No security vulnerability advisories found.`
No local credentials or generated APP_KEY are tracked/staged.

## 16. CI

`.github/workflows/ci.yml`: pull_request to main and push to main. PHP 8.4 on Ubuntu,
maintained setup-php action, Composer download cache, metadata validation, locked install,
safe test environment/key, platform requirements, Pint, static analysis, tests and audit.
Permissions contents: read, checkout credentials not persisted, concurrency cancellation,
15-minute timeout. Any quality failure fails CI; no continue-on-error or fake passing step.
No production secrets, deployment or Git mutation is part of CI. Local equivalent checks
passed. GitHub accepted the push and created
[Backend CI run 38039282457](https://github.com/NabilAlsaidaly/Pharma/actions/runs/38039282457).
Remote workflow completed with **success**: metadata validation, dependency installation,
platform checks, Pint, static analysis, tests and security audit all passed on PHP 8.4.

## 17. CD Status

**DESIGNED — NOT CONNECTED TO PRODUCTION.** Hosting/runtime/secrets have not been finalized.
`docs/deployment/ci-cd.md` documents release preparation, backups when required, safe
migrations, caches, queue restart, health checks and application release rollback.
Database rollback must not blindly run migrate:rollback. Migration strategy:
Add → deploy compatible code → backfill → switch → remove later. Herd remains local only.

## 18. Files Created

- `.github/workflows/ci.yml`
- `app/Http/Middleware/AssignRequestId.php`
- `app/Support/ApiErrorResponse.php`
- `routes/api.php`
- `tests/Feature/RuntimeFoundationTest.php`
- `phpstan.neon`
- `docs/api/foundation.md`
- `docs/deployment/ci-cd.md`
- `docs/reports/phase-01-runtime-foundation-report.md`
- `.env` (local, ignored; never staged)

Temporary connectivity probe files were removed before staging. Ignored dependency,
autoload, logs, test/static-analysis cache and framework view artifacts are not source files.

## 19. Files Modified

- `.env.example`
- `bootstrap/app.php`
- `composer.json`
- `composer.lock`
- `README.md`
- `docs/README.md`
- `docs/architecture/README.md`
- `docs/architecture/performance-principles.md`
- `docs/adr/ADR-002-uuidv7.md`
- `packages/system-console/README.md`

## 20. Dependencies Added

Direct dev dependency: larastan/larastan ~3.0 (locked 3.13.0), Laravel-aware static analysis.
Transitive additions: phpstan/phpstan 2.3.1 and iamcal/sql-parser 0.7.
Composer resolved exactly three installs, zero updates and zero removals. No runtime
dependency added. Laravel remains 13.35.0.

## 21. Architecture Verification

Modular Monolith and all 13 modules' Domain/Application/Infrastructure/Interfaces
boundaries are preserved, empty and unchanged. No PHP implementation exists in domains
or System Console. Package remains separate, reusable across unrelated Laravel projects,
without Sumaya class dependencies. Business Management Panel remains separate; shared
authentication/guard/user-model decisions are deferred. HealthCheck, MetricProvider,
SettingDefinition and SystemAction are future documented extension points only.

No Google auth, Catalog, Orders, other business features, business migrations, frontend,
management UI or console implementation was added. User model, default migrations,
Blade/assets, package.json and Vite configuration are unchanged. No business API routes
exist. Performance documentation preserves batch queries, database filtering, pagination,
query-driven indexes, EXPLAIN, bounded complexity, justified caching and measurement.

## 22. Validation Commands

| Exact command | Final result |
| --- | --- |
| php --version | PHP 8.4.26 |
| where.exe php | Herd bin/php.bat |
| composer --version | Composer 2.10.2 |
| where.exe composer | Herd bin/composer.bat |
| php -m | pdo_mysql and redis installed |
| php artisan --version | Laravel Framework 13.35.0 |
| php artisan about --no-ansi | local, ar, mysql, file cache/session, sync queue |
| composer validate --strict | ./composer.json is valid |
| composer install --no-interaction --prefer-dist --no-progress | Nothing to install, update or remove; autoload/package discovery succeeded |
| composer check-platform-reqs | all requirements success |
| composer audit | No security vulnerability advisories found. |
| php artisan route:list --no-ansi | 4 framework routes: /, storage GET/PUT, up; no business routes |
| php vendor/bin/pint --test | passed |
| php vendor/bin/phpstan analyse --no-progress | [OK] No errors |
| php vendor/bin/phpstan --version | PHPStan 2.3.1 |
| php artisan test | 10 passed (80 assertions) |
| git diff --check | exit 0, no whitespace errors |
| git check-ignore .env | .env |
| git remote -v | expected Pharma origin for fetch/push |
| git ls-remote origin | succeeded, initially empty refs |
| git push -u origin main | baseline push succeeded |
| git commit -m "chore: establish Laravel runtime and CI foundation" | 7b7426c, 19 files changed |
| git push origin main | implementation push succeeded |
| git status --short | empty after implementation commit and push |
| git status -sb | main...origin/main, synchronized |
| git ls-files -- .env | empty; local environment untracked and ignored |
| herd link sumaiya_pharma | project-local link created |
| herd links | verified URL and preserved other links |
| herd services:list | Herd Pro required |
| php runtime-probe.php (temporary PDO / Redis probe, removed) | MySQL verified; empty DB created; Redis connection failed |
| php runtime-probe.php (temporary Laravel DB probe, removed) | sumaya_pharma, 0 tables |
| curl.exe --noproxy '*' -sS -D - http://sumaiya_pharma.test/api/v1/missing | HTTP 404, JSON contract and matching X-Request-ID |
| curl.exe --noproxy '*' -sS -o NUL -w '%{http_code}' http://sumaiya_pharma.test/up | 200 |
| rg --files app/Domains packages/system-console -g '*.php' | no PHP implementations |
| git diff --name-only -- app/Models database/migrations resources package.json vite.config.js | empty |

Initial Herd/Pint/test commands failed inside the sandbox because the configured PHP
temporary directory was inaccessible; reruns with approval passed, without global settings
changes. Initial Git network access was blocked inside the sandbox; approved retry passed.
An initial Composer constraint was altered by Windows caret handling; a ~3.0 dry run
confirmed compatibility. Overlapping autoload generation produced an invalid generated
file; a single `composer dump-autoload --no-interaction` repaired it. Final analysis and
tests passed after repair. These transient failures are resolved, not hidden.

## 23. Git Status

After implementation commit and successful push, `git status --short` was empty and
`git status -sb` returned `main...origin/main`, without ahead/behind counts. `.env` is
ignored and absent from tracked/staged files. This report's post-commit evidence is saved
in a separate documentation commit; no source changes are pending.

## 24. Risks / Blockers

Redis: EXPECTED working native Redis connection for cache/queues/rate limits/locks;
ACTUAL phpredis exists but 127.0.0.1:6379 is unreachable; BLOCKER local Redis service.
WHAT WAS NOT CHANGED: no system installation, Predis, passwords, business queues/cache.
SAFE NEXT ACTION: provide/start an approved existing Redis service and verified connection,
then verify PING before selecting Redis cache/queue drivers.

Production CD: EXPECTED connected deployment after infrastructure selection; ACTUAL
design only; BLOCKER hosting and runtime not finalized. WHAT WAS NOT CHANGED: no fake
server pipeline or production secrets. SAFE NEXT ACTION: supply production infrastructure
details and connect the documented deployment flow when approved.

## 25. Recommended Phase 2

Identity & Google Authentication
