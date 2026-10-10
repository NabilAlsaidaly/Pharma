# Phase 3 — Catalog design gate

Status: **APPROVED — P1 + C2 + V1 + S1**, explicitly authorized by phase3.1.md.
Checkpoint A inspected; Checkpoint B passed. Continue Checkpoints C/D.
The original pending proposal below is preserved as historical context; C1 was not
selected. ADR-016 records the accepted decisions, including optional prices and
future purchase requiring a configured price greater than zero.
Source: C:/Users/nabil/Desktop/sumaiya/phase3.md, especially sections 11 and 51.
No Catalog migration, model, route, seed data or payment implementation is created.

## Verified starting state

- Laravel 13.35.0, PHP 8.4.26, Composer 2.10.2, Herd CLI 1.30.0.
- main, initially clean, HEAD 8d9e355c09bfe531ab09bd45cfc8f1fb4ed9927e; expected Pharma origin.
  feature/catalog-foundation does not exist locally. No branch/commit/push created
  at this approval checkpoint; documentation changes remain local for review.
- [Baseline CI 38042922686](https://github.com/NabilAlsaidaly/Pharma/actions/runs/38042922686)
  completed successfully for that exact HEAD; CI retains official MySQL 8.4.
- Development connection verified as sumaya_pharma, MySQL 9.7.0, two tables:
  customers and migrations. Existing customer count: **1**.
- Customer snapshot SHA-256 before changes:
  bfd1fec4fa98a4844988f217c4a4a887a41861e2f5559397fea314cf8184ab6c.
  Hash covers all rows/columns in ID order; no personal data, identifiers or secrets
  are printed. Post-documentation comparison completed: customer count remains 1
  and the complete row snapshot hash is identical. No customer data changed.
- Only migration 2026_10_10_000001_create_customers_table exists; Ran, batch 1.
  No pending Catalog migration. No development migration/reset command executed.
- Existing Identity implementation, guard, OAuth state, CSRF/CORS and test safeguards
  inspected; unchanged. Baseline suite freshly rerun: **38 passed, 243 assertions**,
  including 28 Identity tests, against separate sumaya_pharma_test only.
- File cache/session, sync queue retained. Redis operational status not reverified;
  no Redis installation or driver change. Public storage link is not present.
- Local Google credentials are now configured (presence only checked). The brief
  reports the owner's successful real login and redirect. This is owner-reported
  evidence, consistent with one persisted customer, not independent OAuth verification.
  No live Google login repeated. Phase 2 report is retained as historical evidence.
- Current APP_URL was changed locally since Phase 2 to an Expose HTTPS host; left
  untouched. No existing Google secrets or redirect configuration altered.
- All required architecture/API/CI documents, existing ADRs 001–014, phase reports,
  runtime/configuration/Identity files and migrations inspected. composer.lock has
  Laravel 13.35.0, Socialite 5.31.0 and Sanctum 4.3.3; dependencies unchanged.
- No SRS/PDF found inside the repository. Approved Catalog requirements come from
  the Phase 3 brief; no inaccessible SRS is claimed as evidence.

## Decision classification

APPROVED means explicit owner brief/accepted ADR evidence. A technical choice below
is a proposed implementation detail, not a newly approved commercial rule.

| Decision | Classification | Evidence / proposed resolution |
| --- | --- | --- |
| Brand primary grouping, one Brand per Product | APPROVED | Phase 3 sections 12–13; ADR-008 |
| Product has zero/many variants and images; many/many concerns | APPROVED | Section 12 |
| Product status active/hidden only | APPROVED | Section 14 |
| Product availability available/on_request/unavailable | APPROVED | Section 14 |
| Recommendation is a flag, not an engine | APPROVED | Sections 12/18 |
| Public reads only; parent visibility on every access path | APPROVED | Sections 25/27 |
| A: Product owns prices when it has no variants | BUSINESS CLARIFICATION REQUIRED | Not fixed by brief or existing implementation; option P1 below |
| B: Variant owns prices vs inherits product prices | BUSINESS CLARIFICATION REQUIRED | Exact fallback explicitly withheld in section 15 |
| C: Direct product vs internal default variant | BUSINESS CLARIFICATION REQUIRED | Default variants cannot be introduced silently |
| D: Missing currency and both-prices-null behavior | BUSINESS CLARIFICATION REQUIRED | One currency may be absent; sellability with neither price is unspecified |
| E: Variant availability states and product/variant interaction | BUSINESS CLARIFICATION REQUIRED | Product states specified; variant states/interaction not fixed |
| Brand status and Concern status semantics | BUSINESS CLARIFICATION REQUIRED | No accepted lifecycle values found; propose active/hidden with visible active only |
| F: Future optional quantity location | DEFERRED | No stock columns now; future direct product/variant allocation depends on approved P1/P2 |
| G: Product/parent visibility at SQL query boundary | APPROVED | Must apply to list/detail/search/brand/concern/recommended endpoints |
| H: Decimal-safe money | APPROVED | Section 19; no floats or conversion |
| H: Numeric precision/storage scale | TECHNICAL IMPLEMENTATION CHOICE | Propose DECIMAL(18,2), non-negative CHECK, decimal strings; explicit limit below |
| I: At most one primary image; missing-image fallback | TECHNICAL IMPLEMENTATION CHOICE | Propose 0/1 primary, safe deterministic read fallback; no placeholder/image invented |
| J: Optional product type | DEFERRED | Not among allowed list filters; no taxonomy/table/column invented now |
| Payment before order, configurable deposit/full payment | APPROVED | PAY-001 sections 7–8; documented in ADR-015 |
| Hard vs soft permanent deletion | DEFERRED | No deletion endpoints; restrictive FKs proposed, no blanket SoftDeletes |
| Query/pagination implementation | TECHNICAL IMPLEMENTATION CHOICE | SQL filtering/eager loading, bounded Laravel pagination, whitelisted sorting |
| Capacity and production volume | ARCHITECTURAL DECISION REQUIRED | CAPACITY ASSUMPTIONS NOT YET APPROVED; no fabricated benchmark |

## Approval package P: pricing and sellable selection (A/B/C)

**P1 recommended:** A product without variants owns price_usd/price_syp directly.
For a product with variants, each variant owns its prices with no inheritance; parent
prices are null and not authoritative. No generated internal default variant.
DB: nullable currency columns on both products and product_variants; write/persistence
rules must prevent conflicting price ownership. API: explicitly identify selection
price owner; listing gives per-currency variant ranges when applicable; detail gives
exact variant prices. Future Cart uses product_id and nullable variant_id; Checkout
requires a variant for a variant product. Simple browsing matches the zero-variant
relationship, but future write rules must enforce ownership transitions.

**P2 alternative:** Every sellable item uses a variant, including an internal default
for products without visible choices. DB: prices only in product_variants; requires
an approved default-variant representation/invariant. API hides or explains the
default selection; Cart/Checkout always use variant_id. Uniform checkout rules,
but adds implicit records and changes the direct zero-variant commercial model.

**P3 alternative:** Product owns base prices; nullable variant currency overrides
inherit each missing currency independently. DB: prices in both tables; API must
expose effective price and inheritance/source. Cart/Checkout recalculate the effective
selection price; convenient editing, but ambiguity when only one currency override
exists and more complex history snapshots. No inheritance is implemented without approval.

Owner approval required: P1, P2 or P3 (or an explicit alternative).

## Approval package C: currency semantics (D)

**C1 recommended:** USD/SYP are independent configured prices. Either may be absent;
missing currency serializes null, with no exchange rate/fallback/zero substitution.
At least one non-negative price is required for an active sellable selection; a hidden
product may remain unpriced. Future Checkout accepts only a configured currency.
DB: nullable DECIMAL pairs plus approved persistence rules for active selections;
API emits exact decimal strings/null. This avoids advertising an active sellable
selection with no price, while allowing one-currency operation.

**C2 alternative:** Both currencies may be null even on active selections; API shows
an explicit unpriced state. DB does not require one price. Future Cart/Checkout must
reject buying an unpriced selection or use a separately approved quotation process.
Allows unpriced visible information, but the commercial meaning must be explicit;
Phase 3 would not implement quotation or checkout.

Both options use independent currency values and never convert. Owner approval
required: C1 or C2. Whether zero-value products are commercially allowed can be
restricted separately; the technical proposal rejects negative values and permits zero.

## Approval package V: variant availability (E)

**V1 recommended:** Variants use exactly available/on_request/unavailable. Product
availability is a manual parent declaration, not derived from variants. Effective
future selection is unavailable if either parent or variant is unavailable;
otherwise on_request if either is on_request; otherwise available. API preserves
both declared values; it may expose this explicit effective value once approved.
No inventory guarantee or reservation. DB: variant availability_status with the same
allowed-value CHECK; no aggregation or quantity field. Future Checkout checks both.

**V2 alternative:** Same three variant states, but a variant product's displayed
product availability is derived: available if any variant available, otherwise
on_request if any on_request, otherwise unavailable. No independent override on
variant products. DB must define synchronization or query-time derivation; API
returns computed parent availability; Checkout checks the chosen variant. Easier
summary for buyers but more writes/aggregation and an explicit commercial policy.

Owner approval required: V1 or V2 (or exact alternative states/precedence).

## Approval package S: Brand/Concern public status

**S1 recommended:** Both use active/hidden only. Only active brands/concerns are
returned publicly; hidden brand blocks all child product paths. Hidden concern is
excluded from product detail and its products endpoint returns 404, without hiding
otherwise active products. DB: status varchar with allowed-value CHECK. API never
exposes hidden grouping records. Future management can hide grouping without deleting.

**S2 alternative:** Both use active/inactive, public only active; same query/API
behavior, but lifecycle language describes disabled rather than hidden grouping.
No draft/archive/deleted lifecycle is introduced under either option.
Owner approval required: S1 or S2 (or exact entity-specific values/semantics).

## Conditional schema proposal (NOT IMPLEMENTED)

The following exact candidate uses P1/C1/V1/S1. It is **not accepted schema** until
owner approval. Money precision and other technical choices can be amended before
migrations. All six tables belong exclusively to Catalog; utf8mb4, UUID keys char(36),
required created_at/updated_at timestamps. No Identity FK or changes to customers.

| Table | Candidate columns |
| --- | --- |
| brands | id PK; name varchar(255) NOT NULL; slug varchar(255) NOT NULL globally UNIQUE; logo_path varchar(2048) NULL; description text NULL; status varchar(16) NOT NULL default hidden [S1]; sort_order unsigned int NOT NULL default 0; meta_title varchar(255) NULL; meta_description text NULL; timestamps |
| products | id PK; brand_id char(36) NOT NULL FK brands.id; name/slug varchar(255) NOT NULL, slug globally UNIQUE; short_description text NULL; full_description longtext NULL; status varchar(16) NOT NULL default hidden; availability_status varchar(16) NOT NULL default unavailable; is_recommended boolean NOT NULL default false; usage_instructions/suitable_for/benefits text NULL; price_usd/price_syp DECIMAL(18,2) NULL [P1/C1]; timestamps |
| product_variants | id PK; product_id char(36) NOT NULL FK products.id; name varchar(255) NOT NULL; price_usd/price_syp DECIMAL(18,2) NULL [P1/C1]; availability_status varchar(16) NOT NULL default unavailable [V1]; sort_order unsigned int NOT NULL default 0; timestamps |
| product_images | id PK; product_id char(36) NOT NULL FK products.id; path varchar(2048) NOT NULL, relative to configured public disk; is_primary boolean NOT NULL default false; sort_order unsigned int NOT NULL default 0; timestamps; nullable generated primary_product_id char(36) = product_id when primary, otherwise NULL, UNIQUE (technical invariant, must validate on MySQL 8.4/9.7 before adoption) |
| concerns | id PK; name/slug varchar(255) NOT NULL, slug globally UNIQUE; status varchar(16) NOT NULL default hidden [S1]; sort_order unsigned int NOT NULL default 0; timestamps |
| concern_product | product_id char(36) NOT NULL FK products.id; concern_id char(36) NOT NULL FK concerns.id; composite PK(product_id,concern_id); no UUID or timestamps |

FK actions proposed: RESTRICT on delete/update, no implicit mass removal. Actual
future deletion workflow remains deferred. No soft-deletion columns. Product price
ownership across tables needs a focused transactional persistence operation, not an
untruthful cross-table CHECK. Individual money columns get non-negative CHECKs;
application also enforces approved currency presence and ownership semantics.
DECIMAL(18,2) allows up to 9,999,999,999,999,999.99; PHP/JSON representations are strings,
never floats. Two fractional digits and this maximum are technical proposals, not
evidence of approved commercial price ranges; owner may request a different scale.

The generated unique image column is a persistence invariant, not a public field.
Fallback: flagged primary first, otherwise first image by sort_order then id; no
image returns null and images=[]. Reject unsafe/absolute/traversal/executable paths
from URL serialization. No upload route/service and no third-party storage activation.
Use existing public disk URL configuration; no hard-coded host or private path output.

```text
Brand 1 -- * Product
Product 1 -- 0..* ProductVariant
Product 1 -- 0..* ProductImage
Product * -- * Concern (concern_product; unique pair)
```

## Planned API contract (NOT IMPLEMENTED)

Exactly these guest GET routes after approval; no mutation/upload endpoints:

| URI under /api/v1 | Planned response |
| --- | --- |
| /brands | Paginated visible brand summaries |
| /brands/{slug} | Visible brand detail; otherwise 404 |
| /brands/{slug}/products | Paginated visible products for visible brand |
| /products | Paginated SQL-filtered visible products |
| /products/recommended | Same visibility; recommended=true; register before slug route |
| /products/{slug} | Visible product plus ordered variants/images/visible concerns; otherwise 404 |
| /concerns | Paginated visible concern summaries |
| /concerns/{slug}/products | Paginated visible products for visible concern |

Proposed list validation: q trimmed nonempty text <=200 characters when supplied;
search product name only with bound LIKE parameters, literal %/_ escaped; brand and
concern slug <=255 with Unicode letters/numbers and hyphens; availability one approved
code; recommended normalized true/false/1/0; page integer >=1; per_page default 12,
range 1..48; sort whitelist newest/name_asc/name_desc, default newest; stable id
tie-breaker. No arbitrary SQL sorting; no price sort until pricing design is approved.
Brand/concern lists use sort_order then id. Malformed filters return existing 422
VALIDATION_FAILED with request_id. A valid unknown filter slug yields an empty list;
an unknown/hidden route grouping yields 404. Pagination uses data/links/meta within
success/message/data contract, out-of-range page empty. Exact resource pricing fields
remain conditional on P/C approval. Detail selects only its actual relationships.

## Proposed query/index plan (NOT CREATED OR MEASURED)

| Table / index | Query / reason |
| --- | --- |
| brands UNIQUE(slug); (status,sort_order,id) | Exact visible slug lookup; bounded ordered visible brand list |
| products UNIQUE(slug); (status,created_at,id) | Detail lookup; stable newest visible listing |
| products (brand_id,status,created_at,id) | Brand filter/list and FK prefix; no redundant single brand_id index |
| products (status,is_recommended,created_at,id) | Recommended list with same parent visibility |
| product_variants (product_id,sort_order,id) | Batch ordered detail variants and FK prefix |
| product_images (product_id,sort_order,id); UNIQUE(primary_product_id) | Batch image order and at-most-one-primary invariant |
| concerns UNIQUE(slug); (status,sort_order,id) | Visible concern lookup/list |
| concern_product PK(product_id,concern_id); (concern_id,product_id) | Detail associations, reverse concern filter and both FK prefixes |

Product visibility is one reusable SQL boundary: active product AND active parent
brand. Concern filtering uses EXISTS/pivot condition with approved concern visibility.
List loads selected brand summaries and primary/fallback images in batches; variant
price summaries use bounded SQL aggregates if P1 approved. Detail eagerly loads
only its variants/images/concerns. Plan avoids per-product queries and PHP filtering.
Query counts must be measured after implementation on small/larger test datasets;
no universal count or latency claimed now. EXPLAIN pending real schema/fixtures.
Substring LIKE and name sorting may scan/filesort; ordinary slug/status BTREE indexes
do not make arbitrary Arabic substring search index-efficient. No speculative search
engine/full-text index/cache. Availability composite indexing is deferred until
approved query distribution/EXPLAIN justify it, rather than indexing every filter.
**CAPACITY ASSUMPTIONS NOT YET APPROVED.**

## PAY-001 and future compatibility

PAY-001 is documented in ADR-015 and payment-before-order-rule.md. Current price,
selected currency, IDs, visibility and declared availability will be consumed by
future Checkout, which owns historical snapshots. No stock allocation from Catalog,
Cart or proof upload; verified required payment and availability recheck precede
order confirmation. Future quantity ownership is deferred; P1 would require an
explicit future product/variant stock strategy, while P2 could place it on variants.
No quantity/reservation/checkout/payment schema is created at this gate.

## Gate result and next action

EXPECTED: approved commercial schema before Catalog migrations.
ACTUAL: A/B/C/D/E and Brand/Concern status semantics lack an accepted decision.
CAUSE: the brief deliberately requires owner approval and existing Catalog has only
empty scaffolding; accepted ADRs do not supply these missing semantics.
IMPACT: Catalog schema, resources and all eight read APIs wait at Checkpoint B.
WHAT WAS NOT CHANGED: development/customer data, Identity/Google/auth configuration,
application/routes/dependencies/CI, System Console and management areas.
SAFE NEXT ACTION: owner approves P1/P2/P3, C1/C2, V1/V2, S1/S2, plus any requested
technical-proposal changes. Record approved choices in a new Catalog ADR, create a
verified feature/catalog-foundation branch, then continue Checkpoints C and D.

Recommended approval bundle: **P1 + C1 + V1 + S1**, with the proposed technical
precision/image/query details. A recommendation is not approval. No Phase 3
implementation-complete report, commit/push or PR is created before this checkpoint.

Files created at this gate only: docs/reports/phase-03-catalog-design-gate.md,
docs/adr/ADR-015-flexible-payment-before-order.md and
docs/architecture/payment-before-order-rule.md. No tracked source file modified
or deleted. git diff --check passed; these three Markdown files remain untracked
for owner review. Baseline CI is verified; no new remote CI run is claimed.
