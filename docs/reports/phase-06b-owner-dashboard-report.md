# Phase 6B — Custom Owner dashboard report

Date: 2026-10-14 (owner/client context). **CONFIRMED** = observed evidence; **POTENTIAL** = reasoned risk; **NEEDS VERIFICATION** = not demonstrated. No merge, deployment, development migration or real Owner provisioning is performed.

## Arabic executive summary

**CONFIRMED:** أُضيفت لوحة عربية مخصصة داخل Laravel لعرض المعلومات فقط: تنقل متجاوب، إحصاءات فعلية، مخطط يومي، بحث موحد، وقوائم وتفاصيل للمنتجات والعلامات والاهتمامات والطلبات المبدئية. بقيت حماية المالكة منفصلة عن العملاء، وبيانات التواصل محصورة في تفاصيل الطلب المحمية. لا توجد أرقام زيارات مصطنعة أو إجراءات تعديل أو حذف أو اعتماد أو مدفوعات. بصمات قاعدة التطوير وملف البيئة متطابقة قبل العمل وبعده. التسليم للمراجعة عبر فرع مستقل وطلب غير مدمج، مع التحقق من CI على الالتزام الأخير قبل التسليم.

## 1. Phase objective

Implement the approved phase6b.md read-side management presentation scope, not business administration. Reuse Laravel13/Blade/Tailwind4/Vite; retain Customer Next.js separation and System Console independence. Search/statistics are database-backed, not rendered-row filtering or sample counters.

## 2. Starting commit

**CONFIRMED:** fetched remote main and fast-forwarded from clean worktree to `5bf935e2362084c8aef668d0491c1503b826f738`. GitHub PR5 rechecked CLOSED/MERGED, same merge SHA and feature head863691886de4d0da7543f836e89f8ef01ce5f37b. Revalidated baseline351tests/2367assertions locally before wiring changes. ADR021 was the next available number.

## 3. Feature branch

`feature/custom-owner-dashboard-foundation`, based on the verified latest main. One PR targets main; owner retains merge authority.

## 4. Architecture decisions

ManagementDashboard Application coordinates definitions, navigation, date/search semantics; Infrastructure owns bounded explicit read queries/schema availability; HTTP adapters validate and return views. No writes or new source of business truth. Existing identity controllers/guards/providers, public Catalog and Customer API source remain authoritative. ADR021 records this decision.

## 5. Reusable Blade components

owner-icon (local SVG), owner-page-heading, owner-status, owner-metric, owner-unavailable, owner-table, owner-pagination plus shared feedback/layout. Existing owner-field/login/profile retained. Seven new components cover meaningful repeated presentation, without a universal data-grid or arbitrary HTTP-driven SQL configuration.

## 6. Sidebar/navigation

Eight actual named-route entries, grouped البداية/الكتالوج/التواصل/الحساب. Stable keys, Arabic labels, icons, route, order, nullable ability and descriptions; unknown routes/abilities filtered. Named-route active states include details. Existing workspace/account remain and overview is a separate real destination. Owner name comes from the guard's fresh authoritative ManagementUser. No fake future menu or browser-stored identity.

## 7. Owner route security

**CONFIRMED:**10 new GET routes inside the existing prefix/RequireOwner group,17 total. Guest/Customer denied on every destination; disabled/restricted/idle/version changes403 and clear Owner. Prefix fail-closed/cache tests updated from7 to17 while strengthening inventory coverage. No alias/admin login, public management API or token. Production HTTPS/session rules and CSRF identity actions retained. Unknown private404/405/validation/SQL errors use safe no-store HTML, not raw trace/Customer JSON. Error rendering skips another identity lookup.

## 8. Layout responsiveness

Desktop persistent sidebar;375/768 mobile/tablet collapsed dialog, overlay dismissal, inert background, focus containment, Escape and restoration. Progressive no-JS navigation remains visible. Top bar: page title, breadcrumb context, global search, current Owner/account. Wide tables and long charts have contained accessible scrolling, not body overflow. Dates/IDs/codes/page fractions explicitly handle RTL/LTR.

## 9. Supported dashboard metrics

Products total/active/hidden; Brands total; Concerns total; registered Customers; submitted preliminary Requests all-time; submitted Requests within selected calendar range. No sales, confirmed Orders, revenue, profit, payment balance, funds, stock quantities or visitor count.

## 10. Authoritative source of each metric

| Metric | Source |
| --- | --- |
| Product total/active/hidden | products COUNT and status SUM in one SQL aggregate |
| Brands/Concerns | respective table COUNT |
| Customers | customers COUNT only, no directory/PII selection |
| Preliminary requests | checkout_intents, status=submitted |
| Selected range/daily chart | same request source, half-open timestamp bounds and grouped COUNT |

