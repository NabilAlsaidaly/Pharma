# Public Catalog API

IMPLEMENTED, guest read-only. Exactly eight GET endpoints, with framework HEAD support.
No customer/admin authentication required; no Catalog POST/PUT/PATCH/DELETE/upload.

| Method / URI | Query parameters | Visibility / result |
| --- | --- | --- |
| GET /api/v1/brands | page, per_page | Paginated active brands; sort_order/id |
| GET /api/v1/brands/{slug} | none | Active brand detail; hidden/unknown 404 |
| GET /api/v1/brands/{slug}/products | Product list parameters below | Visible brand required; visible scoped products |
| GET /api/v1/products | Product list parameters below | Active product AND active brand |
| GET /api/v1/products/recommended | Product list parameters below | Same visibility; recommended forced true |
| GET /api/v1/products/{slug} | none | Visible detail, ordered children; hidden/unknown 404 |
| GET /api/v1/concerns | page, per_page | Paginated active concerns; sort_order/id |
| GET /api/v1/concerns/{slug}/products | Product list parameters below | Active concern required; visible scoped products |

Product parameters: q (required nonempty string if supplied, <=200 characters, name-only
literal substring); brand/concern (Unicode letters/numbers separated by single hyphens,
<=255 characters); availability (available/on_request/unavailable, declared parent state);
recommended (true/false/1/0); page integer >=1; per_page 1..48, default 12;
sort newest/name_asc/name_desc, default newest. Newest uses created_at descending;
every sort has id ascending tie-breaker. No price sort or arbitrary SQL columns.
Unknown parameters are ignored and excluded from generated links; validated product
filters are retained in pagination links. Brand/Concern lists use pagination only;
the shared validator also validates supplied product-filter keys, but they do not
change those grouping lists. Known malformed values return 422 VALIDATION_FAILED.
Valid nonexistent brand/concern filter yields an empty list. Scoped route lookup
for unknown/hidden grouping yields 404. Out-of-range page gives empty data safely.

List envelope: success=true, localized message, data=[records], Laravel links/meta
at the top level, including total/current_page/per_page/last_page. Detail envelope:
success=true, localized message, data={record}. All requests receive X-Request-ID.
Errors retain the foundation envelope and matching request_id, e.g.:

```json
{"success":false,"error":{"code":"RESOURCE_NOT_FOUND","message":"المورد المطلوب غير موجود.","fields":{}},"request_id":"uuidv7"}
```

Brand fields: id/name/slug/description/logo_url/meta_title/meta_description.
Concern fields: id/name/slug. Brand summary in product: id/name/slug only.
Product list fields: id/name/slug/short_description/brand/availability_status/
is_recommended/pricing/primary_image. Detail additionally exposes full_description,
usage_instructions/suitable_for/benefits, ordered variants/images and active concerns.
No status/internal storage path/generated image key/private filesystem path is exposed.

Direct pricing example (both prices may be null, including active products):

```json
{"source":"product","price_usd":"12.50","price_syp":null}
```

Variant product summary:

```json
{"source":"variants","price_usd":null,"price_syp":null,"range_usd":{"min":"10.00","max":"15.00"},"range_syp":{"min":null,"max":null}}
```

Each detail variant exposes id/name/price_usd/price_syp/availability_status/
effective_availability_status/sort_order. Currency values are decimal strings/null,
never floats, conversion or inherited price. Missing is null; configured zero is
"0.00" and not permission to buy for free. Price visibility is not purchase eligibility;
future purchase requires a valid configured selected-currency price >0 and future
checkout checks. No purchasing endpoint exists. Effective availability uses V1:
unavailable dominates, then on_request, otherwise available; declared parent unchanged.

Image fields: id/url/is_primary/sort_order. Primary flag wins; absent explicit primary
uses first sort_order/id image. No metadata yields primary_image=null and images=[].
Unsafe image references produce url=null; no placeholder or per-image file lookup.
Hidden concerns are excluded from details but do not hide a visible product.
Public writes, management authentication, image management, Cart/Checkout and future
payment/order processing are PLANNED, NOT IMPLEMENTED.
