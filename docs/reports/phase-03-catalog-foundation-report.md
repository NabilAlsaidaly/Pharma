# Phase 03 - Catalog Foundation Report

Date: 2026-10-10. Status: PHASE 3 COMPLETE.

## 1. Executive Summary

PHASE 3 COMPLETE. Implemented the owner-approved read-only Catalog on Laravel 13.35.0 / PHP 8.4.26. Six new Catalog tables, five UUIDv7 entities, eight public GET routes, SQL filtering, safe visibility, bounded pagination and measured query behavior. Local verification: 76 tests / 621 assertions, PHPStan, Pint and Composer checks passed. GitHub Actions implementation run succeeded on MySQL 8.4. No next phase started.

## 2. Starting State

Baseline main commit: `8d9e355c09bfe531ab09bd45cfc8f1fb4ed9927e`. Identity and Google authentication were implemented; development MySQL 9.7.0 contained one real customer. The three existing design-gate/PAY-001 documents were preserved. Existing local Google configuration, HTTPS APP_URL and runtime settings were retained. Docker was installed but its engine was unavailable; actual MySQL 8.4 compatibility was established through the existing CI service.

## 3. Approved Design Decisions

Owner approval: phase3.1.md; accepted [ADR-016](../adr/ADR-016-approved-catalog-foundation.md).

| Decision | Implemented semantics |
| --- | --- |
| P1 | Product-only prices without variants; independent variant prices when variants exist; no inheritance or artificial default variant; conflicting ownership rejected by model persistence. |
| C2 | USD/SYP optional, including both null for active products; null remains null; DECIMAL(18,2) strings; no conversion. Zero does not authorize purchase. |
| V1 | available/on_request/unavailable; effective unavailable wins, then on_request, then available; parent declaration stays independently configured. |
| S1 | active/hidden; hidden brands block their products everywhere; hidden concerns are excluded without hiding otherwise visible products. |

## 4. Design Gate Result

APPROVED. [Design gate](phase-03-catalog-design-gate.md) records the owner decision and preserves the original alternatives as historical material. The earlier C1 recommendation was superseded by explicit C2 approval. Generated nullable primary-image uniqueness was tested on local MySQL 9.7 and CI MySQL 8.4 before adoption.

## 5. Unresolved Decisions

No unresolved decision blocks this approved scope. Purchase policy beyond PAY-001, administrative editing/deletion workflows, operational storage provisioning and production capacity targets remain future owner decisions.

## 6. Database Tables Created

Migration `2026_10_10_000002_create_catalog_tables` creates brands, products, product_variants, product_images, concerns and concern_product. It was applied forward-only to development, batch 2; the existing customers migration remains batch 1. Catalog tables remain empty in development. No seed/factory data was inserted there.

## 7. Column Definitions

[Verified schema](../database/catalog-schema.md) lists every actual MySQL column, type, nullability, default and generation expression from post-migration metadata. Five entity IDs are char(36) UUIDv7; their timestamps are required. Pivot identity is (product_id, concern_id), without timestamps. Prices are nullable DECIMAL(18,2). Image paths are varchar(2048), with a stored nullable char(36) generated primary_product_id. No inventory quantity, order, payment or reservation column exists.

## 8. Foreign Keys and Constraints

Five restrictive FKs: products.brand_id; variants.product_id; images.product_id; pivot.product_id and pivot.concern_id. All ON DELETE/UPDATE RESTRICT. Unique slugs on brands/products/concerns; unique pivot pair; unique nullable generated image primary_product_id. Eleven CHECK constraints enforce three active/hidden statuses, two availability enums, four non-negative nullable prices and two 0/1 flags. Real MySQL failures are asserted for duplicates, orphans, invalid states, negative values and required nulls. Price ownership spans tables and is enforced by model save transactions with parent locks; it is not a database CHECK. Future raw SQL/bulk writes must preserve that rule.

## 9. Indexes and Their Query Justifications

