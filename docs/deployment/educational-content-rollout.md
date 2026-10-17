# Educational Content testing and future activation

Phase6G delivers source, additive schema, Owner UI, public read contracts and tests. It does not deploy, merge, provision a real Owner, change.env credentials or apply development/production migrations. Next.js and Phase6H remain deferred.

## Isolated verification

Use existing vendor/locked node installation. PHPUnit config forces testing/MySQL/sumaya_pharma_test; IdentityTestCase and independent race fixture verify configured AND physical database before any reset/migration. Workers verify it again. Never substitute SQLite or a database whose name only resembles the testing DB. Do not run suites concurrently on the shared physical testing DB. Race tests use independent PHP processes/connections and actual blocked locking SQL, then reverse migrations only on the guarded test target. Temporary media roots must resolve inside framework/testing before recursive cleanup.

Commands after physical guard inspection: `php -d memory_limit=512M vendor/phpunit/phpunit/phpunit`, `php vendor/bin/pint --test`, `php vendor/bin/phpstan analyse --no-progress --memory-limit=512M`, Composer validate--strict/platform/audit, `npm ci --no-audit --no-fund` and `npm run build`. No weakened configs, skipped historical tests, suppressions or new dependencies. Local MySQL9.7 and CI8.4 cover actual DDL/FKs/CHECK/rollback; remote CI must match final PR head and verified main. Report actual totals and query plans, not guessed capacity.

Synthetic browser preview is separate guarded testing-only native-CSRF server with private fixture media/file cookie/cache; no test login route enters application source. Before/after fixture business table hashes and real development rows/DDL/.env fingerprints must match. Stop browser/server before further MySQL suites or final delivery. Preview helpers/screenshots/JUnit/logs stay ignored under storage/framework/testing and output/playwright.

## Separate authorized rollout

1. Owner review and explicit merge/activation authorization; verify actual physical destination, backup and restore procedure.
2. Apply all outstanding earlier migrations in their chronological order, then `2026_10_17_000002_create_educational_content.php`. Do not assume Phase6E/6F schema exists because source was merged. Coordinate source/schema; missing schema intentionally503.
3. Real Owner provisioning separately; never copy fixture credentials/data/images into live environments.
4. Verify PHP8.4/GD supported formats, MySQL compatible checks/indexes, writable PRIVATE local disk, TLS/cookie/domain/CORS/prefix/session settings and Vite build. No public storage symlink for educational images.
5. Check proxy/PHP post/upload/pixel/memory limits against source5MiB/aggregate10MiB and normal256KiB, plus shared session/attempt limiter across instances and shared private bytes if multi-node. Do not add Redis as an unapproved prerequisite.
6. Set operational retention, orphan reconciliation, monitoring and recovery policy. Generic logs omit content. History/bytes grow; down destroys educational history and is not an automatic rollback plan. No clinical validity, legal disclaimer, capacity or full assistive-tech certification is implied by technical tests.

The approved editorial process still requires Owner-authored content, sources and explicit acknowledgments. No seeded advice, professional identity or medical disclaimer. A later frontend renders the exact Laravel contract without inventing publication/health/Catalog rules. No checkout/payment/stock/shipping/notification action is activated by Article publication.
