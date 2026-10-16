# Phase 6E — Store settings and content management

Date: 2026-10-16, Asia/Damascus. CONFIRMED = directly verified; POTENTIAL = reasoned risk; NEEDS VERIFICATION = not yet verified. Exact final self-referential hash/CI recorded independently in PR/final delivery.

## 1. Arabic executive summary

CONFIRMED: أُضيفت مساحة عربية لإعدادات المتجر والمحتوى داخل لوحة المالكة الحالية، مع مسودات وإصدارات محفوظة ونشر صريح وسجل تغييرات. معلومات المتجر والعملات للعرض، بينما الدفع والتوصيل تجهيز فقط. وافقت المالكة على القرارات كاملة مع تأجيل إيقاف استقبال الطلبات. نجح التشغيل الكامل بـ580 اختباراً و5240 تحققاً، وتطابقت بصمات قاعدة التطوير وملف البيئة. تبقى نتيجة CI للرأس النهائي شرطاً مستقلاً قبل إعلان اكتمال التسليم.

## 2. Starting main SHA

CONFIRMED: fetched origin/main7427c8a7032973792e0cd77f2a2abaa07157ab78, merged PR#8; connector confirms PR8 closed/merged with matching merge SHA. Actual main [CI38913703844](https://github.com/NabilAlsaidaly/Pharma/actions/runs/38913703844) completed success, independently read from GitHub Actions API.

## 3. Feature branch

CONFIRMED: feature/store-commercial-settings-and-content created from verified current main, normal fast-forward/switch, no reset/force. Pre-existing untracked storage/framework/lsp-74da1b7d667d6302.php preserved/excluded, SHA2568d0f5b931fe0a63f90d8d515c77c4219bca53b08dfc9ff747ef17f5d264fe97f.

## 4. Final feature SHA

Final feature SHA is independently recorded in PR/final delivery after this report's commit, avoiding an impossible self-referential hash. Source head3d5e14908d24c04b6ff8b624d4bbd1eb26da5cb2 is verified below; final documentation-head CI must independently succeed before delivery.

## 5. Pull Request URL

