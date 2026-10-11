# Phase 04: Favorites and Cart Foundation Report

Date: 2026-10-11. Status: LOCAL VERIFICATION PASSED; CI AND DEVELOPMENT ADOPTION PENDING.

Evidence labels: CONFIRMED means observed code/execution; POTENTIAL means a risk hypothesis; NEEDS VERIFICATION means not established.

## 1. Executive Summary

Phase 4 implementation is complete locally; remote compatibility and development adoption are pending. Do not interpret this provisional report as final completion. Owner phase4.1.md authorizes the approved D1-D6 only. No next-phase work or merge.

## 2. Starting State

Verified origin/main and local main baseline 95fb9d7df09c933d8fcdfb9d05db636894f3e565 (merged Catalog PR #1). Existing gate/index changes preserved during safe synchronization. Baseline 76 tests/621 assertions; local MySQL 9.7.0, PHP 8.4.26, Laravel 13.35.0. Development has one real Customer and six empty Catalog tables.

## 3. Owner-Approved Decisions

CONFIRMED: D1 F1+H2, D2 U1+A1, D3 CART-C2, D4 M1+R1, D5 L1+O1, D6 Q1; accepted ADR-017 is the next available ADR. No decision reopened. Q1 is technical safety; commercial maximum remains undefined.

## 4. Design Gate Outcome

Gate header updated APPROVED through phase4.1.md, historical analysis preserved and explicitly distinguished from current approval. [Accepted ADR](../adr/ADR-017-approved-favorites-cart-foundation.md).

## 5. Architecture Overview

Modular monolith/pragmatic hexagonal boundaries. Favorites and Cart own focused Application operations; CartQuantity/CartConflict are Domain rules. CatalogSelectionReader is a narrow Catalog Application port; Eloquent adapter supplies authoritative selections. Thin controllers/resources; query builder for compound Favorite identity and Eloquent Cart models. No generic repository/new framework. See [Favorites](../architecture/favorites.md), [Cart](../architecture/cart.md) and [integration](../architecture/cart-catalog-integration.md).

## 6. Favorites Business Rules

Product-level Favorites, including visible unavailable/unpriced products. Own idempotent add/remove, duplicate additions preserve timestamp. F1 retains hidden rows but excludes list/count and gives indistinguishable hidden/unknown membership 404. Reactivation restores visibility. No GET cleanup.

## 7. Cart Business Rules

Lazy one persistent Cart/customer; exact selected Product/Variant, no auto-default. Strict positive price in at least one currency and available/on_request for admission, increases and changes. Retained hidden/unpriced/unavailable/structurally invalid items remain, allowing reduction/removal. No immediate purchase representation.

## 8. Database Schema

Three tables favorites, carts and cart_items; approved additive product_variants UNIQUE(id,product_id). Favorite composite pair, UUIDv7 Cart/Item, unsigned BIGINT version serialized as string, signed INT quantity, required timestamps, generated stored selection key. [Schema](../database/favorites-cart-schema.md).

## 9. Foreign Keys

Restrictive Customer/Product/Cart FKs plus exact (variant_id,product_id) -> variants(id,product_id). Real MySQL tests reject orphans, cross-Product pairing, referenced Variant reparent/deletion; NULL Variant still requires a valid separate Product parent. No cascade or synthetic Variant.

## 10. Unique Constraints

One Cart/customer, one Favorite pair, one normalized Cart selection. Tests prove duplicate simple NULL and duplicate Variant rejection, different Variant acceptance; database is final integrity backstop.

## 11. Nullable Variant Uniqueness Solution

STORED CHAR(36) selection_key = COALESCE(variant_id,''); unique(cart_id,product_id,selection_key). Real raw concurrent duplicate NULL INSERTs use independent connections, overlap while FK parent lock is held, then one commits and the other fails with MySQL 1062. Local 9.7 confirmed; CI 8.4 NEEDS VERIFICATION before development adoption.

## 12. Customer Ownership and IDOR

Owner derives only from authenticated customer guard, never body/query. Cart item operations traverse owned Cart; Favorites always include pair Customer ID. Another Customer cannot read/change/delete a stolen CartItem or Favorite pair. Missing/non-owned removal is identically harmless. Forged Customer/Cart/prices/totals/stock fields rejected.

## 13. Authentication and CSRF

Existing auth:sanctum + EnsureActiveCustomer on all eight private routes; stateful Sanctum/customer guard unchanged. Real CSRF middleware tests override only its unit-test bypass: all five mutations fail without token; valid token succeeds. Guest 401, suspended 403; bearer unsupported. Actual session-cookie login/logout/login using fake external OAuth preserves both collections. Existing Google OAuth live integration was not repeated; credentials/env untouched. Request ID and existing envelopes preserved.

## 14. Favorites API Endpoints

GET /api/v1/favorites; GET /api/v1/favorites/check; POST /api/v1/favorites; DELETE /api/v1/favorites/{productId}. Full schemas/status/pagination in [API](../api/favorites.md). First addition 201, duplicate 200; visible list uses compact existing ProductResource.

## 15. Cart API Endpoints

GET /api/v1/cart; POST /api/v1/cart/items; PATCH /api/v1/cart/items/{itemId}; DELETE /api/v1/cart/items/{itemId}. Full [API](../api/cart.md) documents empty/hidden/partial totals, merge identity and safe conflict reasons. Initial add 201, repeat 200. No extra endpoints. Route list contains exactly 20 API routes: 4 Identity, 8 Catalog, 8 personal collections.

## 16. Product and Variant Validation

Same-parent explicit Variant required when Product has Variants; no Variant on simple Product. Wrong parent/missing Variant is 422, unknown/hidden initial Product 404. Admission uses exact price owner and existing AvailabilityStatus V1 effectiveWith. Structural change retains selection_invalid rather than picking a default.

## 17. Quantity and Merge Logic

Strict JSON integer 1..2147483647; no clamp or commercial maximum invented. Central CartQuantity rules detect safe sums before mutation. M1 preserves source quantity and atomically sums occupied destination/deletes source, returning merged plus surviving/removed IDs. Overflow/constraint errors roll back all writes. Actual 200-selection boundary and unsigned version overflow rollback tested.

## 18. Price Evaluation

Current exact decimal price, not Variant range; existing brick/math BigDecimal only, no float money. Maximum DECIMAL(18,2) 9999999999999999.99 times maximum quantity yields exact 21474836469999999978525163.53. No persisted price baseline: current reevaluation implemented; historical price-change comparison unavailable/deferred.

## 19. Currency and Estimated Total Strategy

USD/SYP independently: priced_subtotal, estimated_total nullable on any exclusion, complete, excluded_item_count and reason counts. Reasons inaccessible/invalid/unavailable/missing_price/zero_price ordered per item. Whole-Cart evaluation despite per_page=1. On-request priced items contribute with requires_request_count. Empty 0.00 explicit and never a free purchase.

## 20. Unpriced Product Handling

Missing remains null, zero remains decimal 0.00 and never admits free purchase. At least one positive currency required; missing other currency makes that currency estimate incomplete. Saved zero/missing records remain, reducible/removable. Favorites accept unpriced visible products.

## 21. Availability Handling

Available/on_request positive selections accepted; unavailable rejected for admission/increase/destination, retained and reducible/removable. Parent/Variant effective availability V1 reused. On_request counted/flagged, no is_purchasable promise or inquiry resolution.

## 22. Hidden Product and Brand Handling

F1 preserves pairs invisibly; H2 exposes only own item ID/quantity and generic selection_inaccessible with null Product/Variant/pricing. Tests assert hidden UUID/metadata absent, increases/changes conflict, reduction succeeds and reactivation restores current totals. GET never modifies data.

## 23. Concurrency Protection

Deterministic Customer -> Cart -> sorted Product/Variant/Brand -> affected item locks, short MySQL transactions, three recognized contention attempts. Existing Customer mutex serializes lazy Cart creation and all aggregate writes. Optimistic version rejects lost absolute PATCH edits; unique/FK/CHECK constraints backstop. No Redis, only firstOrCreate or in-memory lock.

## 24. Idempotency and Retry Behavior

POST additions intentionally NON-idempotent, every accepted repeat increments. PATCH requires canonical string expected_version, exactly one absolute quantity or Variant; stale 409, no-op does not advance. DELETE idempotent/owned. Frontend must disable pending duplicate submission, avoid automatic uncertain POST retry, GET/reconcile before user-confirmed retry. No exactly-once claim or R2 infrastructure.

## 25. SQL Query Review

CONFIRMED measured operation counts: Favorite listing 4 at 2 and 50 records; check 2, add 7, remove 2. Cart reads 6 at 2 and 50 records; add 11, absolute quantity update 10, Variant change 10, totals read 6, remove 5. Read measurements capture tested HTTP SQL with existing auth context; mutation counts isolate Application operations and exclude authentication, include locking statements, exclude PDO transaction control. Tests prevent lazy-loading regressions. Queries do not grow per item; all Cart items are evaluated up to API admission cap 200. No production latency/throughput benchmark.

## 26. Index Justifications

Pair PK supports own membership/deletion; favorite Customer/time/Product supports ordering; Cart Customer unique supports owner lookup; normalized selection unique enforces same-selection integrity; Cart/time/id supports ordered items; parent FKs supply required reverse indexes. Composite Variant parent unique is expressly authorized. [Index table](../database/favorites-cart-schema.md).

## 27. EXPLAIN Results Where Applicable

Real local EXPLAIN for owned Cart ID lookup uses carts_customer_id_unique, type const, rows=1, Using index (key_len 144 under utf8mb4). Existing Catalog EXPLAIN remains successful and uses status/time/id and Variant parent/sort indexes. Exact plan choices vary with dataset/server; not a blanket claim that pagination never filesorts.

## 28. Tests Added

58 new executable cases across CartApiTest (including invalid-input data provider), FavoritesApiTest, CollectionsSchemaTest, CollectionsSecurityTest, CollectionsPerformanceTest and CollectionsConcurrencyTest; shared guarded PersonalCollectionsTestCase and independent collection-worker.php. Tests aggregate related required cases without weakening existing suites. Coverage: constraints, null/different Variant uniqueness, real concurrency, partial/whole totals, decimal maxima, ownership, hidden retention, current price/availability, guest/suspension, actual CSRF/session behavior, CORS, limits, overflow, pagination, constant query counts and no unauthorized schema/routes.

## 29. Total Tests and Assertions

CONFIRMED local php artisan test --compact: 134 passed, 1014 assertions, 28.89 seconds. Baseline 76 tests unchanged; 58 new cases. Isolated sumaya_pharma_test MySQL only. CI counts pending actual logs.

## 30. Real Concurrency Test Results

Eight executable tests use independent Symfony Process PHP workers, independent MySQL connection IDs and committed fixtures under guarded DatabaseMigrations. Nine overlapping write pairs observed with SHOW FULL PROCESSLIST while parent holds a row barrier, both sessions in blocked SQL, then released to inspect committed final state. Includes initial Cart/null add sum, existing Variant increments, stale absolute PATCH, remove/update, competing Variant changes, merge versus destination add, Favorite duplicates/add-remove, raw nullable duplicate FK INSERT. Eighth test independently commits changed Catalog price/availability during a held repeatable-read snapshot and proves consistent old results then current new results. Coordination ready files cleared between repeated pairs; earlier harness stale-ready failure corrected, not tests weakened. Final full suite all passed.

## 31. MySQL Integration Results

Local server actual VERSION() 9.7.0 and REPEATABLE-READ. All added migrations/constraints tested repeatedly on guarded isolated MySQL. Development actual database sumaya_pharma verified before pretend: only Phase4 migration pending. Development forward adoption and CI MySQL 8.4 compatibility NEEDS VERIFICATION at this provisional checkpoint. No SQLite substitution.

## 32. Identity Regression Results

All 28 existing Identity tests pass in the full suite, unchanged; no Google/Sanctum implementation refactor. Additional session persistence, guest/suspended ownership and real CSRF checks passed. Existing env, OAuth credentials, secure HttpOnly SameSite policy unchanged. Fake external OAuth is integration evidence, not live Google provider evidence.

## 33. Catalog Regression Results

All existing Catalog tests pass unchanged, including P1/C2/V1/S1, public visibility/images, constraints and query regressions. Products list remains four SQL queries at 2 vs30 products. Only approved parent unique, focused read port/adapter and honest Brand status PHPDoc added. No prices/status/availability rewrites.

## 34. PHPStan Result

CONFIRMED vendor/bin/phpstan analyse --no-progress: no errors, existing level 5. No new baseline/suppression or reduced scope. Accurate string-version and enum annotations corrected inference; no business behavior altered.

## 35. Pint Result

CONFIRMED vendor/bin/pint applied formatting; vendor/bin/pint --test passed on final local implementation. No formatting failure ignored.

## 36. Composer Audit Result

CONFIRMED composer validate --strict valid; composer check-platform-reqs all pass; composer audit no security vulnerability advisories. Existing lockfile/dependency metadata unchanged.

## 37. GitHub CI Result

NEEDS VERIFICATION: PR creation and exact pushed-head Backend CI on MySQL 8.4 pending. Workflow unchanged; mysql:8.4, PHP8.4, Composer validation/platform/audit, Pint, PHPStan and full tests are mandatory.

## 38. Customer Data Preservation Evidence

CONFIRMED pre-migration actual sumaya_pharma: Customer count1, nine columns and all-row hash bfd1fec4fa98a4844988f217c4a4a887a41861e2f5559397fea314cf8184ab6c matches Phase3/gate. No personal fields or secrets printed. Post-forward comparison pending before completion.

## 39. Catalog Data Preservation Evidence

CONFIRMED pre-migration all six Catalog tables count0, each SHA256 4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945. Post-forward comparison pending. Customer/Catalog fixtures exist only in test DB; development is not seeded.

## 40. PAY-001 Compliance

CONFIRMED no Checkout/Order/Payment implementation. Cart estimates confer no promise; future intent snapshots, payment/deposit verification, availability recheck and atomic Order/stock allocation remain separately authorized work. [Compatibility](../architecture/cart-checkout-compatibility.md).

## 41. No Inventory Reservation Confirmation

CONFIRMED no reservation, deduction, inventory allocation, stock columns/endpoints or model introduced. Catalog availability is descriptive. Schema tests assert no Orders/Payments/reservations/stock_movements.

## 42. System Console Separation Confirmation

CONFIRMED packages/system-console unchanged, no imports of Sumaya business classes or commercial policy. No Business Management Panel/frontend feature.

## 43. Files Created

- `app/Domains/Cart/Application/CartEvaluator.php`
- `app/Domains/Cart/Application/CartOperations.php`
- `app/Domains/Cart/Application/CartSnapshot.php`
- `app/Domains/Cart/Domain/Exceptions/CartConflict.php`
- `app/Domains/Cart/Domain/Rules/CartQuantity.php`
- `app/Domains/Cart/Infrastructure/Persistence/Models/Cart.php`
- `app/Domains/Cart/Infrastructure/Persistence/Models/CartItem.php`
- `app/Domains/Cart/Interfaces/Http/Controllers/CartController.php`
- `app/Domains/Cart/Interfaces/Http/Resources/CartItemResource.php`
- `app/Domains/Catalog/Application/Ports/CatalogSelectionReader.php`
- `app/Domains/Catalog/Infrastructure/Persistence/Queries/EloquentCatalogSelectionReader.php`
- `app/Domains/Favorites/Application/FavoriteOperations.php`
- `app/Domains/Favorites/Interfaces/Http/Controllers/FavoriteController.php`
- `app/Http/Middleware/LimitPersonalMutationBody.php`
- `app/Http/Requests/PersonalCollectionRequest.php`
- `config/customer_collections.php`
- `database/migrations/2026_10_11_000001_create_favorites_and_cart_tables.php`
- `docs/adr/ADR-017-approved-favorites-cart-foundation.md`
- `docs/api/cart.md`
- `docs/api/favorites.md`
- `docs/architecture/cart-catalog-integration.md`
- `docs/architecture/cart-checkout-compatibility.md`
- `docs/architecture/cart.md`
- `docs/architecture/favorites.md`
- `docs/database/favorites-cart-schema.md`
- `docs/reports/phase-04-favorites-cart-design-gate.md`
- `docs/reports/phase-04-favorites-cart-foundation-report.md`
- `tests/Feature/Collections/CartApiTest.php`
- `tests/Feature/Collections/CollectionsConcurrencyTest.php`
- `tests/Feature/Collections/CollectionsPerformanceTest.php`
- `tests/Feature/Collections/CollectionsSchemaTest.php`
- `tests/Feature/Collections/CollectionsSecurityTest.php`
- `tests/Feature/Collections/FavoritesApiTest.php`
- `tests/PersonalCollectionsTestCase.php`
- `tests/Support/collection-worker.php`

## 44. Files Modified

- `app/Domains/Catalog/Infrastructure/Persistence/Models/Brand.php`
- `app/Providers/AppServiceProvider.php`
- `app/Support/ApiErrorResponse.php`
- `bootstrap/app.php`
- `config/cors.php`
- `docs/README.md`
- `docs/architecture/README.md`
- `docs/architecture/module-boundaries.md`
- `docs/architecture/payment-before-order-rule.md`
- `routes/api.php`

## 45. Files Deleted

None. Existing module placeholders and prior reports retained.

## 46. Dependencies Added or Removed

None. Existing transitive brick/math 1.0.0 is reused; composer.json, composer.lock and package metadata unchanged. No Redis prerequisite, SQL tools service or browser dependency introduced.

## 47. Commits

Pending feature implementation commit and final evidence documentation commit. Baseline 95fb9d7df09c933d8fcdfb9d05db636894f3e565. No force push/reset or automatic merge.

## 48. Branch and Pull Request

feature/favorites-cart-foundation based on current verified main; PR into main pending. Final ready-for-owner-review requires green exact-head CI and preservation evidence; no merge authorized.

## 49. Limitations and Risks

CONFIRMED limitations: no historical price comparison, commercial quantity maximum undefined, POST uncertain retries unsafe, MySQL RR required for consistent snapshots, 200 selection cap affects UX, hidden rows retained, restrictive physical deletion and no future privileged imports implemented. POTENTIAL risks: hot Catalog rows/Customer writer serialization contention, file-cache throttling consistency across future multiple servers, default thresholds need production review. NEEDS VERIFICATION outside Phase4: real production load/topology, future management lock order, live Google/manual frontend UX and separately approved commercial/Checkout policies. None permits claiming purchase authorization.

## 50. Recommended Next Phase

STOP after Phase4. Owner reviews PR without automatic merge. Recommend separately scoped Checkout Intent/PAY-001 design gate and commercial purchase limits, inquiry handling, snapshots and payment verification before implementing any next-phase functionality.