Existing hidden data is included for authorized Owner; active Product state alone does not guarantee public visibility through an active Brand. No full-row loading to count. Separate aggregates may reflect slightly different moments under concurrent writes; no cross-domain atomic snapshot is claimed.

## 11. Date-filter semantics

Today/last7days/last30days/current month; custom paired YYYY-MM-DD,1–93 calendar days, inclusive user dates, no future day. Start midnight inclusive/end next midnight exclusive in configured app zone; UTC boundaries for existing UTC timestamp interpretation. Each daily boundary converts independently for DST. Tests cover Damascus+03 and New York23-hour day. Current app timezoneUTC unchanged. Totals are all-time; only filtered card/chart change.

## 12. Charts and real data

Accessible HTML/CSS bars from actual COUNT buckets, explicit dates/counts and a textual daily equivalent. Real empty source shows empty state; no requests means no invented bars. Only existing event source permits zero-filled days. Browser fixture8 requests across7days produced counts1/1/1/1/1/1/2; selecting today changed range count to2, all-time stayed8. These are synthetic isolated stored records, not development/production business statistics.

## 13. Visitor analytics unavailable-state behavior

Reusable owner-unavailable explicitly says «لم يتم تفعيل تتبع الزيارات بعد.» No event collection, IP/user-agent visitor tracking, unique-visitor inference, analytics SDK/provider or false zero counter. Future Next.js capture/data/auth/privacy/consent/retention/bots/reporting/visitor definitions are documented. Not-installed, installed-unavailable and real zero must remain distinct future states.

## 14. Global Search resources

Explicit Products, Brands, Concerns and Preliminary Requests registry. Only safe summaries; no Customer directory, auth/session/security/operational table scanner or future Payment source. Metadata availability query is bounded to eight allowed tables.

## 15. Search matching semantics

Meaningful Unicode2–100 characters, trimmed surrounding whitespace, no control/format chars. Catalog contained name/slug LIKE; request UUID exact or hexadecimal prefix. Native utf8mb4_unicode_ci default, Arabic tested, no stripping Arabic letters/marks. Literal %, _ and ! escaped using bound values and ESCAPE !. Stable name/id or created_at/id ordering. At most10/group,40 total, proven simultaneous full cap across all four groups. No result pagination or full Arabic stemming/typo/semantic/AI claim.

## 16. Search security

**CONFIRMED:** guest/Customer/state/version authorization, query size/type/control/wildcard checks, SQL injection literal bindings, XSS escaping, exact safe fields/destinations. Search SQL excludes Google subject, owner hashes/versions, fingerprints and contact columns; auth provider lookups are separately legitimate identity queries. Unknown query/table/column/sort inputs rejected422. No search term dumps in application logs or private DOM metadata.

## 17. Search limitations

Leading wildcard requires scans/sorts and limit10 bounds output, not database work. No linguistic engine, cursor/group paging or hidden external search service. Larger datasets need measured scaling work. GET search terms may remain in private browser history; external access/APM log policies require production review.

## 18. Protected result destinations

Every returned ID links to owner.products/brands/concerns/requests.show under actual configured prefix. Hidden Catalog inspected separately from unchanged public visibility. UUID constraints/missing record404. No edit, public Customer, imaginary approval or future CRUD destinations.

## 19. Module-specific listings

Four real lists, validated keyword/status/sort/page filters, deterministic ordering, bounded SQL relation counts/join and no contact snippets. Product Brand/availability; Brand/Concern related counts; Request date/currency/item count/recorded fulfillment flag. No fabricated payable total or uniform Variant Product price. Empty results are explained.

## 20. Read-only detail views

Product current metadata/prices/Variants; Brand/Concern related current Products; Request submitted contact name/phone/email/governorate/city/address/notes and historical Product/Variant IDs/quantities/review flags. Related rows paginated25. Request links explicitly refer to current Catalog, not frozen names/prices. No status/contact/payment/shipment/approval write action. Escaping covers stored Product names/descriptions and contact notes.

## 21. Pagination and filtering

10/25/50 list sizes, default25; pages1–1000, related details25. Allowed name/newest/oldest (Requests only date orders), real schema states and currencies. Validated filters preserved in links; unsupported states/columns/order/paging/PII scans422. Brand options at most50, selected Brand retained; others through directory/detail. No arbitrary ORDER BY interpolation or unrestricted offset.

## 22. Selection behavior

**CONFIRMED browser:** page1 selected25visible rows; next page3rows reset selection0; no localStorage entries. Individual/page selection, count, clear and indeterminate state are transient DOM only; no network mutation/session/large ID set. No all-matching/cross-page selection promise or destructive control/API. Future scoped filter snapshot/authorization/bounded confirmation/action contract documented for6C.

## 23. Soft Delete deferral

