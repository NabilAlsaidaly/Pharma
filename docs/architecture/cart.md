# Cart foundation

Status: IMPLEMENTED, Phase 4, ADR-017. Cart stores selections, never commercial authorization or stock allocation. Application operations coordinate persistence and Catalog evaluation; `CartQuantity` centralizes integer safety, `CartEvaluator` performs decimal-safe current evaluation and `CartSnapshot` supplies a consistent complete-Cart read. Controllers/resources contain transport/presentation only. No generic repository, events framework or new dependency is introduced.

## Ownership and writes

One lazy persistent Cart per Customer; GET returns an explicit empty projection without creating a Cart. Cart and CartItem IDs are UUIDv7. Every private lookup is scoped through the authenticated Customer and their Cart. Favorites and Cart writers first lock Customer, then Cart when applicable, ordered Product/Variant/Brand records through the Catalog reader, then affected items ordered by ID. This serializes aggregate writers before creating a first Cart; unique/FK/CHECK constraints remain the integrity backstop. Transactions retry Laravel-recognized deadlocks up to three attempts; exhausted MySQL lock timeout/deadlock maps to safe 503. No Redis locks.

Cart version is an unsigned BIGINT represented as a canonical decimal string in JSON, avoiding JavaScript precision loss. Each actual change increments it once atomically; no-op PATCH/idempotent absent DELETE leave it unchanged. Overflow rolls back the entire transaction. PATCH accepts exactly one absolute `quantity` or `variant_id`, with required string `expected_version`; stale versions return 409. Replaying a changed PATCH with its old version cannot change it twice.

POST adds or increments a selection. Repeated POSTs are separate accepted operations. Variant changes preserve source quantity; occupied destination quantities merge, source is deleted within the same transaction, and response identifies the surviving item and removed source. Wrong Product/Variant pairing, overflow and blocked destination roll back. No implicit/default Variant exists.

## Admission and retained selections

The exact Product without Variants, or explicitly selected Variant, needs at least one current strictly positive USD/SYP price. No range pricing. Available and on_request selections qualify; on_request is flagged/countable and never promised immediately purchasable. Unavailable/unpriced configurations cannot be newly added, increased or chosen as a new Variant destination. Saved invalid selections remain; reductions and removals are permitted. Catalog structure changes can invalidate a selection without choosing a replacement.

Hidden Product/Brand items return only owned item ID, quantity, null Product/Variant/pricing and a generic `selection_inaccessible` evaluation. They may be reduced/removed; increases and Variant changes conflict. No hidden IDs, availability, names, images or prices appear. GET performs no cleanup. Public visibility restores evaluation automatically.

## Currency evaluation and consistency

USD and SYP are independent. Existing brick/math BigDecimal computes unit price times quantity and whole-Cart subtotal exactly, as decimal strings. Null stays null; zero remains a configured value and never enables a free purchase. Any excluded item makes that currency's `estimated_total=null`; `priced_subtotal` may still be partial. Each currency includes complete, excluded count and one primary exclusion reason per item: inaccessible, invalid, unavailable, missing_price or zero_price, in that precedence. On-request priced lines contribute, with a separate whole-Cart request count. Empty totals are 0.00 with item_count=0, not a free purchase.

A short MySQL REPEATABLE READ transaction reads Cart/version, all items and bulk Catalog data consistently; totals cover the complete Cart independently of the page. Independent-process testing changes Catalog price/availability after the read snapshot starts and verifies old consistent results followed by new current results. This requires MySQL's verified REPEATABLE READ isolation; do not silently change it to READ COMMITTED. No persisted monetary, currency, policy or availability snapshot. The 200-selection operational limit bounds normal reads. Future privileged imports must respect it.

## Technical limits

Quantity is signed INT 1..2,147,483,647. This is a representation limit, not a commercially acceptable purchase amount; commercial limits remain UNDEFINED. `config/customer_collections.php` localizes the initial 200-selection cap, 16 KiB mutation body and 60 write/120 read requests per minute per Customer. Laravel's existing cache is used; Redis is unnecessary. Pagination is 12 by default, max 48. Limits require operational reassessment and are not production capacity evidence. Quantity overflow never clamps values.

## Frontend retry contract

Disable duplicate submissions while a request is pending. Never automatically retry uncertain Add POSTs. If outcome is unknown, GET current Cart, show the result, and retry only after user confirmation. R1 does not promise exactly-once network delivery. An optional future R2 idempotency-key design is deferred. No frontend is implemented here.

See [API](../api/cart.md), [schema](../database/favorites-cart-schema.md), [Catalog boundary](cart-catalog-integration.md) and [Checkout boundary](cart-checkout-compatibility.md).
