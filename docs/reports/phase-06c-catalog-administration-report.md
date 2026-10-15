# Phase 6C — Catalog administration implementation report

Date: 2026-10-15 (Asia/Damascus client context). CONFIRMED = observed; POTENTIAL = reasoned risk; NEEDS VERIFICATION = unproven. This source report is prepared before publication; exact final feature SHA/CI proof is independently recorded in the PR description and final delivery after the documentation evidence commit, because a file cannot contain its own commit hash.

## 1. Arabic executive summary

أُضيفت إدارة عربية مخصصة للكتالوج داخل Laravel: إنشاء وتعديل المنتجات والعلامات والاهتمامات والخيارات، أسعار مستقلة، وسائط آمنة، أرشفة واستعادة، وعمليات جماعية محدودة ومؤكدة مع سجل تدقيق دائم. تُحفظ المفضلة والسلة والطلبات السابقة. لا يوجد حذف نهائي أو تغيير مدفوعات أو مخزون أو ترحيل لقاعدة التطوير. تبقى أدلة المتصفح والالتزام النهائي وCI شروطاً مستقلة قبل إعلان اكتمال التسليم.

## 2. Starting commit

CONFIRMED: fetched origin/main, verified merged PR6 main `a15753aa79b8bf1cde96d7e3fc11928522b776b7`, clean starting worktree. Reran original behavior baseline394tests/2970assertions before activating schema/model changes (02:27.745). No historical count represented as new work.

## 3. Final feature commit

Implementation/source and final documentation evidence hashes are recorded after publication in sections5/53 and PR description. Final delivery must match local HEAD/remote feature head and successful final-head workflow. Pending until independently checked.

## 4. Feature branch

`feature/catalog-administration-foundation`, from verified current main; no destructive reset or force push.

## 5. Pull Request URL

Pending one Phase6C PR against main; must remain OPEN/UNMERGED. No merge or deployment authorization.

## 6. Exact authorized scope

Catalog create/edit/visibility/availability/recommendations/associations, controlled Variants, secure Product images/Brand logos, five-entity soft archive/restore/recycle, bounded confirmed synchronous bulk, transactional audit and necessary public/management read integration. No Phase6D/staff/inventory/payment/confirmed Order/visitor collection/Redis/System Console implementation.

## 7. Design-gate findings

[Mandatory design gate](phase-06c-catalog-write-design-gate.md) completed before behavioral writes: six original Catalog tables, restrictive customer/history FKs, Product-first admission locks, raw management readers, reserved slugs and generated primary-image uniqueness. Conservative explicit all-physical-Variant ownership avoids archive-to-direct transition; no unresolved policy needed for implemented operations.

## 8. Architecture decisions

Catalog Application/Administration services own writes; existing Eloquent invariants/setters remain authoritative. Thin protected web controller/FormRequest, existing Blade dashboard/native JS, fixed typed registry/actions. No generic SQL editor or second source of business truth. [ADR022](../adr/ADR-022-catalog-administration.md).

## 9. Domain invariants preserved

P1/C2/V1/S1/F1, Customer/Cart retention and PAY001. Physical UUID/FK/unique/CHECK integrity unchanged. Hidden differs from archived; no automatic publishing/stock logic or historic rewriting.

## 10. Product creation

Existing fields only, hidden/unavailable/not-recommended defaults unless explicitly chosen; nonarchived Brand, generated or manual unique slug, optional nullable independent prices/description, UUIDv7. Arabic help explains public visibility and informational zero/null prices.

## 11. Product editing

Explicit allowlist; omit preserves, explicit empty nullable input clears. Name changes preserve established slug. lock_version under row lock rejects stale tabs409. No ID/timestamp/customer/account reassignment or arbitrary mass assignment.

## 12. Brand management

Name/slug/description/status/order/meta fields and secure logo replacement. Archive/restore preserves child identities/statuses; confirmation counts affected Products and warns public visibility. No merge/cascade/hard-delete.

