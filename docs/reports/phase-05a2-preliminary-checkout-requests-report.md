# Phase 05A.2 preliminary checkout requests

Date: 2026-10-13. **CONFIRMED** means actual code/test/tool evidence; **POTENTIAL** means reasoned risk; **NEEDS VERIFICATION** means not established. Delivery evidence is updated after actual GitHub checks. No merge, rollout or development migration is authorized.

## 1. ملخص تنفيذي

أضيف حفظ الطلبات الأولية مع معلومات التواصل ولقطة الاختيارات والكميات، وإنشاء الطلب وعرض سجل العميل وتفاصيله. العملية ذرية وقابلة لإعادة المحاولة دون تكرار الحفظ، وتعيد التحقق من السلة والكتالوج داخل المعاملة. الطلب يحتاج مراجعة بشرية ولا يؤكد شراءً أو دفعاً ولا يحجز مخزوناً. قاعدة التطوير وبيانات العميل محفوظة؛ تشغيل migrations عليها مؤجل عمداً.

## 2. Exact approved scope

Owner phase5.3.md authorizes Phase5A.2 implementation, exactly POST/GET checkout/intents and GET checkout/intents/{id}, two new tables, focused tests/docs, commit/push and one open unmerged PR. Existing evaluation and collection contracts remain. PAY-001 is unchanged. No commercial policy invented.

## 3. Starting Git commit

**CONFIRMED:** PR3 was already owner-merged. Fetched origin/main and fast-forwarded local main to `8f8b7ff5c13255aa842d7c606c018534f6296b3c`; began from that exact commit. Initial workspace was clean. No reset, force push, destructive cleanup or discarded user work.

## 4. Final feature commit

Publication evidence pending final local checks and actual commit. The implementation commit and final documentation head will be distinguished here; a report cannot contain its own SHA without changing it again.

## 5. Feature branch

`feature/preliminary-checkout-requests`, created from verified current main, targets main.

## 6. Source files created

Orders/Application: SubmitPreliminaryCheckoutRequest.php, PreliminaryRequestHistory.php. Orders/Infrastructure/Persistence/Models: CheckoutIntent.php, CheckoutIntentItem.php. Orders/Interfaces/Http: PreliminaryRequestController.php, PreliminaryCheckoutRequest.php, PreliminaryHistoryRequest.php, PreliminaryRequestResource.php. Configuration: config/preliminary_checkout.php. Tests: PreliminaryCheckoutTest.php, PreliminaryCheckoutConcurrencyTest.php and tests/Support/preliminary-checkout-worker.php.

## 7. Source files modified

CartSnapshot adds the locked submission application boundary. EloquentCatalogSelectionReader makes Variant aggregate counts current when locking; a real race first reproduced stale aggregate admission and now guards the correction. AppServiceProvider adds a separate named POST limiter. routes/api.php adds only three routes. bootstrap/app.php sanitizes preliminary-route exception reporting. IdentityTestCase now verifies actual physical database before destructive setup. Historical CheckoutEvaluationTest keeps its single evaluation-route assertion scoped to that exact route; CatalogPersistenceTest now asserts the two authorized tables while preserving prohibited stock/order/payment assertions. No test removed, skipped or baseline suppression added.

## 8. Migration files created

`2026_10_13_000001_create_checkout_intents_table.php` and `2026_10_13_000002_create_checkout_intent_items_table.php`. Existing migrations and tables are unchanged. Only isolated test schema migrations are executed. Down methods exist for isolated test cleanup; no development rollback is run.

## 9. Constraints and indexes

UUID primary keys; UNIQUE(Customer, operation); Customer-created_at-id history index; restrictive Customer/Cart/Product/parent FKs; composite Variant/Product ownership FK; generated COALESCE Variant key and UNIQUE(parent, Product, key), UNIQUE(parent, source item), UNIQUE(parent, line_order); signed integer quantity CHECK1..2147483647; boolean review CHECK0/1; enum USD/SYP and submitted-only status. Historical source CartItem UUID deliberately has no FK. Cart/Customer ownership is verified under transaction locks rather than altering existing schema. Child timestamps are represented by immutable header creation time. Real MySQL constraints are tested, including NULL duplicate, mismatched Variant and deletion restriction.

## 10. Contact validation

Wire JSON objects, exact allowed keys, Unicode text and deterministic surrounding trim. Name150, phone40, governorate/city120, address500, optional nullable notes1000. Required text must contain a letter/number; phone international digits/separators and optional leading plus, at least three digits. Control/format characters, markup, forged data and query parameters rejected. Notes omitted/empty/null canonicalize to null. No geography lookup or invented Syrian-only phone rule.

## 11. Email snapshot