Actual index names and columns are in the schema and [query review](../architecture/catalog-query-design.md). Brand/concern lists use status,sort_order,id; product lists status,created_at,id; brand products brand_id,status,created_at,id; recommendations status,is_recommended,created_at,id; variants/images product_id,sort_order,id; pivot indexes both directions. Slugs have unique indexes. FK prefixes avoid redundant single-column indexes. Contains-name search is bounded but cannot be claimed to use a B-tree substring-search index.

## 10. Catalog Module Architecture

Modular monolith with pragmatic hexagonal boundaries. HTTP request/controller/resources handle validation and presentation; CatalogFilters and PublicCatalogReader form the application boundary; enums and price normalization hold domain rules; Eloquent models/query adapter own persistence. One AppServiceProvider binding connects the reader port to the Eloquent adapter. No Identity dependency, repository-per-table scaffolding, service bus, caching layer or purchasing service.

## 11. Classes Created and Responsibilities

| Class / trait / interface | Responsibility |
| --- | --- |
| `app/Domains/Catalog/Application/DTOs/CatalogFilters.php` | immutable validated filter values |
| `app/Domains/Catalog/Application/Ports/PublicCatalogReader.php` | public read application port |
| `app/Domains/Catalog/Domain/Enums/AvailabilityStatus.php` | declared and effective V1 availability |
| `app/Domains/Catalog/Domain/Enums/CatalogStatus.php` | approved active/hidden enum |
| `app/Domains/Catalog/Domain/Exceptions/ConflictingPriceOwnership.php` | explicit ownership failure |
| `app/Domains/Catalog/Domain/Rules/CatalogPrice.php` | decimal-safe string normalization and rejection of precision loss |
| `app/Domains/Catalog/Infrastructure/Persistence/Concerns/HasCatalogPrices.php` | shared price mutators |
| `app/Domains/Catalog/Infrastructure/Persistence/Models/Brand.php` | UUIDv7 brand persistence and visibility |
| `app/Domains/Catalog/Infrastructure/Persistence/Models/Concern.php` | UUIDv7 concern persistence and visibility |
| `app/Domains/Catalog/Infrastructure/Persistence/Models/Product.php` | product relations, visibility, primary-image selection and locked price ownership |
| `app/Domains/Catalog/Infrastructure/Persistence/Models/ProductImage.php` | UUIDv7 image metadata persistence |
| `app/Domains/Catalog/Infrastructure/Persistence/Models/ProductVariant.php` | UUIDv7 variant persistence and locked parent price ownership |
| `app/Domains/Catalog/Infrastructure/Persistence/Queries/EloquentPublicCatalogReader.php` | indexed SQL reads, eager loading, summaries and pagination |
| `app/Domains/Catalog/Interfaces/Http/CatalogImageUrl.php` | safe configured public-disk URL presentation |
| `app/Domains/Catalog/Interfaces/Http/Controllers/PublicCatalogController.php` | eight thin public read handlers and response envelopes |
| `app/Domains/Catalog/Interfaces/Http/Requests/CatalogListRequest.php` | filter/sort/page validation and DTO creation |
| `app/Domains/Catalog/Interfaces/Http/Resources/BrandResource.php` | public brand serialization |
| `app/Domains/Catalog/Interfaces/Http/Resources/ConcernResource.php` | public concern serialization |
| `app/Domains/Catalog/Interfaces/Http/Resources/ProductImageResource.php` | safe image URL serialization |
| `app/Domains/Catalog/Interfaces/Http/Resources/ProductResource.php` | product list/detail and optional price/range serialization |
| `app/Domains/Catalog/Interfaces/Http/Resources/ProductVariantResource.php` | independent prices and effective availability serialization |

## 12. API Endpoints

All paths below are GET (+ framework HEAD), public, prefixed `/api/v1`:

1. `/brands`
2. `/brands/{slug}`
3. `/brands/{slug}/products`
4. `/products`
5. `/products/recommended`
6. `/products/{slug}`
7. `/concerns`
8. `/concerns/{slug}/products`

[API contract](../api/catalog.md) specifies fields and examples. Success responses retain success/message/data; lists also contain links/meta. Detail lookup failures use existing safe 404 handling; bad filters use the existing 422 error contract. Recommended route precedes the product slug route. No extra Catalog endpoint or public write method exists.