## 13. Concern management

Create/name/slug/status/order, archive/restore preserving unique pivots. Existing archived links can be retained; new archived association rejected. Explicit empty associations detach only pivots; max100distinct IDs, no Product deletion or hierarchy.

## 14. Variant management

Add/edit name, independent prices, declared availability/order; original parent immutable; current list25/page and parent-scoped recycle link. Archive/restore preserves composite references. Nonarchived original parent required for child operations.

## 15. Price ownership

ALL physical Variants reserve price ownership. Parent save uses Product lock then physical Variant locking current read. First Variant requires explicit prior clearing of both parent prices, never silent copy/clear/default. Restore cannot revive dual ownership; structural/price races independently tested.

## 16. Currency validation

CatalogPrice/existing setters preserve DECIMAL(18,2) strings/null/zero; reject negative, float, exponent, excess scale/precision, locale/control input. USD/SYP independent, no exchange conversion. Exact before/after decimal strings audited.

## 17. Product visibility

Active unarchived Product AND active unarchived Brand only. Hidden/archived/recommended distinctions preserved. Product restoration under archived Brand stays non-public; Brand is never automatically restored or child statuses changed.

## 18. Availability behavior

Existing available/on_request/unavailable only; Product and Variant independent, existing unavailable-then-on_request precedence unchanged. Descriptive availability is not inventory or reservation.

## 19. Concern associations

Atomic Product-first locks then ordered Concern validation/sync; unique pivot unchanged, prior/new IDs audited. Owner filter by retained Concern ID matches existing pivot semantics; public archived Concern visibility still excluded.

## 20. Product image management

Real GD decoded/re-encoded PNG storage from validated JPEG/PNG/WebP/GIF, immutable generated paths. Gallery20/page/max20current images, reorder/primary/archive/restore. No external URLs, binary SQL, CDN, arbitrary path editor or real media testing.

## 21. Brand logo management

Same validation, generated public-disk path. Old logo retained through replacement/failure; no in-place overwrite or garbage collector. Stale version compensates only fresh new file.

## 22. Primary-image integrity

Original generated unique primary_product_id unchanged. Product lock coordinates at most target/current-primary metadata (bounded2). Atomic reassignment, deterministic existing fallback, no image=null. Archive clears explicit flag, restore secondary; no displacement of another primary.

## 23. Soft Delete entity matrix

Product/Brand/Concern/Variant/Image receive deleted_at plus version/index. No Customer/Cart/Favorite/Checkout/identity/audit SoftDeletes. No HTTP hard-delete/purge or cascade. Full matrix in [database document](../database/catalog-soft-delete-and-audit.md).

## 24. Restoration safeguards

Same UUID/status/slugs/pivots/references, no duplicate replacement. Existing Brand required for Product; child requires current Product. All-physical Variant P1 and safe existing image bytes checked. Current-version satisfied repeats no-op without false audit; stale repeat409.

## 25. Recycle Bin

Five typed trash-only resources, search/filter/pagination25, date/name/UUID/original parent and selected/all-matching restore preview. Separate from normal search/lists/counts; no permanent deletion.

## 26. Bulk selection semantics

Individual/page IDs versus explicit all-filtered across pages, live exact selection count/clear/reset, no browser storage. Server stores one bounded expiring preview, validates scope/resource/filter/IDs, renders exact type/count/action/targets/impact before consent. Clear resets matching scope too.

## 27. Bulk operations

Cap100 synchronous records. Typed archive/restore; Product/Brand/Concern active/hidden; Product availability/recommendation. Atomic complete-set validation, deterministic locks/versions, re-resolved matching set; changed/oversized/missing targets reject entirely. No silent partial success or background/unlimited processing.

## 28. Audit history

Append-only catalog_audit_events: event/Owner/target/action/time/request/bulk UUIDs and explicit concise before/after JSON. Audit and business SQL commit/rollback together. No credential/customer PII or financial transaction inference, no audit editing/purge route. Trusted DBA/raw SQL remains outside application enforcement.

