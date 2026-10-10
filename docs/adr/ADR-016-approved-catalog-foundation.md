# ADR-016: Catalog foundation P1 / C2 / V1 / S1

## Status

Accepted by the owner in phase3.1.md. Supersedes the pending proposal in the design gate.

## Decisions

- P1: products without variants own their prices. Variants own independent prices;
  parent prices are not authoritative when variants exist. No inheritance/default variant.
  Persistence serializes ownership changes under the parent row lock and rejects conflicts.
- C2: either or both USD/SYP prices may be null even on active records. Such products
  remain publicly visible. Missing means null, never zero or conversion. DECIMAL(18,2)
  non-negative values serialize as strings/null. Zero is configured information,
  never permission for a free purchase. Future purchase needs a selected-currency
  valid price >0; purchasing/Cart/Checkout are not implemented here.
- V1: declared product and variant availability are independent. Effective selection:
  unavailable if either unavailable, else on_request if either on_request, else available.
  Products without variants use their declared state. No parent status aggregation.
- S1: Brand/Concern/Product use active/hidden. Active product with active Brand only
  is publicly visible. Hidden concerns are excluded without hiding other visible products.
- UUIDv7 entity keys; real foreign keys, unique slugs/pivot pair; restrictive deletion.
  No stock/reservation/payment schema, soft-delete workflow, type taxonomy or public writes.
- At most one explicit primary image via nullable generated unique product key,
  subject to independent MySQL 9.7 and 8.4 compatibility proof before adoption.
  No primary uses first ordered image; no images yields null, never a placeholder.
- SQL filters, bounded pagination, stable sorting, safe Resources and batched relations.
  No Redis/cache, admin authentication or frontend implementation.

PAY-001 in ADR-015 is preserved. Checkout/Orders own future snapshots, verified payment
and atomic stock allocation where tracking exists. Capacity/deletion/optional inventory
and management remain deferred.