Current locked authoritative Customer email, validated usable, copied only for new requests. No writable email, no Customer profile modification, no email in idempotency fingerprint. Same-operation replay returns the original email snapshot after account changes. Google browser-session integration uses the existing fake OAuth adapter; no claim of renewed real external Google authorization.

## 12. Transaction and lock order

Customer FOR UPDATE -> active check -> scoped operation lookup FOR UPDATE -> email -> owned Cart FOR UPDATE -> expected version -> bounded all selections -> deterministic Catalog Product/Variant/Brand locking reads -> CartItems FOR UPDATE -> verify selection equality -> header insert -> one bulk children insert -> count -> commit. Three deadlock retries use existing Laravel transaction behavior. Existing Cart mutation Customer-first locking provides serialization. No source rows are written. Exhausted database contention uses safe 503; arbitrary hardware outage recovery is not experimentally established.

## 13. Cart/Catalog revalidation

Every submitted line is freshly evaluated, not trusted from a previous evaluation response. Positive price in requested currency, valid ownership/Variant selection, active Product/Brand and available/on_request required. Empty/stale/over200/ineligible selections return safe409 without partial persistence. Price ownership stays in Catalog. Locked Variant aggregate current-read fix does not affect nonlocking evaluation snapshots. No financial snapshot is stored.

## 14. Idempotency

Client UUID4/7 normalized lowercase; UNIQUE(Customer, operation). SHA256 canonical currency, version, contact six fields in fixed order, nullable notes; excludes email. Identical replay looks up the committed result **before** fresh Cart eligibility, returns same ID/timestamp/snapshot200. Changed payload409. Distinct operations create distinct requests; no invented active-request cap or expiry. Hourly attempt quotas precede lookup and count replays.

## 15. Real concurrency evidence

Independent PHP processes bootstrap guarded physical test DB, report connection IDs, and overlap committed fixtures. Parent holds Customer or Product lock; SHOW FULL PROCESSLIST observes each worker actually waiting on intended FOR UPDATE before release. Same operation yields one created/one replay and one complete header/items; distinct keys yield two complete requests; Cart increment races yield coherent snapshot or stale conflict; hiding/unavailability/zero/null price while waiting blocks; Variant creation invalidates base selection. Lost-response replay occurs on a second independent connection after source deletion/hiding/email change. Real CHECK failure after header insertion rolls back all request rows and leaves source Cart unchanged. Tests emit PRELIMINARY_REAL_OVERLAP evidence. No mocks substitute for race connections.

## 16. Ownership and immutable history

Every query scopes Customer. List default12/max48, created_atDESC/idASC, summaries omit PII/items. Owned detail includes only historical identifiers/quantity/review flags and contact snapshot; no Catalog lookup. Unknown/nonowned404 is indistinguishable. Cart edits/removal, hidden Catalog and changed email cannot mutate submitted facts. No update/delete/status endpoints.

## 17. Error responses

401 guest/bearer;403 suspended;419 CSRF;422 invalid/forged wire input;413 oversized16KiB;409 selection/version/email/idempotency;429 attempts with Retry-After;404 owned lookup concealment;405 unsupported mutations;500 real constraint failure;503 lock timeout/deadlock safe envelope. Existing request ID header and error body preserved. Successful responses explicitly keep payment_required_now=null, payment_instructions_available=false, is_confirmed_order=false and reserves_inventory=false.

## 18. Security coverage

Strict invalid-field and forged-commercial-field datasets, actual CSRF enforcement, Google session, guest/bearer/suspended rejection, scoped operation/customer ownership, body limits, forbidden history input, nonfinancial resources, sanitized QueryException logging assertion, physical database guard, independent-connection races. Laravel logger callback suppresses bindings/trace and logs only exception class + request ID. Existing trusted-origin CORS behavior is reused; no body/PII in URLs. Infrastructure logging policy outside this repo remains an owner rollout consideration.

## 19. SQL measurements

**CONFIRMED:** POST SQL counts 1/2/50/200 =12/12/12/12; exactly one child bulk insert per request. Detail counts2/2/2/2 with zero current Catalog queries; list count2. History EXPLAIN FORMAT=TRADITIONAL uses checkout_intents_customer_id_created_at_id_index; local MySQL reports indexed lookup with filesort for mixed descending/ascending order. Bounded pagination does not imply zero sorting. No N+1 or item-dependent SQL count. No arbitrary latency claim.

## 20. Full PHPUnit count

**CONFIRMED: 288 passed**, full local suite, no failures/skips; 195 historical tests plus93 new cases. Final run includes independent-process mid-creation SQL failure proof. All tests execute against physically guarded sumaya_pharma_test MySQL, never development. No coverage weakened for convenience.

## 21. Full assertion count

**CONFIRMED: 1852 assertions**, full local run110.52s. Additional final focused Race F run verifies the header-observed and exact MySQL-code assertions. Local and CI counts can differ with these additional observation assertions and when legitimate concurrent ordering exercises a different safe branch.