## 13. Public Visibility and Security

SQL public scope requires both active product and active parent brand on every product read path, including direct URLs, recommendations, brand/concern paths and search. Hidden concerns are absent from lists/detail associations and their route is 404. Raw image paths and generated helper keys are not returned. Queries use bindings; literal LIKE wildcards are escaped. Public POST/PUT/PATCH/DELETE attempts are tested as 404/405. Existing Identity/signed framework storage routes were not changed.

## 14. Product Pricing Representation

Product-only pricing exposes decimal strings/null. Variant products expose independent variant prices on detail and per-currency min/max summaries on lists; parent price fields are null. Aggregates ignore null, with all-null ranges remaining null. Both missing prices and configured "0.00" remain informational values. No floating-point money arithmetic, conversion or inferred minimum selling price. Future selected-currency purchase must require a configured valid price greater than zero; eligibility and Checkout are not implemented.

## 15. Variant Representation

No implicit/default variant. Ordered by sort_order then UUID. Parent declared availability is neither derived nor overwritten. Effective availability follows all nine V1 combinations, tested exhaustively. Model save locks serialize variant creation/reparenting and parent-price updates to reject conflicting ownership. No structural editing API exists.

## 16. Image Storage/Metadata Behavior

At most one explicit primary per product is enforced by generated unique key; unlimited secondary rows use null. Read selection prioritizes primary, then smallest sort_order/id, with deterministic fallback and null when absent. URLs use the existing configured public disk, accepting safe raster-image relative paths and rejecting absolute/traversal/encoded/executable references. No placeholder, file existence query per image, upload API, new storage service or storage-link activation. Metadata can refer to a missing file; future management must validate physical objects.

## 17. Concerns and Pivot Relationships

Many-to-many concern_product owns only the unique FK pair. Both directions indexed; deletion restricted. Public detail associations load active concerns only. Hidden concern metadata does not hide an otherwise visible product.

## 18. Search and Filtering

Product-name search supports Arabic and literal special characters. Filters: q, brand slug, concern slug, parent availability_status selection via availability, recommended. SQL predicates compose without PHP collection filtering. Slugs validated; recommended accepts approved boolean forms. Unknown parameters are ignored and omitted from page links. Supported sorts: newest, name_asc, name_desc, all with UUID tie-breaker.

## 19. Pagination

Default per_page 12, accepted 1..48; page integer >=1. Deterministic ordering and database LIMIT/OFFSET; empty/out-of-range pages return safe empty data. Pagination links retain validated filters/sort only. No unbounded public collection response.

## 20. Performance / Query Review

Measured SQL statements with MySQL query logging and lazy-loading prevention:

| Path | Statements |
| --- | ---: |
| Brands list | 2 |
| Brand detail | 1 |
| Products list | 4 |
| Product detail | 6 |
| Brand products | 5 |
| Concern products | 5 |
| Recommended products | 4 |
| Product search | 4 |
| Concerns list | 2 |

Products with related brand/image/variant data: 2 products and 30 products both used 4 statements. Detail remained 6 after adding 10 unrelated products. List selects omit long detail text and use five indexed correlated variant summaries. Actual EXPLAIN FORMAT=TRADITIONAL on the local fixture showed brand slug/PK const lookups, products_status_created_at_id_index ref access (estimated 21 rows), and product_variants_product_id_sort_order_id_index ref access for the correlated subqueries (estimated 5). Counts and plans are evidence for the fixtures, not production throughput or latency benchmarks. No Redis caching.

## 21. Test List

38 new tests, individually listed below. Fixture helpers use guarded test-only MySQL setup.

### tests/Feature/Catalog/CatalogImageTest.php (4)

- `test_primary_flag_beats_sort_order_and_fallback_is_deterministic`
- `test_safe_metadata_serializes_configured_public_url_without_raw_paths`
- `test_unsafe_absolute_traversal_encoded_and_executable_references_never_expose_a_url`
- `test_absent_image_metadata_returns_null_without_placeholder_or_filesystem_lookup`

