# Favorites API v1

All four routes require existing stateful Sanctum customer authentication and an active Customer. Obtain CSRF cookie and send X-XSRF-TOKEN for browser mutations using the established session flow. Bearer tokens are unsupported. Existing success/error envelopes and X-Request-ID remain. CORS extends methods only to PATCH/DELETE; no origin/credential change.

| Method/path | Input | Result |
| --- | --- | --- |
| GET /api/v1/favorites | page >=1, per_page 1..48, default 12 | 200 existing compact Catalog Product list with links/meta; only visible own pairs |
| GET /api/v1/favorites/check | required product_id UUID | 200 data.product_id + is_favorite; unknown/hidden 404 |
| POST /api/v1/favorites | product_id UUID only | 201 for first pair; 200 for duplicate; data.product_id + is_favorite=true |
| DELETE /api/v1/favorites/{productId} | UUID path, no body/query fields | 200 data.product_id + is_favorite=false, even absent/hidden owned pair |

Unknown fields, submitted prices/ownership and malformed UUID/pagination return 422. Hidden/nonexistent additions are identical 404; unpriced/unavailable visible Products remain favorite-eligible. Suspended Customers return existing 403; guests 401; CSRF failures 419. Mutation body >16 KiB returns safe 413 PAYLOAD_TOO_LARGE. Customer collection reads share 120/min and mutations share 60/min with Cart; exceeded limits return 429 through existing envelope. Favorite pair deletion never removes another Customer's pair.

Listing sorts created_at descending then Product ID. Hidden pairs remain stored and do not affect visible totals; reactivation restores them. GET never cleans up. No per-Variant favorites or Favorite UUID.
