# CI and deployment foundation

CD STATUS: DESIGNED, NOT YET CONNECTED TO A SERVER

## Continuous integration

`.github/workflows/ci.yml` runs on pull requests to main and pushes to main,
with read-only contents permission, cancellation of superseded runs, and PHP 8.4.
It validates Composer metadata, installs locked dependencies, generates a disposable
test key, checks platform requirements, runs Pint, Larastan/PHPStan, PHPUnit and
Composer audit. Every failing check fails the job. No production secrets or deployment
steps are present. Composer downloads are cached by OS, PHP version and lock hash.

Reproduce locally using Herd PHP:

```text
composer validate --strict
composer install --no-interaction --prefer-dist --no-progress
composer check-platform-reqs
vendor/bin/pint --test
vendor/bin/phpstan analyse --no-progress
php artisan test
composer audit
```

On Windows use `php vendor/bin/pint` and `php vendor/bin/phpstan` if needed.
Create `.env` from `.env.example` and run `php artisan key:generate` only on first setup.
Do not use the skeleton `composer setup` script: it runs migrations and frontend commands
outside the approved scope. Identity integration tests require the isolated MySQL
database `sumaya_pharma_test`; `.env.testing` is ignored and must use its own DB settings.
PHPUnit forces that database name; IdentityTestCase also checks environment and connection
before RefreshDatabase can reset tables. Never point tests at development or production.
CI provides an official `mysql:8.4` service with a health check, a disposable database
and dummy CI-only credentials. Google adapter tests mock HTTP; no real provider secrets
or Redis service are required. Production deployment remains unconnected.

## Future deployment flow

Merge / push to main → CI → all checks pass → release preparation → backup when required
→ deploy release → run safe migrations → optimize Laravel caches → restart queue workers
→ health checks → mark deployment successful.

Health check failure or critical post-deploy error → rollback application release.
Never blindly run `migrate:rollback` during rollback. Preserve database compatibility
with the previous application release using:

Add → deploy compatible code → backfill → switch → remove later.

Use additive schema changes first, bounded resumable backfills, and defer destructive
changes until older releases no longer need the old schema. Validate backups and recovery
procedures before migration risk warrants them. Health checks must validate the actual
deployed runtime and its required dependencies; `/up` is the existing framework endpoint.

Production hosting, runtime, release location, secrets, database, Redis and queue/scheduler
process supervision have not been selected. Wire CD only after those details and recovery
ownership are known. No server, provider, domain or credential is invented here.
Herd is local development infrastructure and does not define the production runtime.
