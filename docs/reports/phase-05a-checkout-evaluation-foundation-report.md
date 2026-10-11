# Phase 05A - Checkout Evaluation Foundation Implementation Report

Date: 2026-10-11. Evidence classifications: **CONFIRMED** = observed code/tool/test evidence; **POTENTIAL** = risk hypothesis; **NEEDS VERIFICATION** = not established. Phase5A only, not full purchasing workflow. Publication/CI fields below are completed after actual GitHub verification; no merge/deployment authorized.

## 1. Executive summary

Active Google-authenticated Customers can evaluate their existing Cart in USD or SYP without purchasing, paying or reserving. Complete current merchandise estimates, exclusions and human-review needs are returned. No business persistence or schema expansion.

## 2. Authorization and exact scope

Owner phase5.2.md authorizes exactly POST /api/v1/checkout/evaluations, tests/docs/commit/push and one unmerged PR. No Intent, Order, payment, staff, upload, stock, delivery, Redis or frontend. Accepted ADR-018 is narrow evaluation-only authority, not approval of all Phase5 business choices.

## 3. Verified starting commit and branch

**CONFIRMED:** initial feature/favorites-cart-foundation HEAD c9992f51765241d9e968b3b8b7168dbcdcc896e3; local main95fb9d behind origin/main6ff053a36d593500e6234ab6bddec2efd73112fa. Remote main fetched and verified. Baseline [CI38126419707](https://github.com/NabilAlsaidaly/Pharma/actions/runs/38126419707) quality job114431611451 rechecked completed/success. Historical134tests/1014assertions are not new Phase5A evidence.

## 4. Git safety and preserved untracked files

**CONFIRMED:** original workspace had only untracked revised Phase5 design gate. Switched main, fast-forwarded to6ff053a and created feature/checkout-evaluation-foundation from that exact baseline. Gate remained available and is included intact in this PR. No reset/force-clean/discard/stash/history rewrite. Original gate prefix51,571bytes SHA256 a75b22a6983dece436909d09e8de886465bf08a325307726a8694e07780ba03d preserved; owner clarification appendix preserved too. No change to gate contents in Phase5A.

## 5. Architecture decisions

Orders/Application EvaluateCheckout -> Cart/Application CartSnapshot.complete -> existing CatalogSelectionReader and CartEvaluator. Thin controller and dedicated FormRequest. No Orders direct Cart infrastructure queries, models, external adapters, speculative repositories or empty new domain layers.

## 6. Cart snapshot implementation strategy

One short non-locking DB transaction, Cart SELECT first establishes REPEATABLE READ view; sorted bounded items then batched Catalog reads share it. Pure evaluation/projection after detached arrays requires no later SQL. No exclusive Customer/Cart locks, isolation change or payment cache. Expected version compared against that same snapshot. Existing CartSnapshot.read method unchanged.

## 7. Exact new API route

**CONFIRMED route inspection:** exactly one Checkout route, POST api/v1/checkout/evaluations. Existing Catalog, auth, Favorites and Cart route entries unchanged. Existing auth:sanctum, EnsureActiveCustomer, LimitPersonalMutationBody and throttle:personal-collections apply.

## 8. Request validation

JSON object with exactly currency USD/SYP and canonical unsigned BIGINT decimal string expected_cart_version. Bounds0..18446744073709551615 checked via Brick BigInteger without PHP integer conversion. Raw wire validation prevents middleware trimming malformed input into valid values. No unknown body/query field, client owner/Cart/price/quantity/financial/reservation/staff data. Invalid422; stale409 safe reason.

## 9. Response schema

Existing success/message/data envelope. Data currency/cart/items, selection_status blocked/requires_review/valid, exact merchandise pricing, blockers/review count and commercial object. Commercial payable_total/required_advance/delivery_fee always null; quote_issued/payment_instructions_available/can_confirm_order/reserves_inventory always false. Exact complete example and error contract in [API document](../api/checkout-evaluation.md).

## 10. Currency evaluation rules

Independent exact selected-currency strings using existing Brick BigDecimal; no conversion, alternate-currency fallback, range price, shipping, tax, discount or policy calculations. Partial priced_subtotal excludes blocked lines; complete merchandise estimate null on any exclusion. Large-price maximum technical-quantity test verifies21474836469999999978525163.53 without floats.

## 11. Product and Variant correctness

Existing zero-variant Product or exact belonging Variant price owner. Structure changes block rather than choose defaults/repair Cart. Existing composite FK prevents wrong-Product Variant persistence; shared evaluator test also verifies malformed selection rejection. Existing primary reason precedence extracted unchanged into CartEvaluator.exclusionReason and reused by totals/Orders projection.

## 12. Hidden Product/Brand protection

Hidden projection contains only owned item ID, quantity, generic inaccessible reason, null price/estimate and false review flag. No raw model, Product/Variant ID/name/slug/image/availability. Tests hide Product and Brand independently, verify redaction/retention and show reactivated selections evaluate again.

## 13. Empty Cart behavior

No existing Cart returns null ID/version0/count0 and blocked empty_cart without lazy creation. Persistent empty Cart retains actual ID/version. Merchandise arithmetic0.00/complete=true is preserved while commercial amounts remain null and confirmation false. Tests include maximum unsigned Cart version string.

## 14. On-request behavior

Priced effective on_request can contribute estimate with requires_fulfillment_review. All-price-complete request Cart has requires_review; an excluded selection takes precedence as blocked. Both Product and Variant on_request tested. No instructions/reservation/stock promise.

## 15. Stale Cart version behavior

Exact string mismatch throws existing CartConflict(stale_cart_version), safe409 RESOURCE_CONFLICT/error.fields.selection and request-ID. No successful misleading quote or data mutation. Concurrent writer commit after reader snapshot may make returned estimate stale; later evaluation observes new version and rejects old supplied version.

## 16. Full-Cart and Item-cap behavior

All bounded items returned without pagination. Technical config200 distinct selections, read at most201 to detect malformed/imported excess; excess409 cart_evaluation_capacity_exceeded before Catalog read, never truncated successful completeness. Tests2/50/200 and over-limit201. Current GET Cart still paginates48 without changed contract. Stable created_at DESC then UUID ASC ordering.

## 17. Authentication, CSRF and authorization

**CONFIRMED:** Guest and bearer-only401; suspended403; actual Google callback/session evaluation sees only own Cart; forged identity/financial fields422; real CSRF middleware bypass explicitly disabled in test, missing token419/correct token succeeds. Existing body16KiB413, shared POST/write quota60/min/Customer429 with Retry-After, independent Customer quota, safe envelope/request-ID. Public Product read still succeeds as Guest. No widened guards/OAuth/CSRF exceptions.

## 18. Zero-side-effect evidence

Tests compare all business table rows and SHOW CREATE TABLE definitions before/after valid, blocked, stale and invalid evaluation. No lazy Cart/profile/price/quantity/version writes or cleanup. Framework session/CSRF/rate counters are ordinary operational work. No jobs, messages, uploads, payment/reservation creation; development preservation independently checked after full suite.

## 19. Query-count results

**CONFIRMED HTTP SQL capture:**2items=6queries,50=6,200=6 in same authenticated test context; no FOR UPDATE, no per-item SQL. Count excludes test helper's preliminary version query by supplying known expected version directly. EXPLAIN FORMAT=TRADITIONAL bounded cart_items probe: ref access through existing cart_items_selection_unique, estimated201rows, Using filesort. Bounded200-row sort needs no new index/migration; no production throughput claim. Existing Cart6/Favorites4 regression query evidence remains passing.

## 20. Real concurrency results

**CONFIRMED:** three independent PHP/MySQL reader processes, committed fixtures and deterministic file barriers, reader transaction_level1, distinct actual connection IDs, writer commit before reader resumed. Final local full-suite evidence: header update reader1067/writer1066; Catalog price+availability reader1070/writer1069; additive Cart mutation reader1073/writer1072 (IDs are run-specific). Reader returns old version1/quantity1/estimate2.00/available-valid; later evaluation sees committed version2/quantity3/estimate6.00 or new9.00 Catalog price/blocked unavailable. Old expected version conflicts later. No lost writer, partial mixed state or exclusive reader lock. Existing Phase4 real concurrency suite also runs.

## 21. Full test count and assertions

**CONFIRMED final local full suite:**195passed/1371assertions, duration50.13s, including all134historical tests and61new Checkout cases. Earlier complete run195/1362 preceded required coverage refinements for visibility recovery, Variant on_request and currency independence. Counts are actual execution output, not inferred.

## 22. Existing regression results

Full suite includes all134 historical tests plus61 new Checkout cases, including input-provider cases; none deleted/skipped/weakened. Existing Identity/Catalog/Favorites/Cart business/security/constraints/concurrency/query tests remain. New test helpers exercise isolated committed fixtures; no broad testing framework introduced.

## 23. Pint result

**CONFIRMED:** php vendor/bin/pint formatted new test files; php vendor/bin/pint --test passed. No unrelated source reformatted. Final formatting check after coverage additions required before commit.

## 24. PHPStan result

**CONFIRMED:** php vendor/bin/phpstan analyse --no-progress, existing Larastan level5, no errors. No baseline/suppression/level reduction or configuration change. Application source unchanged since that successful run.

## 25. Composer validation, platform and audit

**CONFIRMED:** composer validate --strict valid; composer check-platform-reqs all requirements success, PHP8.4.26; composer audit reports no vulnerability advisories. No dependency added/removed or lock change. No install/update performed.

## 26. MySQL compatibility

**CONFIRMED locally:** actual development and isolated test DB MySQL9.7.0/REPEATABLE-READ; environment local versus testing verified before setup. Independent worker and new migration fixture guard actual SELECT DATABASE() as well as configured testing/mysql/name before use. CI MySQL8.4/PHP8.4 results recorded only once observed; not presumed from local execution.

## 27. Development database preservation

**CONFIRMED before/after full suite:** all11 tables identical full sorted-row SHA256/count and SHOW CREATE TABLE schema hash; customers1, full-row SHA256 bfd1fec4fa98a4844988f217c4a4a887a41861e2f5559397fea314cf8184ab6c and schema hash6b325a2ae7094320662710ee51933758f0c1738081e198a7ad457a7832c5fe80. Migrations3 unchanged; all nine Catalog/collection tables0. .env SHA unchanged, OAuth untouched. Evidence ignored storage/framework/testing/phase5a-development-before.json and after.json contains only hashes/schema metadata, no personal values. Development inspections SET TRANSACTION READ ONLY with finally rollback of the read transaction; no migrate rollback/fresh/reset/wipe/reseed/truncation. Zero new migrations, zero new business tables, no development migration runner.

## 28. Documentation updates

New API/architecture/ADR-018 and this report. Preserved original gate+owner clarifications included. Only relevant docs index/module Orders row updated. No historical SRS or accepted PAY-001 edit, no full-design acceptance claim.

## 29. Files created

EvaluateCheckout.php; CheckoutEvaluationRequest.php; CheckoutEvaluationController.php; CheckoutEvaluationTest.php; CheckoutSnapshotConcurrencyTest.php; checkout-snapshot-worker.php; docs/api/checkout-evaluation.md; docs/architecture/checkout-evaluation.md; docs/adr/ADR-018-checkout-evaluation-foundation.md; this report. Previously created untracked docs/reports/phase-05-checkout-payments-design-gate.md included without content changes.

## 30. Files modified

CartSnapshot.php (complete boundary), CartEvaluator.php (shared exclusion reason), routes/api.php (one route), docs/README.md (links), docs/architecture/module-boundaries.md (Orders row). CartOperations, GET controller/resources, Catalog persistence/readers, Identity/config/env and CI unchanged.

## 31. Files deleted

None. Runtime worker coordination files cleaned only inside their unique isolated test directory; existing user files not removed.

## 32. Dependencies added or removed

None. Existing Brick Math, Symfony Process and testing tools reused. No Redis/service/container/queue/SDK/CI dependency expansion.

## 33. Commits

NEEDS VERIFICATION at initial report preparation: focused implementation commit follows green local gates. Actual source commit and later evidence-only commit are recorded after creation; final delivery HEAD is verified against PR and CI, never guessed before Git assigns it.

## 34. CI run and result

NEEDS VERIFICATION until pushed feature PR triggers actual exact-head Actions. Baseline main success is not new implementation CI. CI run/actual MySQL8.4 evidence will be recorded after observed completion; final delivery-head check is also linked in PR and final owner response.

## 35. PR URL and status

NEEDS VERIFICATION until GitHub confirms one open PR against main. No merge/deployment authorization. Actual PR URL added after successful creation, not invented from assumed numbering.

## 36. Known limitations

Non-binding snapshot becomes stale after later commit; REPEATABLE READ is required, not globally altered. Cap is technical, not commercial quantity permission. available is descriptive, not actual stock guarantee. Existing POST quota applies to read-only evaluation. File/session/throttle multi-node topology is POTENTIAL scaling concern outside milestone. Test receipts/provider/real funds do not exist because money workflow is deferred. Initial fixture mistakes (fillable Cart version, request quota already consumed, MySQL EXPLAIN default format) were corrected in tests, without weakening runtime security or expectations; complete local checks rerun.

## 37. Deferred Phase5 capabilities

Persistent Checkout, contacts/history/cancellation/expiry, configured payment methods/QR/policies/deposits, claims/proofs/receipts/verification/balance/refunds, staff/MFA/management, confirmed Orders/stock/fulfillment/delivery, frontend/Redis/provider/webhook/worker/deployment. Full Phase5 remains design approvals, no Phase5B work.

## 38. Final owner-review checklist

Review only evaluation API and unchanged Cart/Catalog/Identity behavior, non-binding commercial fields, input/privacy/auth/CSRF/ownership, whole-Cart bound/snapshot evidence, actual final test/CI results and development preservation. Feature PR must remain open/unmerged on verified passing delivery HEAD. No merge/deployment/next-phase work without separate owner action.