No deleted_at, SoftDeletes trait, FK cascade, restore/purge or delete API.6C must decide eligible entities, hidden/deleted distinction, Cart/Favorites/history integrity, slug reuse/restore and undeletable financial/audit records. Existing restrictive FKs preserved.

## 24. SQL query counts

**CONFIRMED local,50 and200 Products plus multiple Requests:** fresh guard resolution between actual HTTP requests: overview9, Products6, Requests5, Search7, Product detail5 (empty Variant relation), Request detail6. Counts identical at both cardinalities; no per-row framework queries. Populated related pages can add a bounded data query after count. Ignored log storage/framework/testing/phase6b-full-tests.log includes actual measurements. Constant query count does not imply constant cost; no latency benchmark.

## 25. Index usage

EXPLAIN local9.7 confirms products_status_created_at_id_index covering lookup for status/date sort. Contained name search scans/sorts. Global Request dates can scan Customer/date/id covering index and sort, without a leading Customer equality. Estimates may differ from actual fixture cardinality; no actual scanned-row/latency assertion. No new index migration justified by foundation evidence; future global date index/approved search/cursor design remains measured work.

## 26. Security test results

**CONFIRMED:**43 new meaningful cases cover security/navigation, aggregates/date/DST/missing schema/real SQL failure, search40cap/privacy/escaping/injection, lists/filters/pages/details and SQL scaling. Full final suite394tests/2970assertions. All10 new paths tested for Guest/Customer and four invalid Owner states. Real MySQL unknown-column failure yields sanitized500/no fake cards and logs only exception_type/request_id. Business row snapshots unchanged by reads/unsupported writes. Prior four real Owner concurrency races remain green.

## 27. Accessibility findings

Arabic semantic landmarks/headings/table captions, labelled fields/checks, native keyboard controls, focus outlines, skip link, textual chart, ARIA current/expanded/live status, mobile dialog/inert background/focus control. Escape restores menu button focus. RTL date/page fractions corrected from browser findings. **NEEDS VERIFICATION:** full screen-reader/assistive-technology and exhaustive contrast audit. Browser zoom keyboard commands produced unchanged width1440/DPR1 in headless Chrome, so native zoom success is not claimed; responsive reflow checks are separate evidence.

## 28. Browser tests

**CONFIRMED Playwright CLI, local isolated synthetic fixtures only:** login/workspace; overview/dates (today2/all-time8), Arabic global search and safe links; Product page25selection/reset next page; Brands/Concerns lists; Product and Request details; profile navigation; logout and subsequent overview redirects login.375/768/1440 screenshots inspected; measured scrollWidth=innerWidth at375mobile overview/list/detail,768table and1440overview. Menu Escape/restore, localStorage0, final console0errors/0warnings; profile emitted only a non-error browser autocomplete advisory. CSS/JS200 in preview server logs. Browser expired during a long checkpoint and was reopened/re-authenticated; no unperformed action is counted.

Artifacts ignored in output/playwright/: dashboard-desktop/mobile/tablet/mobile-menu.png, products-tablet/mobile.png, search-tablet.png, requests-desktop.png, product-detail-mobile.png, request-detail-mobile.png. Genuine implemented screens with explicitly synthetic data; not production screenshots. Preview server/browser stopped after checks.

## 29. Full PHPUnit count

**CONFIRMED final local:**394passed (baseline351 retained plus43new), PHP8.4.26/MySQL9.7.0;02:40.840,76MB. No test deletion/weakening. Two identity inventory expectations expand7to17 for added routes, preserving cache/prefix assertions.

## 30. Full assertion count

**CONFIRMED final local:**2970assertions. Earlier393/2962 run preceded the real SQL-failure regression; final394/2970 is the delivery local evidence. Historical allowed race outcomes can slightly change assertion totals; exact CI count is separately checked.

## 31. Historical regression results

All Phase6A351 and prior288 test cases retained: Google/Customer sessions, public Catalog, Favorites/Cart, evaluation/snapshots, preliminary request history/idempotency/concurrency and owner4races. Customer/auth/API/business source and existing migrations unchanged. No external real Google login repeated; provider/app regression and historical owner report remain the evidence. Phase5 gate SHA256 unchanged `fd5c084bc74aa3354b6542d36f6553a533f7dc06b6d0d70b6c4c20436d66c730`; PAY001 preserved.

## 32. Pint result

**CONFIRMED:**Pint --test passes; diff --check clean. Formatter-only unrelated welcome/identity/shared request changes were removed to preserve narrow source scope; their original formatting also passes. No behavior change to Customer request validation.

## 33. PHPStan result