CONFIRMED: exactly one [Phase6E PR#9](https://github.com/NabilAlsaidaly/Pharma/pull/9) against main, OPEN/UNMERGED, source head3d5e14908d24c04b6ff8b624d4bbd1eb26da5cb2. No merge/deployment or subsequent milestone.

## 6. Design gate

CONFIRMED: [consolidated design gate](phase-06e-business-configuration-design-gate.md) created before new schema activation/commercial implementation. Three categories separate safe information, prepared payment/delivery, existing-flow controls. Exact field consumers, publication, money bounds/null/zero and no intake switch recorded.

## 7. Owner policy approvals

CONFIRMED: Owner explicitly replied «أعتمد المقترح كاملاً مع تأجيل إيقاف الاستقبال» on2026-10-16. Applies to proposed two-decimal full/deposit, independent preparatory fees/zone identity, explicit informational publication and complete intake pause deferral; earlier Phase6D approval was not substituted.

## 8. Actual configuration catalog

CONFIRMED: fixed [typed catalog](../architecture/business-settings.md) and [Content fields](../architecture/storefront-content-management.md). Profile name/description/about/contact/hours/five social links/logo; currency order/labels/symbols; full/deposit percentage/explanation; zones/governorate/city/independent fees/order/conditions; four explicit content types. No arbitrary keys, credentials or runtime .env editor.

## 9. Settings groups

CONFIRMED: profile/currency/payment singleton aggregates; multiple zones. Missing is unconfigured, genuine save only creates records, optional blank clears/null, omission preserves. Minimum required fields validated; draft and published pointers distinct, preparatory groups cannot publish.

## 10. Data model

CONFIRMED: eight distinct typed aggregate tables plus eight corresponding typed revision tables and one bounded metadata audit table. Positive unsigned versions, UUIDv7, restrictive/composite FKs, unique singleton/slug/zone/op identities, typed decimals and CHECKs. No entire application JSON; only changed-field names JSON in events.

## 11. New migrations

CONFIRMED: one additive2026_10_16_000002 migration,17new tables, depends on prior Owner/project schema. Existing migrations unchanged. Required fields NOT NULL, optional null meaningful. Destructive down removes new history after dropping composite pointer FKs. Isolated MySQL migration/rollback only; no development/production rollout.

## 12. Domain ownership

CONFIRMED: Settings owns configuration/payment/delivery preparation and commands; Content owns drafts/publication and commands; separate HTTP adapters. Shared technical validation/raster/SQL/revision/read coordination uses fixed server catalogs. Payment completeness stays in Settings. Delivery/Payments execution remains future, Orders submission/review untouched, ManagementDashboard only shell/navigation, System Console untouched.

## 13. Store identity

CONFIRMED: required name160, optional description500/about6000, secure optional logo; explicit published revision consumed by configuration API. Owner private preview/history; missing public profile null. No fictitious production business identity. Owner shell branding remains the existing administration brand.

## 14. Contact settings

CONFIRMED: optional email254/phone30/address500/hours300 validated. Public contact is explicitly Owner-entered, separate from Customer contact. Hours informational only, no automatic closing/timezone change. No messaging/email dispatch.

## 15. Social links

CONFIRMED: Facebook/Instagram/WhatsApp/TikTok/YouTube fixed host contracts, HTTPS, bounded URLs, no credentials/ports/control/protocol-relative/executable scheme, no remote fetching. Configured links publish in fixed order; absent links omitted. No scripts/embeds or automatic actual social accounts.

## 16. Currency preferences

CONFIRMED: existing USD/SYP only, first_currency and explicit labels/symbols, separate publication. Presentation consumer only; no conversion, exchange rates, prices or currency activation flag. Tests preserve historical Product/Cart/Checkout facts.

## 17. Payment policy preparation

CONFIRMED: immutable prepared full100.00/deposit revisions with Owner attribution and optional explanation. No active Checkout rule/public percentage, method/payment account, paid amount/proof or Order. UI clearly says future integration only; PAY001 unchanged.

## 18. Deposit validation

CONFIRMED: canonical decimal strings,0.01–99.99at two decimal places; full exactly100.00. Reject zero/negative/100deposit, precision>2, exponent, nonnumeric and JSON floats. Integer basis points only for bounds, no binary float calculations. Real MySQL CHECK also rejects invalid deposit.

## 19. Payment revision history

CONFIRMED: every successful change/action has unique target/version immutable typed snapshot and append-only event. Existing revisions retained when policy changes/archive/restores. Future integration must select a stable explicit revision and define rounding/evaluation/verification, not silently read newest global percentage.

## 20. Delivery zones

CONFIRMED: explicit Owner-entered zone name/governorate/optional city/order/conditions. Normalized governorate/city whitespace hash unique including archive. No seeded served areas, fallback, inferred overlap precedence or dispatch promise; archive/restore preserves history.

## 21. Delivery fee handling

CONFIRMED: independent nullable USD/SYP DECIMAL(18,2), null unknown,0.00deliberately prepared free, max9999999999999999.99technical ceiling. No negatives/float/exponent/excess precision, no conversion/Cart fee addition. Real SQL nonnegative CHECK. Both fees may be absent; no eligibility inference.

## 22. Content management

CONFIRMED: real banners/pages/FAQ/policies CRUD draft forms, private preview, upload where relevant, ordering via draft sort field, explicit publish/unpublish/archive/restore and bounded revision history. No physical deletion, generic website builder, HTML editor, scheduler or fabricated content.

## 23. Banner publication

CONFIRMED: required title/alt/order and processed existing raster before publish; immutable image path/old bytes preserved, safe optional internal/HTTPS destination. Published sort comes from snapshot. Batched Catalog visibility suppresses hidden/archived/missing known destination to null. Gallery has thumbnails; no per-banner filesystem read on public listing.

## 24. Static pages

CONFIRMED: explicit Owner title/slug/body/optional SEO fields, private draft and permanent unique slug. A draft rename is rejected to prevent freeing a still-live published address. Public response reads only pointed snapshot; historical revisions privately inspectable.

## 25. FAQ

CONFIRMED: question300/answer6000/order, plain text, explicit publication, deterministic snapshot sort/id, bounded20default/50max public page and25Owner history/list. Drafts/archived absent, no fictional FAQ seeds.

## 26. Policy document versioning

CONFIRMED: privacy/terms/returns documents are Owner-supplied plain text with explicit confirmation. Draft edits do not rewrite published text; browser independently confirmed old published revision stays visible. Immutable published history retains timestamp/actor. No legal template/acceptance log/Checkout consent or retrospective request change.

## 27. Published public API behavior

CONFIRMED: [five resource groups](../api/public-storefront-content.md) under/api/v1/storefront; existing success/error envelopes, safe explicit projections, canonical revision strings, pagination/unknown filters,503schema/404unpublished behavior. No preparations/audit/Owner identity/Customer data/secrets/disk path. Existing Customer APIs preserved.

## 28. Next.js boundary

CONFIRMED: future Next.js consumes Laravel authoritative published information, owns rendering/layout only. No Next.js code/dependencies, independent business rules or final frontend handoff package. Existing CORS exact-origin/session security not loosened.

## 29. Feature controls

CONFIRMED: actual publication/unpublication and archive/restore controls have real public-read consumers. Intake switch intentionally absent by approved deferral; no fake toggle/security/Redis/backup/operational settings.

## 30. Activation semantics

CONFIRMED: save creates a versioned private draft; publish selects exact snapshot; edit preserves current public pointer; unpublish/archive clear pointer; restore remains unpublished. Payment/delivery save means prepared, never effective on current transactions. Readiness derives real counts from SQL, not decorative success states.

## 31. Immutable submission preservation

CONFIRMED: real Customer API before/after test freezes only observation time and deep-compares Identity/Catalog/Cart/Favorites/history resources and original rows. Browser hashes10original fixture business tables: MATCH after49settings/content events in the complete UI run, and again after35events in the final private-media run. No Checkout/customer/cart/catalog business writes in new services.

## 32. Audit history

CONFIRMED: Owner ID, fixed target type/UUID/action, previous/new version, bounded changed field names, canonical operation fingerprint, request correlation and timestamp. Event/revision/state atomic. Long legal text lives only in typed revisions, no full HTTP body/credentials in generic JSON. Owner display name joined in bounded history.

## 33. Optimistic concurrency

CONFIRMED: expected canonical unsigned version string, exact comparison under target lock; initial absence0, success increments via existing Brick Math. Stale409 with business-specific Arabic refresh guidance, no silent overwrite. BIGINT max overflow rejected; public revisions explicitly serialized as strings for JavaScript safety.

## 34. Idempotency

CONFIRMED: unique Owner+operation UUID across new actions, canonical hash binds table/target/action/version/sorted normalized fields/re-encoded image digest. Committed identical replay before stale check returns original target with no extra revision/event; different payload409. Real concurrent duplicate test creates one row/revision/event.

## 35. Database locking order

CONFIRMED: current Owner row FOR UPDATE and active/credential recheck, prior operation lookup, target row FOR UPDATE, draft/revision/pointer/event SQL, commit/retry3. Owner lock intentionally serializes all this low-volume configuration writer's operations and first singleton creation. No Customer/Cart/Catalog lock acquisition/reverse dependency; image decoding before locks, no remote work/rendering in tx.

## 36. Input validation

CONFIRMED: strict top/nested allowlists, fixed types/lengths, optional null/clear, strings/Unicode controls, safe URLs/email/phone/slugs, two-decimal money, exact versions/UUIDs and bounded page/revision queries. Nonmedia body64KiB, uploaded media body8MiB, source5MiB. Server validates native form/file arrays. No mass assignment.

## 37. Owner authentication/security

CONFIRMED: all11new definitions covered for Guest/Customer denial and disabled/restricted/idle/revoked Owner. Both writes require real native CSRF; server derives Owner. No-store/referrer/CSRF/body/quota/revocation retained, public write405, no admin alias.40Owner definitions (29retained+11), nine navigation destinations.

## 38. Content/XSS protection

CONFIRMED: plain text only, Blade escaping, JSON body/answer_format=text/plain; reject scripts/HTML/control/encoded-angle injection/oversize. Valid Arabic/ordinary symbols survive. Future frontend must text-render, not innerHTML. No iframe/script/remote embed; unsafe links rejected without retrieval.

## 39. Media security

CONFIRMED: secure separate shared raster helper uses existing Catalog security principles without calling Catalog business upload. Real MIME/extension/name, source5MiB,4096dimension/4,194,304pixels, GD decode/re-encode PNG≤8MiB, immutable UUID paths, old file retention, actual SQL-failure compensation and competing-upload test. Private preview/current-publication binary gating and crash/orphan limitations explicitly documented.

## 40. Caching

CONFIRMED: live SQL/no-store public reads, no Redis/TTL/ETag cache or stale admission Boolean; no read-per-field query. Current published pointer invalidation is transactional SQL. Configuration RR snapshot; collection count/page are separate committed reads. Future shared/CDN cache requires its own revision/invalidation contract.

## 41. Query counts

CONFIRMED: empty overview3SQL. At1/20/100records (banners capped20, FAQ/zones reach100, history grows20revisions each stage), ten real HTTP counts identical: overview3/edit7/configuration8/banners5/FAQ5/policy4/zones6/history4/publish11/update11. No body loading to count, no unbounded history/N+1. Query equality is not throughput/latency evidence.

## 42. EXPLAIN findings

CONFIRMED: local9.7actual EXPLAIN: event time/id covering reverse index scan rows25; per-record FAQ revision uses se7_revision_unique covering reverse lookup rows1. Source CI MySQL8.4.11 independently confirms store_event_time_index type=index/rows25 and se7_revision_unique type=ref/rows1, both Backward index scan;Using index, with identical ten-path counts at1/20/100. These demonstrate selected indexes at measured data, not guaranteed plans at arbitrary scale.

## 43. Real MySQL concurrency evidence

CONFIRMED: six real independent-process MySQL cases Afirst singleton stale tab; Bcompeting policy publication one pointer; Cidentical operation one change/both replay; Dcompeting images one safe retained file; Ezone same-version fee conflict; FCustomer committed retry plus Settings change, original request unchanged. Held SQL row barriers/SHOW FULL PROCESSLIST/physical IDs3450–3471 initially, repeated4747–4768 after private storage hardening and5518–5539 in the final full run, no observed retries/deadlocks, attempts1. No sequential test called concurrency. RaceGintake omitted because control explicitly deferred.

## 44. Browser evidence

CONFIRMED: real Playwright CLI/native CSRF on isolated synthetic fixture. Profile save/logo upload/publish and empty consent blocked; prepared25.50%deposit; explicit zeroUSD/nullSYPzone/no publish button; banner upload/publication; FAQ publication; policy draft preserving published body; page draft/unpublish404/archive/restore unpublished; currency presentation.13page states×375/768/1440 with no document overflow, plus corrected validation page at all3sizes after animation-frame stabilization. Keyboard Space confirmation and menuTab/Escape/focus return.Complete first UI run49events; final private-media browser rerun35events independently preserved all10original table hashes, verified draft/public/private status404/200/200 and unpublish404 for known asset URL, plus valid banner PNG/public revision string. No JS runtime errors; expected rejected422 produces one browser resource-error entry per validation session. Validation message/Blade compilation issue found and fixed before final run. Screenshots output/playwright/phase6e-settings-375.png,banners-768.png,policy-1440.png,validation-375.png; local ignored only. Browser/server stopped before final SQL tests.

## 45. Accessibility limitations

CONFIRMED: semantic RTL labels/headings, native required controls/consent, visible browser focus, LTR inputs/identifiers where needed, responsive wrapping/navigation/gallery. NEEDS VERIFICATION: native zoom, full screen-reader/contrast conformance and production capacity; not claimed complete accessibility certification.

## 46. Existing regression results

CONFIRMED: verified baseline full511tests/4498assertions,05:10.024/86MB,PHPUNIT_EXIT=0, JUnit. All historical tests retained; only route inventory29→40/cache40/navigation8→9 changed for actual added routes. Source business/Customer contracts preserved. Final latest schema/projection run is distinct from baseline and focused results.

## 47. Total PHPUnit tests

CONFIRMED: latest complete suite580tests/5240assertions,09:07.812,90MB,PHPUNIT_EXIT=0; JUnit zero errors/failures/skipped.511historical cases retained plus69new cases across8classes. Independently passed focused62/563, performance1/38, compatibility4/48 and private-storage69/688; these are not added to or substituted for the full result. Earlier interrupted full runs are not claimed successful.

## 48. Total assertions

CONFIRMED: latest local full5240assertions/580tests, JUnit/output verified. Source CI independently passed580tests/5240assertions in220.16seconds on PHP8.4.26/MySQL8.4.11. Logs show all six independent-process races with connections659–681,attempts1, expected stale409/replay/safe image outcomes. Final documentation-head CI independently verified externally after this evidence commit. Baseline/focused counts are not substituted.

## 49. Pint result

CONFIRMED: explicit new-file formatting and final full Pint --test pass on the final source, after correcting two method-argument spacing findings. No broad suppression.

## 50. PHPStan result

CONFIRMED: existing PHPStan level5/512M passes without config/ignore/baseline changes. Final current-source recheck passed immediately before complete580-test execution.

## 51. Composer checks

CONFIRMED: composer validate --strict, check-platform-reqs and audit pass, PHP8.4.26, no vulnerability advisories. Composer manifests/lock unchanged.

## 52. Vite result

CONFIRMED: locked npm ci --offline --no-audit --no-fund installed87existing packages; Vite7.3.7/59modules build passes, CSS52.95kB/gzip11.75, JS54.49/gzip20.60. Existing npm11esbuild install-script advisory nonblocking. No new manifest/lock/dependency/CDN.

## 53. Database preservation evidence

CONFIRMED: actual configured+physical development sumaya_pharma9.7.0/REPEATABLE-READ,11tables,1Customer/3applied migrations/other9empty. Before sorted rows/SHOW CREATE/.env metadataSHA256291dd66b3adc84a507a82565465c0065ad987af39d1852a0d9ce476750d7e75d. Test suites/workers positively guard APP_ENVtesting/mysql/configured+physicalsumaya_pharma_test before migrations. CONFIRMED: final after deep comparison matched every sorted row/schema/.env hash; identical metadataSHA256. Three development migrations remain applied; six prior/new migrations remain pending. No development/production schema/data/.env write.

## 54. Real Owner provisioning status

CONFIRMED: synthetic isolated test Owner only; no real Owner provisioning/Customer modification/public media/.env/Google change. Existing untracked LSP helper retained unchanged/excluded.

## 55. Changed files

CONFIRMED: new Settings/Content application/HTTP/read classes, shared bounded validation/raster/revision/read coordination, additive schema, Owner views,69new test cases across8classes plus fixture base/independent worker, gate/ADR024/architecture/database/security/API/report. Existing changes limited to private route/navigation/body/error adapters, additive public reads, three historical inventory tests and doc indexes/boundaries. Final staged diff:55files,44new/11modified,2840insertions/7deletions before this metadata sentence; no whitespace errors.

## 56. Dependency changes

CONFIRMED: none. Existing Laravel13/PHP8.4/MySQL/Blade/Tailwind/Vite/GD/native JS and already-installed Brick Math only. No new Composer/npm/Redis/queue/admin framework/Next.js dependency.

## 57. Final-head CI URL/status

CONFIRMED: source head3d5e14908d24c04b6ff8b624d4bbd1eb26da5cb2, [CI run33](https://github.com/NabilAlsaidaly/Pharma/actions/runs/38927763425), quality job117063595204, every step successful including locked frontend build, Pint, PHPStan,580tests/5240assertions and security audit without advisories. Real server log confirms MySQL8.4.11, installed PHP8.4.26. Final report-evidence head SHA/run/job/results are independently recorded in PR/final delivery after verification, avoiding report self-reference. No prior-main/source result substitutes for exact final feature HEAD. No merge/deployment.

## 58. Remaining risks

POTENTIAL: single-Owner serialized writer throughput; SQL count/offset cost scales with data; collection totals and rows can span publication; operational raw media deletion can break a published asset; crash between disk and SQL can orphan files. Trusted DBAs can alter history. Shared multi-node session/limiter deployment and full assistive-tech/capacity audit need operational verification.

## 59. Deferred commercial rules

CONFIRMED: intake pause deferred by approval; payments/proof/verification/Orders/stock/fees-at-Checkout/geographic overlap/fallback/fulfillment/notifications/queues/Redis/System Console/Next.js/final handoff/legal text remain excluded. Future payment/delivery integration must select stable revisions and explicitly define calculations/rounding/eligibility/verification.

## 60. Rollout prerequisites

CONFIRMED: separately authorize backup and physical-target verification, apply all pending prior+new migrations in order, coordinate source/schema, provision real Owner separately, verify private prefix/TLS/proxy/cookies/GD/private local storage visibility/serving/permissions/body limits/session/cache/shared limiter/log body exclusion/history retention. Down destroys new history. None of these development/production operations executed here.

## 61. Merge-readiness assessment

CONFIRMED: Owner-approved implementation, local full580/5240, independent focused/race/browser evidence, development preservation and source CI all pass. Exactly one PR#9 remains OPEN/UNMERGED and is ready for Owner review subject to independently verified successful final documentation-head CI, recorded in PR/final delivery before declaring completion. Rollout prerequisites remain separate authorized operational work. No merge/deployment/next phase.
