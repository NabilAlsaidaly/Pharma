# Custom Owner dashboard

Phase 6B implements a read-only management presentation module in the existing Laravel application. See [ADR-021](../adr/ADR-021-custom-owner-dashboard.md), [web contract](../api/owner-dashboard-web.md), [access/privacy](../security/owner-dashboard-access.md) and [evidence](../reports/phase-06b-owner-dashboard-report.md). Customer storefront remains separate Next.js; System Console remains independent and untouched.

## Architecture and authorization

ManagementDashboard/Application owns resource definitions, the small navigation registry, normalized literal search terms, bounded date ranges and GlobalSearch coordination. Infrastructure/ReadModels owns explicitly selected MySQL readers, aggregates and schema availability. Interfaces/Http validates requests and returns Blade views. Templates render supplied data; they do not issue SQL. No business write action or second source of Product/Request truth is introduced.

Ten GET endpoints extend the seven Phase 6A identity routes inside the existing validated private prefix and RequireOwner group. Owner.home retains its workspace and account behavior and links to the separate overview. Default Customer guard, Sanctum, public Catalog and authentication are unchanged. Exact active Owner role, idle/version checks, secure production transport/session rules and no-store headers remain authoritative. Navigation visibility cannot replace middleware.

The registry defines stable key, Arabic label, icon identifier, named route, group, order, nullable ability and description. It excludes undefined routes and unauthorized abilities and marks active sections by named routes. Current entries use the existing exact Owner foundation; no speculative staff permissions or database menu settings. The layout composer supplies the authenticated ManagementUser and resolved entries. Generic failures skip identity resolution while rendering errors, avoiding a second failing auth database lookup.

## Statistics and time

Eight cards: products COUNT(*), active/hidden product status SUMs, brands COUNT, concerns COUNT, customers COUNT, submitted preliminary requests COUNT and submitted requests in the selected date range. Totals include hidden records and all dates; active Product count describes its own record state, not guaranteed public visibility through an active Brand. No revenue, profit, paid amount, confirmed Order, stock quantity or visitor inference.

Daily chart values are real submitted-request COUNTs. Presets: today, last7 calendar days, last30 days, current month; custom inclusive dates span at most93 calendar days and cannot end after today. Selected-zone start is inclusive, the midnight after the last selected day is exclusive. The current application zone is UTC and persisted Laravel timestamps are interpreted as UTC. Range boundaries are converted to UTC. Each daily bucket independently converts its configured-zone boundaries, including DST transitions; bounded SQL CASE bindings avoid requiring MySQL named-timezone tables. Tests cover Damascus +03 and a New York23-hour DST day.

Zero-filled days are allowed only when checkout_intents exists. Empty real observations show a meaningful empty chart, never example bars. Absent schema produces nullable unavailable cards/groups or safe503 lists/details. A real SQL error propagates to a safe500, not false zero success. The metadata query examines only eight explicitly allowed source tables and is cached only within its reader instance/request; no persistent schema/data cache.

## Search

Explicit resources: Products, Brands, Concerns and Preliminary Requests. Catalog matches name/slug using literal contained LIKE; UUID request IDs use exact matching or hexadecimal identifier prefix matching. Request contact fields, Customer directories, credentials, sessions and operational tables are excluded. Results select only safe columns and link to actual protected UUID detail routes, including hidden Catalog records. Request contact snapshots appear solely on the protected detail page.

Meaningful Unicode queries have2–100 characters and exclude control/format characters. Unicode surrounding whitespace is trimmed; Arabic letters/diacritics are not silently stripped. Existing utf8mb4_unicode_ci collation controls comparison behavior. No stemming, typo tolerance, semantic/AI search or external engine is promised. Bound values and explicit ESCAPE ! make %, _ and ! literal. At most10 results per group/40 combined, deterministic name/id or created_at/id ordering. Search entry page without a query is supported; submitted empty queries are422. There is no search-result pagination or public API.

## Lists and details

Four lists use bounded server pagination10/25/50 (default25), pages1–1000, resource-specific filter/sort allowlists and preserved query links. Product rows join Brand and count Variants in SQL; they expose no fabricated parent price for Variant products. Brand/Concern product counts use indexed relationships. Requests list IDs, dates, status, currency, item counts and historical review flags without contact details or financial totals. Details paginate related rows25 at a time. Product/Variant prices displayed are current Catalog values. Request detail displays the immutable submitted contact/selection snapshot; linked Products are explicitly current metadata, not historical names/prices.

Brand filter options are bounded to50, retaining a selected Brand. Other Brands remain accessible through their paginated directory/details. Native text/labels, status badges, headings, tables, pagination and unavailable states are composable Blade components. No universal HTTP-driven SQL/data-grid engine.

## Interaction and accessibility

Arabic lang/RTL, LTR credentials/IDs/codes/date spans, skip link, semantic landmarks, focus outlines, labelled controls and textual chart equivalent. Desktop sidebar is persistent; mobile/tablet toggle controls a labelled dialog with focus containment, inert background, accurate expanded state, Escape/overlay dismissal and focus restoration. Without JavaScript navigation remains visible. Tables/charts use contained keyboard-accessible scrolling where needed, without page overflow. Small native JavaScript handles navigation, request loading feedback, date controls and current-page-only checkbox selection; no added Alpine/Livewire/chart dependency or browser storage.

Selection does not make a network call or mutate records and is cleared on page/history navigation. Future all-matching selection requires a server-authorized filter snapshot, bounded processing, explicit confirmation, result/audit contracts and separately approved actions; no hidden destructive endpoint exists now.

## Performance and scaling

Observed fresh-guard HTTP query counts at50/200 Products: overview9, Products6, Requests5, Search7, Product detail5 for an empty Variant relation, Request detail6. Populated related pages can add one data query after the paginator count; all remain bounded. Constant SQL counts do not imply constant execution cost. Aggregation is in SQL and displayed relations do not create per-row framework queries.

EXPLAIN confirms the existing Product status/date/id covering lookup; contained name matching scans and sorts. Global Request date queries can scan the existing Customer/date/id covering index and sort because Customer is its leading column. No false claim of an indexed global date range seek. No new migration/index was justified by the measured foundation dataset. Larger datasets may justify a separately evidenced created_at/id index, cursor paging or an approved search engine; leading wildcard costs remain a documented limitation. No latency or production throughput benchmark is claimed.

## Analytics and future boundaries

Reusable unavailable presentation explicitly states: «لم يتم تفعيل تتبع الزيارات بعد.» No visitor zero counter, event capture, IP/user-agent collection, unique-visitor inference, external analytics SDK or provider. A future Next.js integration needs defined visitors/sessions, authenticated versus anonymous handling, trusted event/data source, bot filtering, privacy/consent, retention, reporting cadence and authenticated integration. Installed-but-unavailable tracking must later differ from not-installed and real zero observations.

6C may add approved Catalog editing, pricing, visibility, bounded bulk actions and safe soft delete/restore. It must decide eligible entities, hidden-versus-deleted semantics, Cart/Favorites behavior, restrictive history FKs, slug reuse/restoration and records that cannot be deleted. No deleted_at or cascading FK change now. 6D may add request review/status/annotations/actions. Later branding/contact/payment/COD/deposit/delivery/confirmed Orders and analytics/staff/recovery/2FA/export/backup work remain separate approval scopes. PAY-001 and historical gates are preserved.
