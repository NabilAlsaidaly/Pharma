# ADR-015: Flexible payment before order confirmation (PAY-001)

## Status

Accepted by the owner in phase3.md. Workflow implementation deferred.

## Decision

The future Business Management Panel owns the commercial payment policy: either
100% payment or a configurable deposit strictly greater than 0% and less than 100%.
Customers cannot override the required percentage. Future Checkout must snapshot
the applicable policy and calculate the required amount from current evaluated prices.

No order confirmation or inventory reservation/allocation before administrative
verification of the required payment. Browsing, cart additions, Checkout Intent,
uploaded payment proof and claimed payment do not reserve products.

After verification, recheck availability and current fulfillment conditions before
confirmation. Where stock tracking exists, order confirmation and stock allocation
must occur atomically with appropriate concurrency controls. Record the actual paid
amount and outstanding balance for partial payments. Changed availability may need
a refund/replacement resolution; its operational rules are not invented here.

## Consequences and ownership

- Catalog exposes descriptions, declared availability and configured prices only.
- Cart, Checkout Intent, Payments and Orders implementation remains deferred.
- No payment settings, reservation columns, quantity counters or stock methods in Catalog.
- Commercial policy belongs to the Business Management Panel, not System Console.
- ADR-005's manual payment baseline remains accepted; PAY-001 specifies sequencing.
- Conflicting earlier SRS checkout sequencing is superseded by PAY-001. No SRS document
  was found inside this repository; its source requires a future formal revision.
  No SRS PDF is modified in this phase.

All actual payment/checkout/order workflow: **NOT IMPLEMENTED — FUTURE PHASE**.
