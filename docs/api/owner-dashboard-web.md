# Owner dashboard web contract

Server-rendered Blade HTML, not a public management REST API. `{prefix}` is the configured validated private prefix, never a literal deployment value. All new routes require the existing active Owner/session/version middleware and no-store transport rules.

| Method | Path | Named route | Purpose |
| --- | --- | --- | --- |
| GET | /{prefix}/overview | owner.dashboard | Real aggregates/date chart |
| GET | /{prefix}/search | owner.search | Global search entry/results |
| GET | /{prefix}/products | owner.products.index | Product list/filter |
| GET | /{prefix}/products/{id} | owner.products.show | Current Product/Variant inspection |
| GET | /{prefix}/brands | owner.brands.index | Brand list/filter |
| GET | /{prefix}/brands/{id} | owner.brands.show | Brand/current related Products |
| GET | /{prefix}/concerns | owner.concerns.index | Concern list/filter |
| GET | /{prefix}/concerns/{id} | owner.concerns.show | Concern/current related Products |
| GET | /{prefix}/requests | owner.requests.index | Safe preliminary request list |
| GET | /{prefix}/requests/{id} | owner.requests.show | Protected contact/selection snapshot |

UUID constraints apply to detail IDs. Seven Phase 6A identity routes retain their names/methods and CSRF semantics; total17 Owner routes. No business write endpoint, alias, staff/Customer directory, public search API or all-matching bulk action. Unsupported methods are405 with safe HTML; unknown private destinations are safe404 even with debug enabled.

## Validated queries

Overview: period today/7days/30days/month/custom, default7days. Custom from/to are paired YYYY-MM-DD dates, inclusive endpoints, at most93 calendar days, to>=from, no future day. Noncustom dates with values are rejected. Chart range is configured application timezone, converted to half-open UTC intervals; total cards remain all-time.

Search: q meaningful Unicode2–100 characters, literal contained Catalog name/slug or exact/prefix request UUID. No query displays the entry instructions; explicit empty/whitespace/array/control/oversized queries are422. Ten safe results/group,40 total. No PII free-text search or fuzzy/AI semantics.

Lists: optional q, page1–1000, per_page10/25/50; sort name/newest/oldest for Catalog, newest/oldest for Requests. Products allow status active/hidden, availability available/on_request/unavailable, Brand UUID and Concern UUID. Brands/Concerns allow actual status active/hidden. Requests allow submitted only, USD/SYP, paired bounded from/to and identifier q. Unknown parameters, arbitrary columns/order clauses, payment states and unsupported resource filters are422. Null/empty optional filters are removed before querying; pagination preserves validated nonempty filters. Detail pages allow page only, fixed related page size25.

Validation errors return Arabic422 HTML with no query-value dump or Customer JSON envelope. Anonymous/Customer requests redirect to existing Owner login before data reads; disabled/restricted/idle/version-mismatched identities receive403 and lose Owner authentication. Missing required schema yields503 or explicitly unavailable aggregate/search groups; SQL failures yield sanitized500. Private error responses avoid raw SQL/debug trace and repeat auth lookup failures.

List/search summaries never expose phone/email/address/name contact snapshots, Google subject, password/hash, session tokens, security versions or fingerprints. Only request details disclose explicitly authorized submitted contact fields. Related Product links mean current Catalog inspection, not frozen submission-time metadata. Request currency is a snapshot code, not a payable total.

UI selection applies only to visible rows, resets on navigation/history and has no server mutation or retained ID set. Login/profile/password/logout remain under [Phase 6A](owner-web-interface.md). See [architecture](../architecture/owner-dashboard.md) and [privacy/security](../security/owner-dashboard-access.md).
