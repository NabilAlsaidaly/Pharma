# Catalog foundation

IMPLEMENTED in Phase 3, approved P1 + C2 + V1 + S1 (ADR-016).
Catalog owns Brand/Product/Variant/Image metadata/Concern and their read APIs only.

Domain contains CatalogStatus, AvailabilityStatus with V1 precedence, CatalogPrice
normalization and ConflictingPriceOwnership. It imports no Eloquent, HTTP or Storage.
Application contains CatalogFilters and the meaningful PublicCatalogReader port;
the read boundary returns Laravel's pagination contract and object records without
unnecessary copies of every profile field. EloquentPublicCatalogReader implements
SQL queries in Infrastructure; Eloquent models/relations also belong there.
Interfaces contains validated requests, the thin public controller, explicit Resources
and safe configured-public-disk URL serialization. AppServiceProvider binds the port.
No generic repository, service per model, new module provider or management API.

P1: direct product prices when no variants; variant-owned independent prices otherwise.
Parent prices are never authoritative for variant products. Product.save locks the
parent before rejecting prices when variants exist; ProductVariant.save locks old/new
parents in ID order and rejects a priced destination. Clear parent prices explicitly
before adding variants. Prices use a shared persistence trait and a decimal-safe
Domain rule; floats, negatives, excess scale/range and exponent notation are rejected.
DB independently rejects negative amounts. Do not bypass these ownership rules with
raw SQL or bulk Eloquent updates. No structural editing endpoint exists.

C2: both currency prices may be null even for active products. Null is not zero;
zero stays a decimal string and does not authorize a free purchase. Price ranges
ignore missing variant prices, without conversion/inheritance. Listing ranges can
come from different variants; future Checkout must price the exact selected variant.
Visibility and purchase eligibility are distinct. Future purchase requires a configured
selected-currency price greater than zero, plus future checkout/payment checks.
No purchase eligibility service or Cart/Checkout implementation is created.

V1: declared parent and variant states are independent; unavailable dominates,
then on_request, then available. Variants expose declared and effective values;
the parent is never automatically overwritten from variant states.
S1: active products AND active brands only, applied in a reusable SQL scope across
every read path. Active concerns only; hidden concerns do not hide visible products.

Images store relative public-disk references, with no binary data or signed URLs.
At most one explicit primary is enforced in MySQL. Ordered fallback is deterministic.
Unsafe/traversal/absolute/encoded/executable paths produce null URLs. Only raster
extensions jpg/jpeg/png/webp/gif/avif are serialized; double executable extensions
are rejected. No upload is accepted or executed. No placeholder is generated.
Metadata without a corresponding file can still produce a configured URL; reads do
not perform filesystem existence checks per image. Future authorized management must
validate actual file content and prevent broken references. Public storage symlink
is not activated; no production image content exists in development.

All development Catalog tables are initially empty; tests use isolated fixtures only.
Identity/Google/Customer schema and data remain unchanged. System Console, management,
stock, cache, payments and all future modules remain outside implementation.

## Phase 6C correction FIX-6C-001

Physical Variant existence reserves P1 ownership even after archival. Public/current
Variant collections and currency min/max still exclude SoftDeletes. Product's explicit
`withPricingOwnership()` SQL EXISTS projection (`has_physical_variants`, including
archived rows) determines ProductResource pricing.source independently of the current
variants_count. Both EloquentPublicCatalogReader and the Favorites listing apply it;
every ProductResource consumer is covered without exposing the internal flag or
archived metadata, adding queries, or hydrating Variants for ownership. All-archived
Products return source=variants with null direct prices and null range endpoints.
Direct Products retain the existing pricing JSON shape. Cart/Checkout physical counts,
admission, retention and historical idempotency remain unchanged. See the
[reproduction and regression report](../reports/phase-06c-fix-001-pricing-ownership.md).