## 22. Historical regressions

All195 historical tests retained. Two inventory assertions evolve only for explicitly approved new routes/tables, with new private-route/nonfinancial/schema assertions added. Catalog read counts4, Cart read6, Checkout evaluation6 remain constant at historical sizes. Existing Identity/collection constraints and independent concurrency tests remain part of full suite.

## 23. Pint

**CONFIRMED:** Pint --test passes. No unrelated formatting changes.

## 24. PHPStan

**CONFIRMED:** level5 app/System Console analysis passes without suppressions. Resource documents the actual delegated model via @mixin; nullable/version and guaranteed string handling corrected rather than ignored.

## 25. Composer

**CONFIRMED:** composer validate --strict, check-platform-reqs and audit pass; no security vulnerability advisories. PHP8.4.26 local platform. No package/version/dependency changes.

## 26. MySQL compatibility

**CONFIRMED local:** MySQL9.7.0, REPEATABLE-READ, real migrations/CHECK/FK/uniqueness/races. MySQL8.4 CI result pending actual run; do not equate local proof with CI compatibility. No SQLite substitutes.

## 27. Development preservation

**CONFIRMED:** ignored read-only helper captures actual physical sumaya_pharma, sorted full-row hashes and SHOW CREATE TABLE hashes before/after; all11 tables, row counts, schemas, Customer IDs/content and .env hash compare equal. Counts: customers1, migrations3, other9 tables0. Customer row-set SHA256 `bfd1fec4fa98a4844988f217c4a4a887a41861e2f5559397fea314cf8184ab6c`; Customer schemaSHA256 `6b325a2ae7094320662710ee51933758f0c1738081e198a7ad457a7832c5fe80`; .envSHA256 `8a30d395f38fc01b2f081077e7690b8aa2d162859e7f8d8bafe62b94951940d5`. No actual Customer PII or credentials included. The helper starts a read-only transaction and rolls it back; its local metadata artifacts are ignored and not committed.

## 28. Development migrations NOT run

**CONFIRMED:** no development migration command executed, no checkout tables added there, migrations row-set unchangedSHA256 `5c9422ec482384cc6689955898253f4d685c9b1838aab70325b935c7314a49f5`. Future separately approved forward rollout command: `php artisan migrate --force`, after backup and explicit target/environment verification. Endpoints require schema before operation. Do not run fresh/refresh/reset/rollback on development. No deployment performed.

## 29. Documentation and ADR

API and architecture preliminary-checkout-requests.md, ADR-019 and this report, plus docs/README and architecture index. Existing SRS, PAY-001 and accepted Design Gates preserved. Phase5 gate whole-fileSHA256 remains `fd5c084bc74aa3354b6542d36f6553a533f7dc06b6d0d70b6c4c20436d66c730`.

## 30. Dependencies

None added/removed/updated. Existing Laravel limiter/cache/session used. No Redis provisioning, external email, queue or platform service added.

## 31. GitHub Actions

Pending publication and exact final-head run. Required job includes Composer metadata/install/platform, Pint, PHPStan, full tests against MySQL8.4, audit. Final head must match observed PR head; earlier successful SHA alone is insufficient.

## 32. Pull request

Pending actual URL. One PR to main, OPEN and UNMERGED. Owner review only. No merge authorization, deployment or next phase.

## 33. Deferred capabilities

Confirmed Order, financial OrderItem snapshots, payment methods/policy/claims/verification/receipts, staff/owner accounts, management, stock counters/reservation/allocation, shipping fees/provider, notifications, frontend, Redis, webhook/gateway, domain/DNS/hosting/deployment and all future phases.

## 34. Failure scenarios and limitations

Test references below are methods in PreliminaryCheckoutTest (`P`) or PreliminaryCheckoutConcurrencyTest (`C`); **CONFIRMED** unless explicitly qualified. Code/operational evidence is separated from experimental fault testing.

