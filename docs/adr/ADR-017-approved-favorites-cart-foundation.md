# ADR-017: Customer Favorites and Cart

Accepted by explicit owner authorization in phase4.1.md on 2026-10-11.

D1 F1/H2 retains relationships: hidden Favorites excluded from list/count/membership;
hidden Cart items expose only owned item identity/quantity and a generic placeholder.
D2 U1/A1 admits exact positive-price available/on_request selections, never unavailable;
retained blocked items permit reduction/removal, no automatic GET cleanup.
D3 CART-C2 independently estimates USD/SYP; incomplete totals null with exclusion counts.
D4 M1 merges Variant collisions preserving summed quantity; R1 POST intentionally
increments without retry deduplication, PATCH absolute/exclusive selection plus version.
D5 L1 one lazy persistent Cart/customer, Favorites composite identity, UUIDv7 Cart/Item.
O1 authorizes only additive Catalog UNIQUE(id,product_id) for composite ownership FK;
generated normalized selection key must pass real 8.4/9.7 tests, including null Variants.
D6 Q1 technical bounds: signed INT 1..2147483647, 200 selections, 16KiB body,
page default12/max48, per-customer mutations60/read120 per minute. These are initial
operational settings, not commercial purchase limits or production capacity evidence.

Database constraints plus short Customer/Cart locks and optimistic Cart versioning
protect writes. Existing Identity, P1/C2/V1/S1 and PAY-001 preserved. No stock,
reservation, Order, Payment, Checkout, inquiry workflow, management, frontend or
System Console implementation. Uncertain POST must be read/reconciled before a
user-confirmed retry; no exactly-once claim. Future commercial maximum remains undefined.
