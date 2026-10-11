# Cart API v1

Private session-authenticated active-Customer routes use the same CSRF/request-ID/envelopes as Identity. Unknown body/query fields are rejected, including Customer/Cart ownership, prices, currency, totals, stock and purchasability. Never submit frontend monetary calculations.

| Method/path | Accepted input | Semantics |
| --- | --- | --- |
| GET /api/v1/cart | page >=1, per_page 1..48, default 12 | 200 complete-Cart evaluation, page of items; no record creation |
| POST /api/v1/cart/items | product_id UUID; optional nullable variant_id UUID; quantity strict JSON integer | 201 new selection, 200 increment existing; every accepted call adds quantity |
| PATCH /api/v1/cart/items/{itemId} | expected_version canonical unsigned decimal STRING; exactly one quantity or nullable variant_id | 200 absolute quantity or Variant change/atomic merge; stale version 409 |
| DELETE /api/v1/cart/items/{itemId} | UUID path; no body/query fields | 200 removed=true, cart_version; absent/non-owned removal has same harmless success |

GET data has id (null until first successful add), version string, evaluated_at timestamp, item_count, items, totals and requires_request_count, plus links/meta. Version range is 0..18446744073709551615. Items sort created_at descending then UUID. Page totals always represent the complete Cart. Mutations return affected item, cart_version, merged boolean and removed_item_id (null unless merged). Surviving item ID is returned after merge; clients should fetch current Cart to update pagination and totals. DELETE retains an empty persistent Cart. No-op PATCH does not advance version; changed mutations advance once.

```json
{"product_id":"<Product UUID>","variant_id":"<Variant UUID>","quantity":2}
```

```json
{"expected_version":"4","quantity":3}
```

Visible item projection: id, quantity, product {id,name,slug,primary_image}, variant null or {id,name}, evaluation {state,reasons,availability,requires_request}, pricing {USD,SYP}, each with unit_price (decimal string/null), line_estimate (string/null), price_valid. Hidden selection projection: owned id/quantity, product=null, variant=null, pricing=null, evaluation {state:blocked,reasons:[selection_inaccessible]}; no hidden Catalog identity or metadata.

Each currency independently returns priced_subtotal string, estimated_total string/null, complete boolean, excluded_item_count, exclusion_reasons object. One primary reason per excluded item, in precedence: selection_inaccessible, selection_invalid, selection_unavailable, missing_price, zero_price. No conversion or range price. Any exclusion invalidates the complete estimate. On-request positive lines can contribute but require additional handling, counted across the whole Cart. Empty Cart: item_count=0, both subtotal/estimate=0.00, complete=true, excluded=0 and empty reason object; this does not authorize free purchase.

Wrong/missing Variant for a Product with Variants: 422. Hidden/unknown initial Product: 404. Unavailable/unpriced admission, hidden retained increase/change, capacity, stale version and version overflow: safe 409 RESOURCE_CONFLICT with constant reason in error.fields.selection. Missing/non-owned PATCH item: 404. Quantity 0, negative, string, float, null, boolean, overflow or unknown fields: 422; quantity is only 1..2147483647 representation safety, with commercial maxima UNDEFINED. Merge overflow is 422 and fully rolled back. Max 200 distinct selections, body 16 KiB (413), shared reads 120/min/writes 60/min (429); locks exhausted after three database attempts: safe 503. Guest 401, suspended 403, CSRF 419. No stock promise, checkout, payment or order endpoint.

Network contract: disable duplicate in-flight submissions. POST is NOT idempotent; automatic retry after unknown outcome is unsafe. Fetch current Cart first and obtain user-confirmed retry. PATCH with its old expected_version fails after a successful change, preventing accidental second application. This is not exactly-once POST semantics; R2 infrastructure is deferred.