### tests/Feature/Catalog/CatalogPersistenceTest.php (13)

- `test_all_five_entity_identifiers_are_uuidv7_and_relations_have_correct_owners`
- `test_slug_uniqueness_is_global_for_each_entity_type`
- `test_real_foreign_keys_reject_orphan_products_variants_images_and_pivot_sides`
- `test_pivot_pair_is_unique_and_restrictive_deletion_preserves_dependents`
- `test_required_fields_reject_null_and_approved_nullable_fields_allow_null`
- `test_decimal_precision_optional_prices_and_zero_are_preserved_without_conversion`
- `test_negative_prices_and_invalid_states_are_rejected_by_database_checks`
- `test_price_mutators_reject_floats_negatives_and_precision_loss`
- `test_variant_creation_cannot_conflict_with_parent_prices`
- `test_parent_price_updates_cannot_conflict_with_existing_variants`
- `test_primary_image_uniqueness_is_database_enforced_and_secondary_images_are_unlimited`
- `test_availability_precedence_covers_every_parent_variant_combination`
- `test_schema_indexes_checks_and_customer_schema_are_intact_without_stock_tables`

### tests/Feature/Catalog/CatalogQueryPerformanceTest.php (3)

- `test_product_listing_queries_do_not_grow_with_brand_image_or_variant_count`
- `test_every_read_path_has_bounded_queries_and_detail_does_not_load_unrelated_products`
- `test_filters_and_limits_execute_in_sql_and_explain_is_observed_on_mysql`

### tests/Feature/Catalog/PublicCatalogApiTest.php (17)

- `test_guests_can_browse_all_eight_endpoints_without_customer_authentication`
- `test_active_products_with_no_prices_are_visible_and_have_no_implicit_variant`
- `test_product_only_prices_are_decimal_strings_and_missing_currency_never_becomes_zero`
- `test_variant_prices_are_independent_and_listing_ranges_ignore_null_without_inheritance`
- `test_variant_products_with_all_missing_prices_have_null_ranges_and_remain_visible`
- `test_variant_availability_obeys_parent_precedence_without_overwriting_parent`
- `test_hidden_products_never_leak_through_any_product_browse_path`
- `test_hidden_brand_blocks_its_active_products_and_direct_urls`
- `test_hidden_concerns_are_not_exposed_and_do_not_hide_otherwise_visible_products`
- `test_brand_concern_availability_and_recommendation_filters_are_composable`
- `test_arabic_search_matches_names_and_literal_wildcards_cannot_broaden_results`
- `test_pagination_is_bounded_and_empty_pages_are_safe`
- `test_invalid_pagination_filters_and_sorting_use_safe_validation_contract`
- `test_pagination_links_keep_validated_filters_and_exclude_unknown_parameters`
- `test_name_sort_has_deterministic_uuid_tie_breaker`
- `test_detail_only_returns_its_own_ordered_variants_images_and_visible_concerns`
- `test_public_catalog_has_exactly_eight_read_routes_and_rejects_every_write_method`

### tests/Feature/PrimaryImageConstraintCompatibilityTest.php (1)

- `test_nullable_generated_unique_key_allows_secondary_images_but_rejects_two_primaries`

## 22. Test Count and Assertions

Final local `php artisan test --compact`: **76 passed, 621 assertions**. Baseline: 38 tests; added: 38 (13 persistence, 17 public API, 4 image, 3 query/performance, 1 generated-key compatibility). No existing test was removed, skipped or weakened.

## 23. MySQL Integration Results

Local MySQL 9.7.0: all tests passed on separate sumaya_pharma_test. RefreshDatabase guard requires testing environment, mysql connection and that exact test database before destructive setup. Development sumaya_pharma received only the reviewed forward migration. CI uses official mysql:8.4; both compatibility preflight and complete implementation jobs succeeded. Generated key rejects duplicate primary with error 1062; DB CHECK failures use error 3819. No SQLite substitute.

## 24. Static Analysis Results

