# Favorites and Cart schema

Migration: 2026_10_11_000001_create_favorites_and_cart_tables. Approved additive change; development application is forward-only. The down method exists for isolated testing teardown and must never be invoked on the real development database. Test target is guarded MySQL sumaya_pharma_test, not SQLite; development is sumaya_pharma. No Customer column/data update.

| Table | Columns / integrity |
| --- | --- |
| favorites | customer_id UUID, product_id UUID, created_at NOT NULL; PRIMARY(customer_id,product_id); both restrictive FKs |
| carts | id UUIDv7 PK; customer_id UUID NOT NULL UNIQUE FK; version unsigned BIGINT default 0; required created_at/updated_at |
| cart_items | id UUIDv7 PK; cart_id UUID NOT NULL FK; product_id UUID NOT NULL FK; variant_id UUID NULL; signed INT quantity; required timestamps; selection_key CHAR(36) STORED generated COALESCE(variant_id,'') |

`UNIQUE(cart_id,product_id,selection_key)` rejects duplicate simple selections as well as duplicate exact Variants, while allowing different Variants. An ordinary nullable Variant unique index would permit repeated NULLs. Generated sentinel is the empty string, never an artificial/default Variant, and is hidden from model serialization.

Composite FK `(variant_id,product_id)` references authorized additive Catalog `UNIQUE(id,product_id)`. It rejects cross-Product pairing, referenced Variant parent reassignment and deletion. NULL skips that composite Variant check but the separate Product FK still requires a real parent. CHECK(quantity BETWEEN 1 AND 2147483647) plus signed INT reject unsafe values. All FKs restrict deletion/update; aggregate/item deletion is explicit application behavior, not implicit cascading deletion. Physical Catalog deletion of referenced selections is unsupported; visibility changes retain selections.

| Index | Query / reason |
| --- | --- |
| favorites PRIMARY(customer_id,product_id) | owned pair check/delete and duplicate prevention |
| favorites(customer_id,created_at,product_id) | customer newest-first listing with stable tie-breaker |
| favorites product FK index | referenced Product integrity |
| carts customer_id UNIQUE | single Cart ownership and constant-time lookup; EXPLAIN const, rows=1, Using index |
| cart_items(cart_id,product_id,selection_key) UNIQUE | selection match, duplicate prevention, cart/product prefix |
| cart_items(cart_id,created_at,id) | cart item ordered read |
| cart_items product FK index | referenced Product integrity |
| cart_items(variant_id,product_id) FK index | enforce exact parent pair |
| product_variants(id,product_id) UNIQUE | MySQL composite FK target, explicitly owner-authorized |

The cap of 200 selections and HTTP 16 KiB/throttling live in localized application config, not commercial schema columns. No currency, pricing snapshots, stock, Checkout/Order/Payment policy columns. Version is a JSON string for JavaScript-safe representation. MySQL 9.7 local and MySQL 8.4 CI compatibility/evidence, data counts/fingerprints and concurrency constraints are recorded in the final report; no production capacity guarantee.
