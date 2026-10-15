# FIX-6C-001 — Pricing ownership after Variant archival

2026-10-15. CONFIRMED = directly observed; POTENTIAL = reasoned risk; NEEDS VERIFICATION = unverified. Narrow pre-merge correction on existing Phase6C branch/PR7; no new pricing policy.

## 1. Original issue

CONFIRMED public ProductResource treated a current Variant count as P1 physical ownership. Archiving every Variant incorrectly changed pricing.source to product, contrary to unchanged physical-Variant policy.

## 2. Actual affected files

Product.php (shared explicit projection), EloquentPublicCatalogReader.php (all public Product queries), ProductResource.php (serialization), FavoriteOperations.php (additional actual Resource consumer). Internal EloquentCatalogSelectionReader already used physical counts and is unchanged.

## 3. Runtime reproduction

CONFIRMED clean local/remote starting head a1fa0fc9bdd2c2e6d29a3d3b782f58bb6e1c4ca7, existing PR7 OPEN/UNMERGED. Before modifying implementation, created Product/Variant20.00USD in physically guarded sumaya_pharma_test and archived through the actual Catalog service. Printed observation: physical1/current0, public source=product, public variants=[], internal structural_count1/selected_variants=[], Cart blocked/selection_invalid. Regression failed expected variants versus actual product (1failed/7assertions). After the fix, same regression passed1/11 and showed source=variants with null range endpoints. Ignored local logs phase6c-fix001-repro.log and phase6c-fix001-after.log retain observations; no production fixtures.

## 4. Root cause

SoftDeletes correctly excludes archived Variants from withCount('variants'). Resource incorrectly reused that count to decide ownership. Internal structural admission correctly included archived rows; public/current representation and physical ownership had different meanings.

## 5. Corrected design

Product::scopeWithPricingOwnership adds one correlated SQL EXISTS aggregate withTrashed aliased has_physical_variants. Both public and Favorites projections invoke it after select. ProductResource uses the boolean; current count/min/max/detail relations keep their existing scopes. No per-record query, relationship hydration, fallback owner, new public field or dependency.

## 6. Physical versus current semantics

Physical includes archived identities and reserves P1. Current means not soft-deleted; it does not invent an active status field on Variant or imply availability/purchase eligibility. Current variants_count, public detail and USD/SYP min/max exclude archived rows. Internal selection's existing variants_count is explicitly structural/physical and unchanged, including its locked current-read guarantees.

## 7. Before/after API example

Only Variant archived, Product/Brand still active. Before:
```json
{"pricing":{"source":"product","price_usd":null,"price_syp":null},"variants":[]}
```
After:
```json
{"pricing":{"source":"variants","price_usd":null,"price_syp":null,"range_usd":{"min":null,"max":null},"range_syp":{"min":null,"max":null}},"variants":[]}
```
Existing conditional range shape remains: direct Products have no ranges, Variant-owned Products have ranges. Null is not zero. No archived names/UUIDs/deleted_at/internal flag leak into public JSON.

## 8. Public Catalog route coverage

CONFIRMED /api/v1/products, /products/{slug}, /products/recommended, /brands/{slug}/products, /concerns/{slug}/products, name-search and composed Brand/Concern/recommended filters. Additional /api/v1/favorites inspected and tested because it also serializes ProductResource. rg audit found no other Resource consumer. Two versus20 archived-Variant Products: public4queries/Favorites4queries in both cases, SQL EXISTS present, no hydrated Variant collection. Historical API query/performance tests retained.

## 9. Cart compatibility

CONFIRMED actual CartOperations/CartSnapshot retain archived Variant IDs and quantity, return blocked/selection_invalid/null pricing/incomplete totals, reject both no-Variant and archived-Variant additions. Authorized decrease2to1 and removal still work. No Cart source changed.

## 10. Checkout compatibility

CONFIRMED actual submitted request exists before archival; new operation rejected checkout_selection_blocked, no new request inserted. Existing operation replay succeeds after archival and Cart removal, same request ID/created=false; full header/item JSON including fingerprints unchanged. Existing locked snapshot/idempotency code unchanged; PAY001 untouched.

## 11. Exact files modified

- app/Domains/Catalog/Infrastructure/Persistence/Models/Product.php
- app/Domains/Catalog/Infrastructure/Persistence/Queries/EloquentPublicCatalogReader.php
- app/Domains/Catalog/Interfaces/Http/Resources/ProductResource.php
- app/Domains/Favorites/Application/FavoriteOperations.php (only relevant projection call)
- tests/Feature/Catalog/CatalogPricingOwnershipTest.php (new)
- docs/architecture/catalog.md
- docs/architecture/catalog-administration.md
- docs/reports/phase-06c-catalog-administration-report.md
- docs/reports/phase-06c-fix-001-pricing-ownership.md (new)