**CONFIRMED:**existing Larastan/PHPStan level5 passes with --memory-limit=512M. Cold local128MB worker exhausted memory; allocating512MB resolved it without ignoring errors, changing scope or lowering rules. CI command explicitly uses the same resource limit. Initial missing-view cache cleared after the actual Blade files were created; no baseline suppression.

## 34. Composer checks

**CONFIRMED:**composer validate --strict, check-platform-reqs and audit pass, no vulnerability advisories. No composer declaration/lock change. Existing PHP8.4/Laravel13 environment preserved.

## 35. NPM/Vite build

**CONFIRMED:**locked npm ci --offline --no-audit --no-fund installs existing87packages; final npm run build passes Vite7.3.7,59modules. CSS51.64kB (gzip11.57), JS54.12kB (gzip20.47). No new dependency/lock change. Local npm11 esbuild install-script advisory occurred, build passed. No CDN or remote icon/analytics dependency; production bundles contain only existing public VITE_APP_NAME reference, no private prefix/credential configuration.

## 36. Files created

ManagementDashboard Application(5: resources, date, term, navigation, global search), Infrastructure read models(3), HTTP request/controller(2). Native owner-dashboard.js;7Blade components, feedback and5dashboard views.5OwnerDashboard test classes plus shared guarded test base. Architecture/web/security docs, ADR021 and this report. Ignored test/preservation/browser helpers/artifacts are not shipped.

## 37. Files modified

Private route registration, owner layout/workspace, layout composer, private error handling, app CSS/JS import, two route-inventory tests, CI analyzer memory flag and documentation indexes/access inventory. No public Customer source, shared collection validator, auth config, source business model/controller/migration or public welcome behavior change.

## 38. Dependencies changed

None. Small native DOM interactions satisfy the current navigation/selection/date/loading need, so Alpine/Livewire/admin/chart/search packages are unnecessary. Existing lockfiles preserved.

## 39. Database migrations

Zero new migrations/schema/index/seed. No pending development migration applied. Missing tables handled explicitly, never created on demand. Full isolated test schema supplied only by the guarded existing test migrations.

## 40. Development data preservation

**CONFIRMED:**actual sumaya_pharma/local/MySQL9.7.0 REPEATABLE-READ checked; read-only sorted full-row and SHOW CREATE TABLE metadata plus .env SHA compared before/after. Eleven tables unchanged:1Customer,3migration rows, other9tables0. Before/after metadata SHA256 both `291dd66b3adc84a507a82565465c0065ad987af39d1852a0d9ce476750d7e75d`. Existing two preliminary-request and one management-user migrations remain unapplied there. No actual identity/Google config modification or real Owner. Physical sumaya_pharma_test positivity retained in every destructive setup/worker/preview guard.

## 41. CI URL/status

**NEEDS VERIFICATION at source report preparation:** PR/source CI not yet published. Source-head and final documentation-head CI must succeed before delivery. Exact final head/run/job/count will be recorded in PR description and final delivery; source-head success is not substituted for final-head evidence.

## 42. PR URL

Pending creation against verified main. Must remain OPEN/UNMERGED, no deployment or6C.

## 43. Final feature SHA

Implementation commit and documentation evidence commit are recorded after publication. The final self-containing report commit hash cannot be embedded in its own source; exact final SHA and independently matched CI proof are recorded in the PR description/delivery. Branch and starting SHA above are already confirmed.

## 44. Known limitations

**POTENTIAL:**contained search/global date scans grow with data; offset pages capped1000, Brand selector50, related pages25 and search10/group limit reach. Concurrent independent metric aggregates need not be one atomic cross-domain instant. Future approved scale work may add indexes/cursors/engine; no Redis/polling/worker introduced. GET query privacy needs external logs/history policy; trusted DBA/server capabilities remain outside application policy.

**NEEDS VERIFICATION:**native browser zoom/full assistive technology, actual production TLS/domain/proxy/shared session/cache/logging, live Google repeat, real activation/schema rollout and large production-load behavior. No production-readiness claim from local/CI success alone.

## 45. Future authorized milestones

6C Catalog creation/editing/visibility/Variants/pricing/safe soft delete/restoration/bulk actions;6D request review/status/annotations/operational workflow. Later owner branding/contact/social, Sham Cash/Al-Haram/COD/zero-deposit/full/fixed/percentage deposit/manual verification/delivery/confirmed Orders remain separate commercial approval under PAY001. Analytics/staff/dynamic settings/verification/recovery/2FA/report export/scheduled backup also deferred. No automatic next stage.

## 46. Owner review recommendation

Review true metric semantics, strict read-only routes, contact disclosure boundary, literal bounded Arabic search, responsive screenshots, selection limitations, EXPLAIN scans and activation prerequisites. Require final current-head CI proof in PR before accepting delivery. Merge remains owner action. Development/customer data unchanged; real prefix/provisioning/rollout and future business control are separate approvals.
