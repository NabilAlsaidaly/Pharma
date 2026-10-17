# Phase6G — Educational Content implementation report

Evidence: **CONFIRMED** directly observed; **POTENTIAL** reasoned risk; **NEEDS VERIFICATION** incomplete gate. Source presence is not final delivery. No merge/deployment/development or production migration.

## 1. الملخص التنفيذي

**CONFIRMED:** أُضيفت شريحة المقالات التعليمية داخل Content مع فئات ديناميكية ومحرر كتل عربي آمن وغلاف اختياري وخمس صور داخلية، وإصدارات غير قابلة للتعديل ونشر صريح وعلاقات تحريرية وقراءات عامة آمنة. اعتمد المستخدم A–N والتعديلين C/D رسمياً قبل البرمجة التابعة لهما. لا توليد نصائح أو اعتماد سريري آلي. الاختبارات الشاملة وCI النهائي تبقى بوابات مستقلة موثقة أدناه، ولا يُعلن التسليم قبلها.

## 2. Baseline main SHA

**CONFIRMED:** fetched origin/main `a78f4149e1a68342e318405f8d25c4347a4cd29b`, matching supplied reference; PR10/6F merged at that SHA. [Main CI38976295022](https://github.com/NabilAlsaidaly/Pharma/actions/runs/38976295022) completed/success. Local baseline focused Content/media/Catalog28tests/335assertions,41.638s,64MB,exit0/JUnit clean; not a new full baseline run. Historical6F exact-head CI685/6247 is separately recorded, not relabeled6G.

## 3. Feature branch

**CONFIRMED:** `feature/phase-06g-educational-content` created from verified origin/main. Previous local6F branch had identical tracked source; discrepancy reported before modifications. No reset/clean/force push. Original untracked LSP file preserved/excluded.

## 4. Final source HEAD

**NEEDS VERIFICATION:** exact source SHA will be recorded after source commit; final report-only documentation head and independent workflow are recorded externally in PR/final delivery to avoid recursive self-referential commits. Prior source CI alone does not establish final-head success.

## 5. PR URL

**NEEDS VERIFICATION:** one PR will target verified main after required local checks, remain OPEN/UNMERGED. No PR merge authorization.

## 6. Approved business gate

**CONFIRMED:** [gate](phase-06g-educational-content-design-gate.md) sections8–10 record partial approval then explicit final approval: «أعتمد رسميًا التعديلين C وD كما وردا بالتفصيل في القسم التاسع» and confirmation of A/B/E–N. Structured blocks, optional cover+5inline, immutable typed JSON presentation and relational media membership approved. No dependent schema/behavior before final approval.

## 7. Implemented features

**CONFIRMED:** first-class Article/Category commands/revisions/audit; safe structured editor, text-selection marks/links, block/list management, sources and reviewed publication, private GD media, native Arabic Owner forms, exact public list/detail/category/media APIs, current Catalog visibility suppression, bounded search/featured/filter/discovery/history. Detailed [domain](../architecture/educational-content-domain.md), [public contract](../api/educational-public-api.md), [Owner routes](../api/owner-educational-content-web.md).

## 8. Deferred features

**CONFIRMED:** no Next.js, consolidated final frontend handoff,6H, CMS/WYSIWYG dependency, Markdown/rawHTML, nested lists/tables/colors/embeds, scheduled/automatic publication, comments/ratings/medical profiles, AI advice, clinical verification, invented citations/credentials/disclaimer, payments/stock/shipping/orders/messages or real Owner provisioning.

## 9. Domain architecture

**CONFIRMED:** [ADR026](../adr/ADR-026-educational-content-management.md). Content owns educational Application/Domain/Infrastructure/Interfaces slice; focused commands/readers, no Advice microservice/universal CMS. Shared BusinessRaster preparation reused technically. ManagementIdentity owns access, ManagementDashboard shell only, Catalog authoritative read integration. Gifts/Orders/Settings/Payments/Delivery/System Console business mutations absent.

## 10. Existing Content compatibility

**CONFIRMED:** ContentTypes/ManageStoreContent/RevisionedStore and6E published infrastructure untouched. No duplicate banners/pages/FAQs/policies, old route/body/publication contracts retained. Additive navigation/routing/scoped body and safe error adapters only. Full historical regression passed in the current local suite below.

## 11. Article aggregate

**CONFIRMED:** UUIDv7,permanent slug80,latest positive unsigned version,current same-Article publication pointer,archive/time. New save needs title/slug, body/scope can be incomplete draft. Fixed metadata and relational children copied into immutable attributed revision on every successful action. No hard deletion endpoint/slug reassignment or generic model-binding authority.

## 12. Category design

**CONFIRMED:** Owner-managed UUID/permanent slug/name160/description500/active/order/archive/version, own immutable revisions;0..1optional Article category. Hide/archive suppresses category metadata/scoped route but keeps Article. Restore hidden. Published label snapshot changes on Article republication; directory uses current visible labels. No defaults/hierarchy/multi-category.

## 13. Article body format

**CONFIRMED:** schema1 typed JSON paragraph/heading2or3/listordered-or-unordered/image, strict text/bold/italic/HTTPS runs.100blocks,50items/list,20runs/region,40000text chars including captions,192KiBcanonicalJSON. caption500,alt250,href2048. No arbitrary attributes/HTML/JS/Markdown; preserve Arabic/Unicode/paragraphs. Adjacent identical marks merge without inserting text. Future renderer maps fixed components; API resolves image descriptors, no business logic in Next.js. See [publication](../architecture/article-publication-and-revisions.md).

## 14. Publication/versioning

**CONFIRMED:** save/media/publish/unpublish/archive/restore revisions and SQL audit atomic. Explicit consent; publish needs readable text, scope, review and health requirements, valid/current owned image placements/files. Draft editing leaves current publication intact, including all children/featured/metadata. Unpublish/archive clears pointer; restore unpublished. MAX readable, cannot advance; stale409. No scheduled/autosave publication.

## 15. Product associations

**CONFIRMED:**0..20manual Product references/order, duplicate reject, new Product+Brand visible/unarchived. No Variant/unit price/stock/purchase suitability requirement; unavailable remains editorially referenceable. Original Catalog models and write services unchanged. Related public projection id/name/slug/declared availability only.

## 16. Concern associations

**CONFIRMED:**0..20optional explicit existing Catalog Concerns/order; active/unarchived for newly linked selection. Revision-specific FK/unique/order/reverse indexes, no symptom inference or parallel taxonomy. Concern filter resolves visible entity and published membership only.

## 17. Hidden Catalog behavior

**CONFIRMED:** previously linked hidden/archived Product/Brand/Concern can remain privately and in republication, but public related fields/filter never reveal them. Article stays public. Restored visibility restores saved membership. Current visible names/slugs may change with Catalog; Article text/membership remain saved. Coherent detail snapshot, no hidden fallback text/image/price. [Association design](../architecture/article-catalog-associations.md).

## 18. Health/editorial safeguards

**CONFIRMED:** Owner chooses general/health_related explicitly before publication; review acknowledgment each publication, health needs≥1Owner-entered source and additional acknowledgment. No medically_approved/doctor_verified default, professional identity or invented public disclaimer. UI warns software does not verify medical accuracy. Sources syntax/duplicates/HTTPS validated without fetching or credibility certification. Tests prove workflow controls, not the medical truth of claims.

## 19. Image handling

**CONFIRMED:** cover0..1+inline0..5current images, alt required, caption optional; generated immutable private education/{uuid}.png, GD actual extension/MIME/decode/re-encode/metadata stripping, source5MiB,4096/dimension,4194304pixels,output8MiB. No SVG/remote fetch/Catalog image copying/client path. One upload/command, existing image UUIDs current-draft-only, inline used once at publication. Historical image count is not the current6-image cap.

## 20. Media authorization

**CONFIRMED:** composite Article/revision/media ownership, private current Owner preview of retained history, public exact current publication membership. Known draft404/private200; publish200; removed old current image404 after republication; unpublish/archive/restore-unpublished404; Guest private302login. Fresh file compensated after actual SQL insert failure, replay no duplicate file; old files retained. **POTENTIAL:** crash orphan and already-admitted bytes cannot be recalled.

## 21. Database schema

**CONFIRMED:** [eleven tables](../database/educational-content-schema.md), additive typed Article/Category+revisions, relational Product/Concern/source/media membership, immutable file identities/audit and per-Owner coordination. Restrictive/composite FKs, same-Article published pointer, unique slugs/versions/operation/relations/slots, SQL CHECKs for coarse document shape/versions/order/dimensions/slot role. Full JSON semantics/membership remain atomic command validation, not a claimed direct JSON FK or defense against every DBA edit.

## 22. Migration inventory

**CONFIRMED:** new `2026_10_17_000002_create_educational_content.php`; no historical migration edited. Development remains3applied of11files,8pending including6G; no development/production migration. Guarded independent-worker schema setup/down validates additive migration and dependency rollback locally; CI8.4 remains separately verified below. Down destroys educational history, not an automatic recovery procedure.

## 23. Owner routes

**CONFIRMED:** ten additive definitions53→63, three navigation destinations12→15 under existing configurable private prefix. Article list/create/edit/post,Category list/create/edit/post,private media,audit. Guest/Customer and all revocation states denied; no/admin alias or second admin application. Native labels/forms, bounded directories with retained selections and no manual UUID/JSON editing. [Inventory](../api/owner-educational-content-web.md).

## 24. Public API inventory

**CONFIRMED:** five GETs `/api/v1/education/articles`,articles/{slug},categories,categories/{slug}/articles,media/{uuid}.png. No public write/Customer endpoint. Existing storefront/Catalog/Gifts routes unchanged. Explicit safe summary/detail/category/image projections, pagination,unknown-key and body validation,no-store/errors/request IDs.

## 25. API examples

**CONFIRMED:** [full contract and synthetic JSON example](../api/educational-public-api.md), published revision string,body_formatstructured_blocks,schema_version1,runs/fixedblocks,server-resolved image descriptors,nullablecover/category/SEO. List omitsbody/internalimages/sources; detailrelatedProducts/Concerns live-visible only. Examples are not seeded advice/sources. GETmissing/unpublished404,missingSchema503,invalidfilters422,wrongmethod405.

## 26. Authentication/authorization

**CONFIRMED:** current Owner session/role/status/credential/idle middleware and persisted command revalidation; no request-supplied Owner ID, Customer role or UUID knowledge authority. Shared Owner lock prevents concurrent revocation during admitted commands; coordination row exclusively serializes educational writes. Public educational reads do not add Customer authentication or change Google/Sanctum behavior.

## 27. CSRF protections

**CONFIRMED:** actual native PreventRequestForgery enforced in HTTP tests and guarded synthetic browser server, not only testing-mode bypass. Both new POST families reject missing CSRF419. Browser form operations use normal tokens. No new origin/CSRF/bearer exemption. Strict body/attempt caps and private error/no sensitive flash retained.

## 28. Idempotency

**CONFIRMED:** permanent SQL uniqueOwner+operation across this slice,canonical fingerprint includes target/action/version/typed normalized values/children/acknowledgments/media digest. Identical committed retry returns original target before stale checks after current Owner validation; changed reuse409. Same operation cross-target serialized. No cache/browser authority; SQL/file compensation and no duplicate revision/event/file assertions.

## 29. Optimistic concurrency

**CONFIRMED:** exact unsigned decimal string expected version,UUID4/7operation; firstidentityexpected0. Concurrent edits one full revision/one409, no lost update/mixed body/image/membership. UnsignedMAX read supported; MAXadvance/overMAX/noncanonicalnumbers rejected. Browser stale tab409 preserves current publication. No float/JSnumber version comparison.

## 30. Lock graph

**CONFIRMED:** OwnerS→educational_owner_locksX atomicupsert→lockingreplay→ArticleX→CategoryX→ProductsXsorted→BrandsXsorted→ConcernsXsorted→revisionchildrenpointeraudit. Category stops atCategory; noCustomer/Cart/Gift locks. The initial OwnerX sketch exposed actual FK-induced deadlock (InnoDB:OwnerX→ConcernX versusConcernX→OwnerS fromCatalogAudit FK). Separate coordination row removes that observed edge while preserving credential lock/serialization. INSERTIGNORE avoided because duplicate S→X upgrade can deadlock. No legacy Catalog mutation/refactor; retry3 still protects transient conflicts, no universal deadlock-free promise.

## 31. Audit history

**CONFIRMED:** Owner/target/action/previous+newversion/time/requestID/operationfingerprint/changedfieldnames. No full Article body/rawrequest/Catalog secrets/Customer contact. Full editorial text stays in immutable revisions; category revision history privately inspectable. Field-name JSON narrowly scoped. Retention/DBA append-only enforcement beyond application commands remains operational policy.

## 32. Real MySQL races

**CONFIRMED:** the complete focused run included eleven independent-process races on physical connections152–193, all observed attempts1:78tests/619assertions,2:14.950,68MB,exit0 and clean JUnit. The final local full run additionally completed all twelve educational races on connections1161–1206, all observed attempts1, including first-time coordination-row creation. Other scenarios: competing edits, draft/publication, publication/unpublication, archive/publication, Category visibility, Product visibility, Concern visibility, media/publication, identical retry, conflicting operation reuse, and Article/Category management. Actual SHOW FULL PROCESSLIST barriers proved blocked locking SQL on distinct connections. The initial two retrying deadlocks were diagnosed and corrected, not hidden. Cleanup verifies resolved temporary roots before deletion; sequential operations are not labeled races.

## 33. Query count results

**CONFIRMED:** empty public list4 queries. At10/50/200 Articles all thirteen paths retained identical counts: list5, category-scoped list6, detail7, featured5, search5, Concern filter6, categories3, Owner list4, Owner edit21, audit4, media2, publication23 and unpublication23. Fixtures include multiple categories, visible/hidden links, a cover and growing revisions/sources/audit. Counts measure HTTP execution only; fixture version lookups happen outside measurement. No full body/history hydration in summaries or per-Article image filesystem probes. Constant query count is not a production throughput claim.

## 34. EXPLAIN evidence

**CONFIRMED:** ten plans captured again in the final local full suite, including separate public page/COUNT, search page/COUNT, Concern page/COUNT, category COUNT, history, media and category directory. Initial search scanned247 revision-history rows. A fixed server-owned STRAIGHT_JOIN now starts with Article pointers and probes each exact published revision; assertions reject unrelated revision table/index scans. History uses a covering reverse lookup, media an index, categories a covering listing index. Sorting current Articles may filesort; optimizer row estimates are not exact fixture counts or capacity evidence. **NEEDS VERIFICATION:** CI MySQL8.4 plans.

## 35. Browser test results

**CONFIRMED:** real Playwright CLI/native-CSRF synthetic Owner on guarded physical sumaya_pharma_test, temporary127.0.0.1:8792 server. Flows covered Category creation; Article metadata/relations/health scope; paragraph marks/HTTPS/h3/bullet and numbered lists/reorder/removal/sources; private cover/internal images; publication5→draft6→publication7; stale409; script422 without execution; five-image publication14, sixth-image422, draft removal retaining the old publication until16 then old image404; Category hide/archive/restore hidden; Article unpublish→republish→archive→restore unpublished at version20. Private history and acknowledgments were inspected. Dirty-navigation warning, keyboard menu Enter/Escape/focus return, publication consent/dialog/Escape focus and native confirmation fallback were exercised. An actual early-click startup race was reproduced and fixed by disabling add controls until initialization; a700ms delayed-module probe then verified disabled→enabled. Completed scoped scenarios had empty pageerror arrays; deliberate404/409/422 resource errors are not called a globally clean console. All44 original fixture table hashes matched, with1Article/25educational events; Owner operational login changes were explicitly excluded. Both sessions and server stopped before full regression.

## 36. Responsive RTL evidence

**CONFIRMED:**33 returned width checks:11states×375/768/1440, document width never exceeding the settled viewport. States: saved hybrid editor, publication, draft over existing publication, stale409, validation422, multi-image publication, final restored Article, audit, filtered draft list, Category list, hidden Category. Additional lifecycle checks ran but are excluded from this total because a transient beforeunload dialog interrupted CLI result collection; final version20 and hidden Category were independently verified. Five ignored screenshots reside under output/playwright; editor1440, confirmation375, preview375 and validation375 were visually inspected. An initial immediate-resize false positive was corrected by waiting for actual viewport/layout settlement, not by relaxing CSS or assertions. No full zoom/assistive-technology audit is claimed.

## 37. Accessibility limitations

**CONFIRMED:** semantic native controls, labels and fieldsets; keyboard text selection; block/list up/down and numeric order; menu/confirmation Escape and focus return; dirty-navigation warning. **NEEDS VERIFICATION:** comprehensive screen-reader, native zoom, contrast and WCAG audit. New block authoring requires JavaScript with a visible noscript notice; existing metadata/body can still be preserved and saved without JS. No WCAG certification.

## 38. Medical-content verification limitations

**CONFIRMED:** software does not determine clinical truth, scientific credibility or whether an Owner misclassifies health content as general. Classification, acknowledgment and source requirements are workflow guards. No AI advice, diagnosis, dosage, professional qualification or public legal disclaimer is automatically added. Product relationships are not clinical recommendations. Humans must review actual content; technical tests are not medical approval.

## 39. Security tests

**CONFIRMED:** coverage includes all new Owner destinations for Guests/Customers and four revocation states; native CSRF/body/attempt/allowlist/no-flash checks; structured XSS/URL/schema/control/byte/count validation; real SQL FK/CHECK/unique and cross-Article media constraints; media replay/compensation; sanitized public500/schema503; read requests without writes. ArticleInput unit tests passed38tests/42assertions in0.561s/44MB, then passed again with all educational classes and the twelfth race in the complete current suite. Partial runs remain separately labeled.

## 40. Historical regression

**CONFIRMED:** the full run passed all685 historical tests and117 new cases. Only four historical route/navigation inventory expectations were updated53→63 and12→15; assertions were not removed. Protected business commands, historical migrations, manifests/locks and CI configuration have no staged diff. Google/Sanctum, Catalog P1/C2/V1/S1, Favorites, Cart, Checkout,6D/6E/6F and System Console regression passed.

## 41. PHPUnit test count

**CONFIRMED:** full current suite passed **802 tests,6964 assertions**, exit0, **17:11.848**, **98MB**, on PHP8.4.26/MySQL9.7.0. Independently parsed JUnit reports802tests/6964assertions, zero errors/failures/skips,1031.678456 summed test seconds. Ignored artifacts: storage/framework/testing/phase6g-full.log and phase6g-full-junit.xml. The earlier78-case focused run and38-case input run are separate evidence. Exploratory failed runs are not called successful. The initial SQL-failure fixture used implicit-commit trigger DDL and broke its test savepoint; the corrected probe uses an actually invalid DML insert and verifies rollback/file compensation without DDL.

## 42. Assertion count

**CONFIRMED:** local complete suite and independently parsed JUnit both report **6964 assertions**. **NEEDS VERIFICATION:** independently observe source-CI and final-documentation-head totals; do not infer them from local evidence.

## 43. Pint result

**CONFIRMED:** full current Pint --test passed after formatting. No ignore/config changes. Recheck only if PHP source changes before freeze.

## 44. PHPStan result

**CONFIRMED:** existing PHPStan level5/512MB passed. No baseline, suppression or configuration weakening. Corrected real null/boolean checks, persisted Concern status access and an unused closure capture; no Catalog model refactor.

## 45. Composer checks

**CONFIRMED:** Composer validate --strict, platform checks on PHP8.4.26 and audit all passed; audit reports no security vulnerability advisories. Manifests and lockfiles unchanged.

## 46. Vite result

**CONFIRMED:** locked offline npm ci installed87 existing packages. Vite7.3.7/61modules passed:CSS54.71kB/gzip12.12,JS65.07kB/gzip24.37. The existing esbuild install-script advisory is recorded; build succeeded without approving new package scripts or changing configuration. Sandbox EPERM required normal approved escalation, not dependency changes.

## 47. Dependency changes

**CONFIRMED:** no dependency changes. Existing Laravel/GD/Brick Math/Blade/Vite/JavaScript reused. No CMS/editor/parser/queue/search/Redis service. Composer/npm manifests and locks unchanged; temporary Playwright CLI remains outside the application dependency graph. Helpers, screenshots, logs and JUnit artifacts are ignored.

## 48. Development database preservation

**CONFIRMED:** read-only full sorted rows/DDL/.env before/after focused tests, browser and the complete802-test suite MATCH: physical sumaya_pharma,MySQL9.7.0/RR,11tables,1Customer,3applied migrations; metadata SHA256`291dd66b3adc84a507a82565465c0065ad987af39d1852a0d9ce476750d7e75d`. Read-only migration inventory confirms8pending, including6G. Other business rows remain empty; test connection independently verified testing/sumaya_pharma_test. Real storage/app contains only its three existing .gitignore files. Original LSP SHA256`8D0F5B931FE0A63F90D8D515C77C4219BCA53B08DFC9FF747EF17F5D264FE97F` retained and excluded. Schema readiness and browser inventories explicitly scope the physical/configured schema; unrelated-schema names are not accepted as readiness evidence.

## 49. Production changes status

**CONFIRMED:** no production changes, deployment, environment secret changes, production connection/migration or PR merge. No actual published advice or medical claim seeded.

## 50. Real Owner provisioning status

**CONFIRMED:** no real Owner provisioning. Only guarded synthetic PHPUnit/worker/browser Owners were created. No real account/password generated or logged. Preview bootstrap is not shipped in application routes/source; temporary fixture credentials are not live credentials.

## 51. Source/schema rollout prerequisites

**CONFIRMED:** [separate authorized rollout](../deployment/educational-content-rollout.md) requires backup/physical target, outstanding migrations in order, coordinated source/schema, real Owner separately, TLS/cookies/CORS/private prefix/GD/private bytes/proxy limits, shared sessions/limiter/storage for multiple nodes, retention and orphan recovery. Missing development schema remains intentionally unavailable; source alone is not live activation. Down destroys new history.

## 52. Remaining technical risks

**POTENTIAL:** per-Owner serialization, storage/write growth from revision copying, crash-orphan files, bounded selectors/offset pagination/literal search at larger scale, current-Article filesort, external writer lock graphs, raw DBA changes outside append-only commands and already admitted responses preceding visibility changes. No RPS/capacity/screen-reader/medical/legal certification claim. Operational retention and multiple-node infrastructure require separate verification.

## 53. Deferred delivery/payment/Orders behavior

**CONFIRMED:** PAY001 unchanged. Articles do not mutate Cart/Checkout/Gifts/Orders/stock/payment/delivery/notifications. Product references are editorial, not purchase eligibility or medical suitability. No confirmed Order or commercial execution activated.

## 54. Deferred Next.js handoff

**CONFIRMED:** no Next.js implementation or final consolidated frontend handoff. The focused structured Article API/rendering contract is documented for future fixed components. Laravel owns all business decisions; no invented frontend routes, prices, publication logic or clinical inference.

## 55. GitHub Actions URL/conclusion

**NEEDS VERIFICATION:** successful source CI, then report-only evidence commit, then independently verified workflow/jobs/logs for the actual final PR head against verified main. Final SHA/run/job/result recorded externally without a recursive evidence commit. Prior phase/main/earlier-source success alone never satisfies the final gate.

## 56. Merge-readiness assessment

**CONFIRMED:** approved scope implemented; complete local802/6964 regression, focused/browser/tooling/preservation evidence collected. Protected staged diff, whitespace checks and12-document relative-link inventory passed, zero broken links. Main was fetched again and remains the baseline a78f4149e1a68342e318405f8d25c4347a4cd29b. **NEEDS VERIFICATION:** one PR and exact source/final-head CI. No further business approval is required for approved A–N. Leave PR open for Owner review; no merge, deployment, development/production migration, real Owner,6H or Next.js.