## 12. Tests added

Six meaningful cases: original only-archived reproduction; no-Variant direct USD/SYP/null/zero/shape; current→mixed→all-archived→restored currencies/UUID/P1; all Resource routes including Favorites/no metadata leak; retained Cart/decrease/removal/new Checkout refusal/immutable replay; bounded SQL projection at2/20 without hydration. CONFIRMED focused6passed/297assertions. Original453tests/3694assertions retained, no weakened assertions or suppressions.

## 13. Total PHPUnit test count

CONFIRMED complete local PHPUnit459passed,234.98s; correction source CI459passed,76.70s on MySQL8.4.11/PHP8.4.26. Includes every Catalog, Collections/Cart/Favorites, Checkout/Preliminary Request, Phase6C administration and historical test. Six new cases added to453 baseline; no skipped or removed tests. Focused6passed/297assertions separately before full run.

## 14. Total assertion count

CONFIRMED local and correction source CI3991assertions/459tests; focused297. Historical453/3694 retained plus6/297. Exact final documentation-head CI result is independently verified after publishing; never inferred from local or historical counts.

## 15. Pint result

CONFIRMED --dirty/--test pass, no unrelated formatting change.

## 16. PHPStan result

CONFIRMED existing level5/512M passes, no suppression/config reduction. Relationship closure uses inferred Eloquent relation builder type, consistent with existing reader, rather than a generic non-model-specific Builder annotation.

## 17. Composer checks

CONFIRMED validate --strict, check-platform-reqs, audit pass; no advisories, manifest/lock/dependency changes.

## 18. Vite build result

CONFIRMED Vite7.3.7/59modules build, CSS52.48kB/gzip11.69, JS54.45kB/gzip20.59. No frontend source/lock/dependency change.

## 19. Database preservation verification

CONFIRMED before/after read-only fingerprint and deep comparison: actual development sumaya_pharma/MySQL9.7.0/REPEATABLE-READ,11tables/1Customer/3migration rows/other9empty. Every sorted full-row/schema/.env hash unchanged; both metadataSHA256291dd66b3adc84a507a82565465c0065ad987af39d1852a0d9ce476750d7e75d. Test superclass positively checks testing/mysql/configured AND physical database name before refresh. No real Owner/.env/public-media changes.

## 20. Migration status

No new or modified migration. Existing pending development migrations remain unapplied. Existing migrations run only on positively guarded test DB; no schema change needed by this read-side correction.

## 21. Final feature HEAD

CONFIRMED correction source HEAD dd8f867971eaa00f5d25582a6a7ec21a27f20468, following starting head a1fa0fc9bdd2c2e6d29a3d3b782f58bb6e1c4ca7. This evidence-only documentation commit changes only this report; its final hash and exact workflow proof are independently recorded after verification in PR7/final delivery because the report cannot embed its own commit hash. Normal push on feature/catalog-administration-foundation, no new branch/reset/force push.

## 22. Final GitHub Actions URL and result

CONFIRMED [correction source CI run27](https://github.com/NabilAlsaidaly/Pharma/actions/runs/38728480431), [quality job](https://github.com/NabilAlsaidaly/Pharma/actions/runs/38728480431/job/116400086820), exact source head dd8f867971eaa00f5d25582a6a7ec21a27f20468: SUCCESS, every step green,459tests/3991assertions,76.70s. Actual logs independently confirm reproduction now source=variants/null ranges, public4/Favorites4queries at2/20, and MySQL8.4.11. Final documentation-head SHA/run/job/results are independently checked in PR7/final delivery after this evidence commit; source/previous Phase6C success does not substitute for final-head proof.

## 23. PR7 URL

[Existing PR7](https://github.com/NabilAlsaidaly/Pharma/pull/7), base main. Remains OPEN/UNMERGED; no new PR/deployment/merge.

## 24. Remaining risks

POTENTIAL raw SQL/out-of-contract writers still require established invariant discipline. New ProductResource consumers must request the explicit ownership projection; every current consumer is audited/tested. EXISTS adds indexed per-row SQL work inside the existing bounded query, not a throughput guarantee. Existing Phase6C operational/accessibility/media limitations remain. No new business decision, schema, Cart/Checkout behavior or public field.

## 25. Merge-readiness assessment

CONFIRMED reproduction, narrow fix,6focused/459full tests, all local/source-CI quality gates and database preservation. Correction source is ready for Owner review; final documentation-head CI must also be verified and recorded in PR7/final delivery before claiming completion. Merge remains Owner-controlled and is not authorized here. No Phase6D work.