## 29. Authentication/authorization

Existing exact active Owner/role/idle/credential version/HTTPS/no-store/CSRF; all9new route definitions protected. Guest/Customer and disabled/restricted/idle/revoked identities denied. FormRequest repeats active Owner check; no conventional admin login/default account/staff scope.

## 30. Customer/API compatibility

Public Eloquent SoftDelete scopes exclude archived entities and Brand, with current Concern/image/Variant projections. Existing response envelopes/fields unchanged; no public mutations/audit exposure. Management raw readers explicitly exclude deleted_at, with separate archived counters and recycle queries. Existing request search/contact boundary retained.

## 31. Favorites compatibility

Actual relationship rows survive archive; list/membership uses existing visibility boundary. Restore/reactivate can show still-valid relationships. GET never cleans rows; existing removal semantics unchanged.

## 32. Cart compatibility

Retain existing IDs/quantities, block new inaccessible/archived selections; actual decrease/removal demonstrated while blocked. Structural selection count includes all physical Variants, selected lookup excludes archived ones. Current-price/USD/SYP/zero/null contracts unchanged.

## 33. Preliminary Checkout compatibility

Immutable contact/selection/fingerprint rows compared before/after archival; idempotent replay returns same request even after Cart removal. New submission locks/revalidates committed Catalog state; independent blocked submission tests cover Product/Variant/Brand invalidation. No confirmed Order/payment/stock effect.

## 34. Transaction and lock order

Products sorted first, children then Brands/Concerns sorted; bulk prelocks entire target set and referenced Brands independently in ID order. Brand-only writes never lock Products afterwards. No Customer/Cart locks in admin. Short transactions/retries3; image decoding/storage outside SQL lock period. Explicit versions prevent lost edits independent of retries.

## 35. Actual concurrency evidence

CONFIRMED:15focused independent-process scenarios: competing Owner edits, parent-price/first-Variant, Product archive/Cart add, Variant archive/submission, Brand hide/selection, primary contention, overlapping bulk, Variant restore/parent-price, Product restore/Brand archive, Variant archive/Cart increase, Product archive/submission, Brand hide/submission, simultaneous slugs, archive/restore, Variant-price/parent-price. Held row or insert-gap barrier, independent connection IDs and actual blocked SQL observed through processlist before commit; final state/audit assertions. Final local worker instrumentation records one outer transaction attempt per worker across all15cases; no retries/deadlocks observed in these controlled overlaps. No sequential test is called a race. Earlier9case run79assertions; expanded outcomes and final exact counts are recorded below after verification.

## 36. Query counts

CONFIRMED13 HTTP paths at50/200 Products with10Variants/20Images/Brands/Concerns and real stored Cart/request references: list6, edit10, detail6, image listing10, Brands5, Concerns5, recycle4, search7, overview9, create7, update9, Variant10. Bulk7 after explicit referenced-Brand prelock; all13counts exactly equal at50/200 in final rerun. Constant query count is not constant cost; bulk scales linearly only inside cap100. No throughput/latency claim.

## 37. Index usage

Local EXPLAIN uses products_deleted_at_id_index covering lookup for current Product ID paging. Existing status/date index and literal name/global date scan limits retained; new soft-delete scope can alter optimizer choice. CI8.4 plan evidence must be checked independently. No wildcard/range indexing claim without measured support.

## 38. Image storage consistency

Max5MiB input,4096per dimension/4,194,304pixels,20current images,8MiB named Catalog body. GD unavailable503. Decode/re-encode strips metadata/animation; new path saved before short SQL. Failure compensates generated path; old bytes never removed/overwritten. Crash/orphan/failed-cleanup risks explicitly remain; no distributed SQL/filesystem atomicity or cleanup worker.

## 39. Image security tests