`php vendor/bin/phpstan analyse --no-progress`: [OK] No errors. Relationship/resource/query types and model column/cast annotations describe real behavior. No new ignore rules, exclusions or baseline.

## 25. Pint Results

`php vendor/bin/pint --test`: passed after formatting the final pagination test. GitHub formatting step also succeeded.

## 26. Composer Security Audit

`composer validate --strict`: valid. `composer check-platform-reqs`: passed. `composer audit`: no security vulnerability advisories. Existing composer.json/lock are unchanged.

## 27. CI Status and Run URL

Verified actual [implementation run 38080802470](https://github.com/NabilAlsaidaly/Pharma/actions/runs/38080802470): completed/success for head 3ca25c29f3961598a50c4b512f4e91b9429880ce. Quality job and every validation/formatting/static-analysis/test/audit step succeeded. [Preflight run 38079814888](https://github.com/NabilAlsaidaly/Pharma/actions/runs/38079814888) also completed/success before adopting the generated constraint. Existing CI workflow retained unchanged. The documentation-only final commit triggers a further run; its live result is visible in the PR checks.

## 28. Existing Identity Regression Results

All 28 existing Identity feature tests remained unchanged and passed within the final 76-test suite. Other baseline tests also passed. No Identity source, auth/Sanctum configuration, Google flow or Customer migration was changed. The owner reported successful live Google login before this phase; it was not repeated or represented as a new automated live OAuth test.

## 29. Customer Data Preservation Evidence

Development database name, migration state, tables and customer records were checked before migration. Customer count before/after: **1 / 1**. SHA-256 of all full customer rows sorted by id before/after: `bfd1fec4fa98a4844988f217c4a4a887a41861e2f5559397fea314cf8184ab6c` (identical). No personal field values or credentials are included here. All six Catalog tables remained empty after migration and HTTPS read smoke tests. No fresh/reset/rollback/wipe/truncate/delete/rebuild was run on development. Customer schema remained unchanged.

## 30. PAY-001 Compatibility

Preserved [ADR-015](../adr/ADR-015-flexible-payment-before-order.md), [payment rule](../architecture/payment-before-order-rule.md) and [future checkout compatibility](../architecture/catalog-checkout-compatibility.md). Policy allows full payment or configurable partial deposit (greater than 0 and less than 100 percent). Required payment verification precedes future order confirmation/inventory allocation. Cart/intent/proof never reserve stock. Future availability recheck, atomic allocation and immutable price/currency/payment-policy snapshots belong to later modules; none is implemented here.

## 31. System Console Independence

No System Console implementation or coupling. Catalog has no dependency on System Console. No console monitoring/operational API was added.

## 32. Business Management Panel Separation

No admin authentication, business panel, public editing/deleting/uploading or administrative API. Future management must call approved persistence rules and validate metadata/files; no permission system was invented.

## 33. Files Created

- `app/Domains/Catalog/Application/DTOs/CatalogFilters.php`
- `app/Domains/Catalog/Application/Ports/PublicCatalogReader.php`
- `app/Domains/Catalog/Domain/Enums/AvailabilityStatus.php`
- `app/Domains/Catalog/Domain/Enums/CatalogStatus.php`
- `app/Domains/Catalog/Domain/Exceptions/ConflictingPriceOwnership.php`
- `app/Domains/Catalog/Domain/Rules/CatalogPrice.php`
- `app/Domains/Catalog/Infrastructure/Persistence/Concerns/HasCatalogPrices.php`
- `app/Domains/Catalog/Infrastructure/Persistence/Models/Brand.php`
- `app/Domains/Catalog/Infrastructure/Persistence/Models/Concern.php`
- `app/Domains/Catalog/Infrastructure/Persistence/Models/Product.php`
- `app/Domains/Catalog/Infrastructure/Persistence/Models/ProductImage.php`
- `app/Domains/Catalog/Infrastructure/Persistence/Models/ProductVariant.php`
- `app/Domains/Catalog/Infrastructure/Persistence/Queries/EloquentPublicCatalogReader.php`
- `app/Domains/Catalog/Interfaces/Http/CatalogImageUrl.php`
- `app/Domains/Catalog/Interfaces/Http/Controllers/PublicCatalogController.php`
- `app/Domains/Catalog/Interfaces/Http/Requests/CatalogListRequest.php`
- `app/Domains/Catalog/Interfaces/Http/Resources/BrandResource.php`
- `app/Domains/Catalog/Interfaces/Http/Resources/ConcernResource.php`
- `app/Domains/Catalog/Interfaces/Http/Resources/ProductImageResource.php`
- `app/Domains/Catalog/Interfaces/Http/Resources/ProductResource.php`
- `app/Domains/Catalog/Interfaces/Http/Resources/ProductVariantResource.php`
- `database/migrations/2026_10_10_000002_create_catalog_tables.php`
- `docs/adr/ADR-015-flexible-payment-before-order.md`
- `docs/adr/ADR-016-approved-catalog-foundation.md`
- `docs/api/catalog.md`
- `docs/architecture/catalog-checkout-compatibility.md`
- `docs/architecture/catalog-query-design.md`
- `docs/architecture/catalog.md`
- `docs/architecture/payment-before-order-rule.md`
- `docs/database/catalog-schema.md`
- `docs/reports/phase-03-catalog-design-gate.md`
- `docs/reports/phase-03-catalog-foundation-report.md`
- `tests/CatalogTestCase.php`
- `tests/Feature/Catalog/CatalogImageTest.php`
- `tests/Feature/Catalog/CatalogPersistenceTest.php`
- `tests/Feature/Catalog/CatalogQueryPerformanceTest.php`
- `tests/Feature/Catalog/PublicCatalogApiTest.php`
- `tests/Feature/PrimaryImageConstraintCompatibilityTest.php`

## 34. Files Modified

- `README.md`
- `app/Providers/AppServiceProvider.php`
- `docs/README.md`
- `docs/api/foundation.md`
- `docs/architecture/README.md`
- `docs/architecture/identity-and-authentication.md`
- `docs/architecture/module-boundaries.md`
- `routes/api.php`

AppServiceProvider adds the Catalog binding; routes/api.php adds exactly eight Catalog reads. Documentation updates describe implemented scope and owner-provided live login evidence. Identity source/configuration remains unchanged.

## 35. Files Deleted

None.

## 36. Dependencies Added, If Any

None. Existing Laravel/Eloquent, filesystem adapter, testing, Pint and PHPStan tooling were used. No lockfile or CI dependency change.

## 37. Git Commit and Push Status

Feature branch: `feature/catalog-foundation`, tracking origin. Preflight/design commit `9bf1a4efc015db797fc2d556cc53b951ffa2df96`; implementation commit `3ca25c29f3961598a50c4b512f4e91b9429880ce`; both committed and pushed. The final report is delivered in a following documentation commit (its SHA is available in Git history). [PR #1](https://github.com/NabilAlsaidaly/Pharma/pull/1) targets main and is prepared for owner review after final documentation verification. Full changes reviewed; git diff --check passed; no .env, credentials, raw customer data or generated testing artifacts staged. No force push/history rewrite/automatic merge.

## 38. Risks / Blockers

No remaining blocker for this Catalog scope. Actual limitations: no physical-image existence validation/provisioning; contains-name search may scan many visible products; correlated price summaries need reevaluation at production scale; price ownership requires model persistence rather than raw/bulk updates; product parent availability is informational and does not guarantee inventory or purchase. Tests verify deterministic behavior and constraints, not production load capacity. These limits are documented rather than hidden by caching or invented benchmarks.

## 39. Deferred Features

Favorites, Cart, Checkout Intent, Orders, Payments, deposit workflow, stock quantities/deductions/reservations/movements, management panel, admin auth, System Console, Redis caching, Next.js frontend, structural editing/deletion APIs, real upload/storage provisioning and purchase eligibility. No next-phase code was introduced.

## 40. Recommended Next Phase

Owner review and merge decision for this PR, then explicit authorization of the next business area and design gate. No phase number or exact next scope is assumed. PAY-001 and catalog visibility-versus-purchase distinctions must remain binding when purchasing is designed.
