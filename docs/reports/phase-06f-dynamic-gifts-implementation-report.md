# Phase 6F — Dynamic hybrid Gifts implementation report

Evidence labels: **CONFIRMED** = directly observed; **POTENTIAL** = reasoned unconfirmed risk; **NEEDS VERIFICATION** = a required gate not yet established. This report is updated as gates finish; completion is not implied by the presence of source files.

## 1. الملخص التنفيذي

**CONFIRMED:** أُضيف تنفيذ Gifts داخل Laravel لإدارة باقات هدايا جاهزة بتركيب هجين وأسعار مستقلة ونشر بإصدارات، وطلبات عميل مبدئية بلقطات تاريخية ومراجعة خاصة. اعتمد المستخدم بوابة القرارات كاملة. لا دفع أو Order مؤكد أو مخزون أو شحن أو Next.js. حالة الفحوص النهائية وطلب الدمج موثقة أدناه؛ لا إعلان إنجاز نهائي قبل نجاحها جميعاً.

## 2. Baseline SHA

**CONFIRMED:** fetched main `07b31d05fad1f99f110c862ede904291815ef07f`, merged PR9. Main Actions [38956670919](https://github.com/NabilAlsaidaly/Pharma/actions/runs/38956670919) succeeded. Historical suite rerun580tests/5240assertions,11:38.612,88MB,exit0/JUnit clean. It began before behavior changes; approved additive schema file appeared during later migration-reset cases, so this is explicitly the historical suite with draft schema present, not a claimed pristine full-files main rerun.

## 3. Branch

**CONFIRMED:** `feature/phase-06f-dynamic-gift-packages`, created from verified origin/main, no reset/force push. Original user LSP file remains preserved/excluded.

## 4. Final head SHA

**NEEDS VERIFICATION:** recorded externally in the final PR body and final delivery after the source/evidence commits, avoiding recursive self-referential documentation SHA changes. Successful CI of a prior source head does not satisfy final-head verification.

## 5. Pull Request

**NEEDS VERIFICATION:** exactly one PR will be opened after local required checks, targeting main and left OPEN/UNMERGED. No merge/deployment authorization.

## 6. Approved gate

**CONFIRMED:** [design gate](phase-06f-gift-business-design-gate.md), original eight decisions retained; explicit user reply «أعتمد المقترح كاملاً بتفاصيله وحدوده». One package/quantity1/recipient; independent positive selected Gift price; live Catalog eligibility without unit-price dependency; optional Owner occasions; mandatory recipient fields; bounded text/components/images; exact revision; individual 6D review. No dependent business schema/behavior before approval.

## 7. Implemented capabilities

**CONFIRMED:** real typed SQL package revisions/children, occasion management, private upload/preview, confirmed publication/unpublication/archive/restore, public package/occasion/media reads, Customer submit/replay/history, private Owner Gift Request review/notes/follow-up/audit. Native Arabic forms exercise hybrid composition without JSON/manual UUID editing. Detailed contracts are linked in [API index](../api/README.md).

## 8. Deferred capabilities

**CONFIRMED:** no stored value, customer-customized components, multi-package quantities/recipients, optional packaging surcharge, coupons, conversion, deadlines, legal retention duration, anonymous sending, scheduled publication, request editing, bulk Gift review, payment verification/deposit/balance/confirmed Order/stock/shipment/recipient messaging/card printing. No Next.js/final consolidated frontend handoff/6G.

## 9. Domain architecture

**CONFIRMED:** [ADR025](../adr/ADR-025-dynamic-hybrid-gifts.md) and [Gifts ownership](../architecture/gift-domain.md). Gifts Application commands own all behavior; narrow Catalog read adapter reuses authoritative selection truth while ignoring component prices. Existing pure ReviewStatus supplies approved matrix; no Checkout review persistence reuse. Shared BusinessRaster supplies technical preparation only. ManagementDashboard/Identity retain presentation/auth ownership, System Console independent.

## 10. Schema design

**CONFIRMED:** [eleven-table relational schema](../database/gift-schema.md). Permanent aggregate, typed immutable revisions and relational composition/images/occasion snapshots; separate immutable request/header/components and review/events, bounded generic audit. Restrictive FKs, composite publication/Variant ownership/review relationships, unique operation and selection identities, CHECKs and indexes. JSON only audit field names, not package/request source truth.

## 11. Migration

**CONFIRMED:** additive `2026_10_17_000001_create_dynamic_gifts.php`; no historical migration edits. Only physical guarded test database authorized. down destroys Gift history and is not an automatic recovery plan. Actual migration/rollback execution is exercised by full-schema independent-worker tests; final full-suite verification pending below.

## 12. Package aggregate

**CONFIRMED:** UUIDv7, permanent unique slug80, latest version/current publication, archival/timestamps. Required meaningful name160; optional short500/body12000/suitability1000; availability enum/order. Creation may be an incomplete publication draft; no preset record/count/price. Slugs cannot be reused on archive or silently renamed.

## 13. Hybrid components

**CONFIRMED:** catalog/manual exclusive type, quantity1..100/order0..999999999, maximum50 rows. Catalogue Product/exact optional Variant; manual names<=160/description1000. Unique normalized selection keys reject accidental duplicates. Components and header copy into the same revision transaction, no mixed public composition.

## 14. Catalog validation

**CONFIRMED:** P1 physical Variant ownership includes archived; no parent fallback or guessed Variant. Product/Brand active/unarchived, belonging exact current Variant, unavailable dominates then on_request. New invalid links/publication blocked; retained invalid draft links can be repaired. Live SQL eligibility withholds affected public package/detail/media without hidden metadata. Renames only enter a new publication; historical text stays saved.

## 15. Manual components

**CONFIRMED:** plain name/description, no Catalog IDs, no independent unit price or conversion; normalized-name duplicate rejection. Native add/remove/quantity/order editor plus server validation. Packaging examples never seeded.

## 16. Pricing ownership

**CONFIRMED:** Gifts owns selling price, no sum/inheritance from Product/Variant, including when linked individual price missing. Existing Catalog/Cart/Checkout pricing rules unchanged. Independent Catalog unit-price change does not change Gift price.

## 17. USD/SYP

**CONFIRMED:** decimal-safe strings/null, precision2, DECIMAL(18,2) technical ceiling9999999999999999.99, no float/negative/exponent/excess scale. Null unconfigured, zero not request-eligible. Publication requires at least one positive, request positive in chosen currency. No exchange rates.

## 18. Publication/versioning

**CONFIRMED:** save/media/publish/unpublish/archive/restore immutable versions and attributed event atomically; explicit consent/native dialog, prior public snapshot retained while editing draft. Archive clears pointer, restore unpublished, stable slugs retained. Exact current publication is server-owned; stale Owner edits409, customer new submissions require reviewed publication.

## 19. Occasions

**CONFIRMED:** Owner-defined active/hidden/archived records, no presets;0..20 package associations, optional Customer selection from currently active published associations. No price/deadline effect. Archive prevents fresh selection without hiding package, restore hidden. Publication/request capture labels, no historical rewrite.

## 20. Images

**CONFIRMED:** at most10 current images, publication minimum1 with alt text; explicit primary at most1 plus stable order/media UUID fallback. GD actual-content inspection/re-encoding, generated immutable private-local gifts namespace. JPEG/PNG/WebP/GIF where decoder supported; metadata/animation flattened. Source5MiB,4096/dimension,4194304pixels/output8MiB. No remote fetch or client paths.

## 21. Public media protection

**CONFIRMED:** known draft404/private Owner200, current eligible publication200, unpublish404, archive/invalid Catalog withheld. Historical bytes retained privately; public eligibility is SQL current-publication membership plus current Catalog condition. File compensation on actual SQL failure tested. **POTENTIAL:** crash or failed cleanup orphan; no claim of distributed SQL/filesystem atomicity or ability to retract admitted bytes.

## 22. Customer workflow

**CONFIRMED:** active session selects one ready-made published package/currency/optional occasion, supplies allowed sender/message/recipient data, submits operation UUID and exact version. Server revalidates and commits immutable accepted header/components. No Cart or ordinary CheckoutIntent writes.

## 23. Customer authentication

**CONFIRMED:** existing Google-only stateful Sanctum/session/CSRF, active account rechecked under row lock. Identity/email derived from server; no recipient login, bearer token or customer_id acceptance. Real external Google OAuth is not repeated here; historical authentication suite remains required.

## 24. Sender/recipient privacy

**CONFIRMED:** optional sender150 distinct from account identity; recipient name150/phone40/governorate120/city120/address500 required, notes1000 optional. Account email private snapshot. No PII in public data, summaries/global search/dashboard/generic audit; owned/Owner detail only. No anonymous mode/recipient messaging/geography fee inference.

## 25. Gift messages

**CONFIRMED:** optional1000 Unicode/plain text, no HTML/control/attachment/executable markup or automatic printed/sent card. Escaped Blade and explicit text/plain contract; browser attempted script rejected422 and did not execute.

## 26. Historical snapshots

**CONFIRMED:** request preserves package ID/version/name/price/currency/occasion/customer email/sender/message/recipient/time and relational original component names/references/quantities/order. Restrictive immutable publication reference retains presentation history. Current Product rename/archive or draft edits do not rewrite accepted facts. No update/delete endpoint.

## 27. Display-price semantics

**CONFIRMED:** display_price_snapshot is exact informational nonbinding historical price. Boolean flags explicitly deny confirmed Order, stock reservation, verified payment, scheduled delivery, binding price and calculated included shipping. No final_total/payment_due/total_paid/balance/deposit is fabricated.

## 28. Customer idempotency

**CONFIRMED:** permanent unique Customer+UUIDv4/v7 with canonical normalized fingerprint, stable key order/nulls/lowercase UUIDs. Active Customer lock then committed replay before current package/version/occasion/email eligibility; identical replay original ID/time, changed payload409. Current email excluded from fingerprint, snapshot unchanged. Rejected/retried attempts still quota-counted.

## 29. Concurrency/lock graph

**CONFIRMED:** [documented graph](../architecture/preliminary-gift-requests.md). Customer→replay→Package→Occasion→Products→Variants→Brands; Owner package writes→Owner→replay→Package→sorted Occasions→same Catalog order; occasion-only no reverse Package; review Owner→replay→immutable Request→review/events. No new Customer/Cart lock in admin or reverse Catalog→Gift. Retry3 does not replace stale checks; no universal deadlock-free claim for external writers.

## 30. Owner review

**CONFIRMED:** approved shared state matrix, inferred new/version0 and no GET writes; individual only, reason-required reviewed→under_review, private cumulative note/follow_up. Separate SQL aggregate and append-only events; timestamps/Owner attribution/version exact. No financial/request fact mutation.

## 31. Audit

**CONFIRMED:** fixed package/occasion/review targets, UUID/Owner/action/previous+nextversion/time/request correlation/operation fingerprint, changed-field names only. Full note/recipient/HTTP body absent. Private review event retains note and Owner ID, current display name joined. Raw DBA modifications outside HTTP append-only guarantees.

## 32. Public API inventory

**CONFIRMED:** GET packages/list+slug, occasions, media under /api/v1/gifts; exact [public contract](../api/gift-public-api.md). Bounded SQL-before-pagination eligibility, string versions/prices, safe projection and public Resources. Unknown filters rejected, missing/ineligible404, missing schema503.

## 33. Customer API inventory

**CONFIRMED:** POST requests; GET own requests/list+UUID, [contract](../api/gift-customer-api.md). POST201/identical replay200; summary PII omitted, detail original owned facts only; no Owner review notes/states. Strict object/nested field allowlists.

## 34. Owner web inventory

**CONFIRMED:** thirteen additive protected route definitions,40→53 total, three additive destinations9→12. [Exact table](../api/owner-gifts-web.md). Existing Arabic RTL shell/guard/session retained. Owner directories/filters bounded, no JSON/UUID editing requirement. Native optional confirmation dialog with server-required consent fallback.

## 35. Validation

**CONFIRMED:** fixed actions, version/operation, bounded Unicode plain fields, decimal and strict component integer policy, exclusive types/IDs/Variant ownership, duplicates, archive/publication state, current eligibility, input/body quotas. Optional blank/null semantics distinct from unknown fields; never trust Customer price/contents. Dates/identifier search/pagination bounded. Individual field caps do not override combined HTTP/proxy limits.

## 36. Security tests

**CONFIRMED:** current complete focused105tests/963assertions covers all13 Owner destinations for Guest/Customer and4 revocation states, native CSRF, body/attempt caps, stored-XSS inputs, unsafe raster cases, known private URLs, actual SQL failure compensation/rollback and safe logging, constraints. Full685-test local regression also passed before the final equivalent optimizer controls. No penetration-test/WCAG certification claim.

## 37. Cross-Customer IDOR

**CONFIRMED:** nonowned and unknown UUIDs share404 envelope; summaries omit contact/message/email, Customer detail excludes Owner review. Package/public APIs never contain request PII. Existing active-account guard preserved.

## 38. Real MySQL races

**CONFIRMED:** final optimized focused run11 independent-process races, physical connections8112–8153; complete local full run also used7692–7733. Owner edits, publish/unpublish, components, media, duplicate/conflicting/replayed and two independent new Customer operations, Catalog archive, Package unpublication, review tabs. Actual SHOW FULL PROCESSLIST barriers observed and distinct PHP worker connections; stale409/one complete committed revision, duplicate same request, independent operations two distinct requests, rollback/no mixed snapshots. All observed attempts1/no observed deadlock; not proof against all possible external lock graphs. Cleanup verifies resolved temporary/disk roots before recursive fixture deletion. Initial ten-case milestone10tests/99assertions,1:50.560 is retained only as historical evidence.

## 39. SQL query counts

**CONFIRMED:** final optimized focused measurements and prior complete full run agree: empty public4; at10/50/200 identical11 paths: public list5/occasion filter5/detail5, Owner list4/edit+composition14, Customer history3, Owner requests4/detail+timeline7/audit4, publish22/requestcreate18. Three current components, occasion, growing package revisions/requests/audit/events. Current-only bounded projections, no component-history hydration or per-image filesystem probing; actual page/count EXPLAIN verifies history-bounded probes separately from network query counts.

## 40. EXPLAIN

**CONFIRMED:** actual MySQL9.7 plans inspected: Customer history covering index (initial mixed order required filesort, corrected descending-date/ascending-ID index removed it), review timeline covering reverse index, exact revision unique lookup, media/occasion reverse covering indexes. The initial public antijoin materialized all1326 historical component rows in the measured fixture. The final fixed STRAIGHT_JOIN starts with200 Package rows, unique current-revision lookup and indexed dependent component eligibility (NO_SEMIJOIN), avoiding unrelated revision/component history; page and COUNT plans are tested separately. Sort of current packages may still filesort; constant network query count/optimizer estimates are not throughput evidence. Primary engine rationale is linked in [publication design](../architecture/gift-publication-and-revisions.md). CI MySQL8.4 plans still **NEEDS VERIFICATION**.

## 41. Browser evidence

**CONFIRMED:** real enforced-native-CSRF synthetic Owner interface on guarded physical sumaya_pharma_test, temporary local server/private media. Hybrid Product/exact Variant/manual composition, quantity/order, native draft save, image upload/private404/public200, primary, confirmed publish, draft preserving old name/version4→6, stale second tab409, dynamic occasion association/new publication8, private request snapshot,7 review events including reopen, note/follow-up, rejected script422, unpublish/archival/restore and component removal/replacement. Before/after27 pre-existing fixture tables MATCH;20 Gift audit events/one Gift Request. Server/browser stopped after proof. Initial CLI stale references/PowerShell argument failure are not counted as UI success.

## 42. Responsive evidence

**CONFIRMED:**14 states×375/768/1440=42 document-width checks without horizontal overflow: draft-primary, published, stale409, occasion edit/list, package list, request reopened, validation422, filtered request list, audit, unpublished, archived, restored plus final directory-create form. Final directory rerun verified all three directory page controls, native GET submission of Product/Occasion page2, responsive menu keyboard/Escape/focus restoration, no pageerror and another27-table MATCH with0 Gift writes. Screenshots privately ignored under output/playwright: phase6f-package-375/1440,confirm-375,review-768,validation-375,directory-375. Visually reviewed confirm375/package1440/review768/validation375/directory375; Arabic RTL readable, native focus returned on Escape, required consent/reopening reason enforced, private preview denied Guest302. Scoped completed scenarios no pageerror; deliberate rejection resource errors422/409 are expected.

## 43. Accessibility limits

**CONFIRMED:** semantic labels/fieldsets/headings/native controls, keyboard add/remove/order, confirmation dialog/cancel/Escape/focus restoration and responsive document widths. **NEEDS VERIFICATION:** comprehensive screen-reader/native zoom/contrast/WCAG audit; no certification claimed. Numeric order input remains usable without dragging; JS needed to add/remove form rows.

## 44. Historical compatibility

**CONFIRMED:** local full685-test regression and protected staged diff audit passed. No legacy business mutation/schema edits; only four existing route/navigation inventory assertions updated. Config/manifests/locks/System Console, Cart/Orders/Favorites/Settings/Content and Catalog Domain/Application/Infrastructure plus PAY001 unchanged. Allowed adapter changes add scoped Gift error headers/logging/body dispatch/navigation/routes. Exact-source/full final-head CI remains a separate gate.

## 45. Full PHPUnit

**CONFIRMED:** complete local685tests/6240assertions,14:06.388,92MB,PHPUNIT_EXIT=0; JUnit zero errors/failures/skips. This includes all580 historical tests plus105 Gift cases across9 classes. Afterwards, EXPLAIN-driven SQL join/optimizer controls and safer fixture-root cleanup were added without changing business rules; complete current focused105tests/963assertions,2:18.401,68MB,exit0/JUnit clean then passed. Exact-source full CI is independently required below; the earlier full local result is not mislabeled as a post-optimization full run. Earlier exploratory/aborted/failed runs are not claimed passed.

## 46. Assertions

**CONFIRMED:** local complete suite6240 assertions before the final optimizer-control/check additions. **NEEDS VERIFICATION:** final-source and final-documentation-head CI assertion totals are independently observed, not inferred by summing partial runs.

## 47. Pint

**CONFIRMED:** existing full Pint --test passed after formatting; no ignore/config changes. Recheck if PHP changes before source freeze.

## 48. PHPStan

**CONFIRMED:** existing level5/512M passed. Found/corrected actual boolean abort check and inaccurate inferred auth model usage; no baseline/suppressions/config/strictness changes.

## 49. Composer

**CONFIRMED:** validate --strict, check-platform-reqs on PHP8.4.26, audit all passed; no vulnerability advisories. No dependency/manifest/lock changes.

## 50. Vite

**CONFIRMED:** locked offline npm ci installed87 existing packages; Vite7.3.7 production build60modules, CSS53.51kB/gzip11.84, JS56.23kB/gzip21.24. Existing npm esbuild install-script advisory recorded; build succeeded. No Next.js or frontend dependency.

## 51. Dependencies

**CONFIRMED:** native Laravel/GD/Brick Math existing installations reused; no new package/service/admin framework/Redis dependency. Lockfiles/manifests unchanged; compile/screenshot/JUnit/helper artifacts remain ignored.

## 52. Development preservation

**CONFIRMED:** read-only before/after full-suite and optimized-focused comparison MATCH: physical sumaya_pharma/MySQL9.7.0/RR,11tables,1Customer,3applied migrations; identical full sorted rows/SHOW CREATE/.env hashes, metadata SHA256291dd66b3adc84a507a82565465c0065ad987af39d1852a0d9ce476750d7e75d. Seven migrations remain pending (ten files/three applied). Real private/public media roots contain no non-.gitignore files. Independent safe test-environment read confirms testing/sumaya_pharma_test/MySQL9.7.0/RR. No development/production migration/real Owner/environment change.

## 53. Real Owner provisioning

**CONFIRMED:** none. Browser/workers provision only synthetic accounts behind testing/physical-DB guards. No real credentials/environment secrets generated or logged. Existing user LSP file SHA2568D0F5B931FE0A63F90D8D515C77C4219BCA53B08DFC9FF747EF17F5D264FE97F retained.

## 54. Source/schema rollout

**CONFIRMED:** not performed/authorized. [Separate operational prerequisites](../deployment/gift-testing-and-rollout.md): backup/physical target, all older migrations then Gift schema, coordinated source/schema, real Owner separately, TLS/cookies/CORS/GD/private disk/PHP proxy limits/shared sessions+limiter/PII-safe monitoring/retention. Missing development schema deliberately remains unavailable; source alone is not live readiness.

## 55. Risks

**POTENTIAL:** Owner-row serialization limits write throughput; relational revision copying increases storage/write volume; bounded selectors/pagination need narrowing; SQL/filesystem orphans; data retention/admin recovery require operational policy; trusted DBA/raw SQL outside append-only guarantees; public response already admitted before state change cannot be retracted. Production capacity, full assistive tech and multi-node storage/session infrastructure need separate verification.

## 56. Future payment/fulfillment

**CONFIRMED:** PAY-001 unchanged; no Order confirmation/reservation before future verified payment and authoritative availability/fulfillment check. Gift display price never activates6E payment/delivery preparation. No stock allocation, shipping promise or notification dispatch. Remaining commercial execution is a future milestone, not silently implemented.

## 57. CI URL/conclusion

**NEEDS VERIFICATION:** source CI then report-only evidence commit, followed by successful workflow/job/log verification for the actual final PR head against verified main. Final SHA/CI URL/result recorded in external PR/final delivery without another recursive report commit. Previous successful main/Phase6E/source CI alone cannot close this gate.

## 58. Merge-readiness assessment

**CONFIRMED:** implementation/local regression/current Gift focused checks, tooling, browser, preservation and protected diff are ready for PR review. **NEEDS VERIFICATION:** full exact-source and final documentation-head CI. No merge, deployment, development/production migration, real Owner or Phase6G. Successful final CI supports Owner review and deliberate merge consideration; operational activation remains separately authorized.