Actual fake-disk raster upload/PNG signature, invalid/SVG/PHP/double/path/corrupt/mismatched/executable-tail, byte/dimension/count caps including restoration, cross-parent image edit, primary uniqueness/fallback/archive/restore/missing file, stale compensation, real MySQL audit FK failure after file save and old-logo preservation. No tests touch real public media.

## 40. Browser/responsive tests

CONFIRMED real Playwright CLI browser against positively guarded physical sumaya_pharma_test, isolated media root and actual CSRF. Created Product with two exact prices and Concern, explicitly cleared both prices, created priced Variant, uploaded/decoded320x240 PNG, changed primary/order4, archived/confirmed/restored same Product; created Brand/Concern and uploaded/decoded logo. Selected25page versus29matching across2pages, cleared scope/count then confirmed29-record hide. SQL proof: parent prices null, Variant9.50/null, primary image/order4,1archive+1restore+29bulk audit events,8requests/1Cart retained. Product/Brand/Concern/edit/list/recycle measured375/768/1440 with document scrollWidth exactly viewport; screenshots output/playwright/phase6c-product-375.png, gallery-768.png, confirm-1440.png and brand-375.png visually reviewed. Artifacts are local ignored fixtures, not production data.

## 41. Accessibility findings

CONFIRMED labelled RTL forms, LTR currencies/IDs, responsive grids, explicit count/scope feedback and horizontally contained tables. Space toggled focused native consent before actual confirmation; mobile menu opened and Escape closed (aria-expanded=false). Browser console0errors/0warnings. Corrected obsolete read-only/future-management copy and technical-ID selectors found during browser review; new named directories preserve associations. Native zoom/full screen-reader/complete contrast audit remain NEEDS VERIFICATION.

## 42. PHPUnit test count

CONFIRMED final local full run453passed (221.86s), baseline394 retained plus59new cases. Earlier451run exposed3legitimate historical hard-delete expectation conflicts; no success claimed for that run. Explicit forceDelete retains exact MySQL1451 protection. Last image-restoration cap hardening is separately rechecked in the media suite, then full exact-head CI.

## 43. PHPUnit assertion count

CONFIRMED local full3689assertions; a final5assertion image-restoration cap regression follows that run. Historical baseline2970 is not a new Phase6C result. Allowed historical race outcomes can cause small assertion-count variation; source/final CI counts must be reported distinctly if different.

## 44. Historical regression results

No tests deleted, rules weakened or baseline suppressions. Two identity route/cache inventories extend17to26; metrics comparison adds three archived-zero fields while preserving prior values; three hard-delete calls now explicitly forceDelete and still demand real FK1451. Prior Catalog/Customer/Cart/Favorites/Checkout/Owner cases retained. PAY001/original migration/Composer/NPM lock/auth config/.env source untouched.

## 45. Pint

CONFIRMED Pint --test passed after formatting new worker/tests; must remain green at final verification. No formatting-only shared Customer source edits.

## 46. PHPStan

CONFIRMED level5 existing scope/512M passes. Fixed actual model union types, exhaustive action handling, nullable image target and redundant comparison; no ignore/baseline/rule reduction.

## 47. Composer checks

CONFIRMED validate --strict/check-platform-reqs/audit pass, no advisories or manifest/lock changes. Existing runtime PHP8.4.26/GD present. GD is explicitly enabled in CI and documented rollout requirement; no new package installed.

## 48. NPM/Vite build

CONFIRMED locked npm ci --offline --no-audit --no-fund,87existing packages, Vite7.3.7/59modules production build. Current measured CSS52.48kB/gzip11.69, JS54.45kB/gzip20.59. Local npm11 esbuild allowScripts advisory nonblocking; no dependency/lock/CDN change. Final rebuild completed after named-selector and default-order UI changes.

## 49. Database migrations

One additive Catalog archival/version/index/audit migration, tested only on positively guarded physical sumaya_pharma_test. Original migration files unchanged. No development/production migration, reset, seed, schema change or automatic rollout.