| # / Scenario | Actual outcome | Evidence | Invariant / limitation |
| --- | --- | --- | --- |
| 1 guest |401 no write|P csrf_guest_bearer_suspension_and_request_id|session identity|
| 2 suspended |403 no write|same P|active Customer; suspension invalidates session|
| 3 empty Cart |409|P empty_stale_hidden_zero_missing_price_and_unavailable_block_atomically|nonempty snapshot|
| 4 stale version |409|same P; C cart_writer_and_submission_have_coherent_or_stale_outcome|locked version|
| 5 Product disappears |source FK restricts deletion; removed selection yields empty409|P schema_rejects_null_selection_duplicates_bad_quantities_and_restricts_deletion; empty test|physical dangling Product requires corrupt foreign-key bypass, not an authorized writer|
| 6 hidden Product |409 new operation|empty test; C catalog_visibility_change_while_waiting_blocks_submission_atomically(hidden)|current locked Catalog|
| 7 invalid Variant |composite FK rejects wrong Product; newly variantized base blocked|P variant_prices_visibility_and_composite_fk_ownership; C catalog_variant_creation_while_waiting_invalidates_base_selection|FK + current aggregate|
| 8 on_request |accepted with review flag|P on_request_is_eligible_with_review_and_selected_currency_price|human review, never promise fulfillment|
| 9 missing currency price |409|same P; variant test|positive selected-currency price|
| 10 invented total |422|P strict_input_rejects_invalid_and_forged_fields_without_writes(total)|exact input|
| 11 invented payment confirmation |422|P all_other_commercial_and_selection_fields_rejected(verified/is_paid)|PAY-001|
| 12 duplicate HTTP |same result200, one request|P normalization_omitted_notes_null_notes_and_uuid_case_replay; C same_operation_two_connections_create_one_complete_request|fingerprint + unique + Customer lock|
| 13 lost response |same committed result on fresh connection after source changes|C lost_response_replayed_on_another_connection_after_source_changes|replay first; network packet loss simulated by discarding result|
| 14 two operations |two complete requests|C distinct_operations_serialize_and_create_two_complete_requests|distinct keys deliberately allowed|
| 15 Cart changes concurrently |coherent snapshot or stale409|C cart_writer_and_submission_have_coherent_or_stale_outcome|Customer-first serialization|
| 16 unavailable during submission |409 no partial persistence|C catalog_visibility_change_while_waiting_blocks_submission_atomically(unavailable)|locked current eligibility|
| 17 Cart edited afterward |saved facts unchanged|P replay_after_cart_deletion_catalog_hiding_and_email_change_returns_original|immutable historical selection|
| 18 account email changed |original email preserved on retry/detail|same P; C lost-response|email excluded from fingerprint|
| 19 other Customer detail |same404 as unknown, empty list|P ownership_list_privacy_and_unsupported_mutations|scoped lookup|
| 20 DB fails mid-create |header/children roll back, safe500 and sanitized log|P real_database_failure_after_header_rolls_back_every_row|real CHECK failure after header; hardware outage NEEDS VERIFICATION|
| 21 extreme quantity |technical max2147483647 accepted with review; DB quantity bounds reject zero|P on_request_is_eligible_with_review_and_selected_currency_price; schema test|technical bound, no invented business cap|
| 22 repeated distinct operations |allowed within10 Customer/100 IP hourly attempts|P changed_payload_conflicts_but_distinct_keys_create_multiple_requests; quota tests|no arbitrary active cap|
| 23 exceeded throttle |429 Retry-After|P post_quota_counts_replays_and_does_not_change_evaluation_quota; ip_quota_blocks_other_customers_and_operates_without_redis|cache-backed attempt quotas, IP test preloads real cache counter|
| 24 hidden Product viewed historically |original IDs/quantity, no current names/prices|P replay_after_cart_deletion_catalog_hiding_and_email_change_returns_original; full_cart test|no Catalog joins|
| 25 no development migrations |development unchanged; feature cannot operate before schema rollout|read-only before/after helper +11-table inventory|operational CONFIRMED, live POST to unmigrated dev deliberately not attempted|
| 26 Redis unavailable |feature works with existing array test cache, inaccessible Redis host|P ip_quota_blocks_other_customers_and_operates_without_redis|no Redis dependency; production configured cache outage is separate|

**POTENTIAL:** local file-backed limiter storage requires coherent shared cache if future multi-instance deployment is chosen. **NEEDS VERIFICATION:** infrastructure body-log policy, arbitrary DB hardware/network outage, live production rollout and real external Google login. Offset history pagination is bounded but may shift between pages when new requests arrive. Restrictive Catalog FKs deliberately prevent deleting referenced history. Contact data retention/encryption policy is not invented here. No owner workflow exists to process submitted requests in this phase.

Additional Race F proof: `C test_database_failure_on_independent_connection_rolls_back_header_and_items` observes worker waiting on Customer, then an executed successful header insert followed by real MySQL1364 failure before children, worker transaction_level=0, no header/children committed and deep-equal Customer/Cart/CartItem rows. This complements HTTP CHECK-failure/PII-log tests. Local metadata artifacts before/after are byte-identical SHA256 `291dd66b3adc84a507a82565465c0065ad987af39d1852a0d9ce476750d7e75d`.

## 35. Owner review recommendation

Review the narrow preliminary-request behavior, privacy, restrictive history FKs, contact contract and operational migration plan. Merge remains exclusively the owner's action after a green exact-head PR review. Schema rollout/deployment and future phases remain deferred. Final recommendation pending complete local and GitHub evidence.