## 50. MySQL 8.4/9.7 compatibility

CONFIRMED local9.7.0; original generated-image unique/CHECK/FK tests retained alongside new archival/audit schema. CI8.4 result remains pending exact-head verification; do not substitute local success. Generated expression/index unchanged.

## 51. Development database preservation

CONFIRMED before: actual local sumaya_pharma, MySQL9.7.0/REPEATABLE-READ,11tables,1Customer/3migration rows/other9empty. Read-only sorted full-row/schema/.env hashes captured; metadata SHA256 `291dd66b3adc84a507a82565465c0065ad987af39d1852a0d9ce476750d7e75d`. CONFIRMED deep after-comparison after browser work: every sorted row/schema/.env hash matches exactly; after metadata SHA256 is identical. Two Checkout, one identity and new Catalog migrations remain unapplied to development.

## 52. No real Owner provisioning

CONFIRMED no real Owner provisioned or real account/Google/.env modification. All synthetic Owners created only in physically guarded test database by tests/CLI browser helper. No public setup endpoint.

## 53. CI URL and final status

NEEDS VERIFICATION until source PR CI and final documentation-head CI are published and checked. Exact final SHA/run/job/test counts are recorded in PR description and final delivery after independent check. Source success must not substitute final-head proof; PR workflow tests GitHub temporary merge ref without merging PR.

## 54. Files created

Catalog administration registry/context/write/archive/media/selection/bulk services; Owner controller/request/body limiter; additive migration; five Owner Catalog Blade views; six meaningful test classes and independent worker; design gate/architecture/database/security/web/ADR022/this60section report. Ignored local helpers/logs/screenshots/media are not shipped.

## 55. Files modified

Five Catalog models/selection reader; raw dashboard readers/metrics; Owner private route registration/error handling; existing list/detail/overview/native selection; CI GD extension; five historical test files for exact route/metrics/FK semantics; relevant documentation indexes. Final diff audit confirms authorized scope; no unrelated module rewrite.

## 56. Dependencies changed

No Composer/NPM package/manifest/lock changes. GD runtime extension already installed locally and added to CI PHP extension list for secure raster re-encoding. No Redis/queue/search/admin/Livewire/Alpine dependency.

## 57. Known limitations

POTENTIAL: synchronous bulk linear within100, offset pages capped1000, names contained scans, aggregate snapshots not cross-domain atomic, selector directories50, association max100, current media20. Physical/archived Variants intentionally prevent direct-price conversion. Archived parent prevents child operations until parent restored. Retained old files may accumulate; crash can orphan files, no cleanup worker. GD format support/animation flattening documented. No native zoom/full assistive-tech/production capacity claim.

## 58. Deferred business decisions

Reparenting, referenced Variant-to-direct transition, archived slug reuse, permanent purge, destructive media retention/garbage collection, automatic selection cleanup/publishing, financial/history/stock/payment/fulfillment decisions excluded. No unresolved decision blocks conservative implemented operations; new material ambiguity must be separately approved rather than invented.

## 59. Operational rollout prerequisites

Explicit authorized schema rollout/backup/physical target verification for pending migrations; real Owner provision separate; secure actual prefix/TLS/proxy/shared session/cache/logging, GD decoder support, PHP/proxy post/upload limits, public disk permissions/safe nonexecutable serving/storage symlink and operational orphan reconciliation. None applied to development/production here. Deploy source and schema coherently after Owner approval; no deployment command executed.

## 60. Owner review checklist

Require final exact-head successful CI/open unmerged PR, actual Arabic form/image/archive/bulk/restore browser evidence, immutable Customer/history snapshots, race barriers/outcomes, typed cap100 confirmations, decimal/physical-Variant ownership, media compensation limits and eleven-table preservation proof. Review remaining production/accessibility limits separately. No merge, development migration, real provisioning or Phase6D authorized by delivery.
