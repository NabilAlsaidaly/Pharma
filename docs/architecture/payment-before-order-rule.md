# Payment-before-order rule

PAY-001 is accepted in the owner's Phase 3 brief; see ADR-015. The flow below is
**NOT IMPLEMENTED — FUTURE PHASE**. This document adds no payment or stock feature.

```text
Cart
  -> evaluate availability and current prices
  -> Checkout Intent and snapshot required payment policy
  -> calculate full payment or required deposit
  -> manual payment instructions
  -> payment proof/reference (no reservation)
  -> administrative verification of required payment
  -> recheck availability
  -> confirm order and allocate inventory atomically, where tracking exists
```

| Owner | Consequence | Implementation |
| --- | --- | --- |
| Catalog | Supply product ID, optional variant ID, explicit currency prices, visibility and declared availability; no promise of stock allocation | Catalog design awaits approval |
| Cart | Store customer selections later; additions do not reserve inventory | NOT IMPLEMENTED — FUTURE PHASE |
| Checkout | Evaluate current price/currency and availability; snapshot payment percentage and calculated requirement in a preliminary intent | NOT IMPLEMENTED — FUTURE PHASE |
| Payments | Manual verification must establish actual paid amount before confirmation; proof alone is insufficient | NOT IMPLEMENTED — FUTURE PHASE |
| Orders | Recheck availability; atomically confirm and allocate tracked stock; retain paid amount, outstanding balance and historical snapshots | NOT IMPLEMENTED — FUTURE PHASE |
| Business Management Panel | Configure full-payment/deposit policy and verify payment | NOT IMPLEMENTED — FUTURE PHASE |
| System Console | Operational infrastructure only; no commercial policy ownership | NOT IMPLEMENTED — FUTURE PHASE |

Full payment means 100%; a deposit percentage is >0 and <100. Customers cannot edit
the requirement. Availability changes before verification must not result in promises
of unavailable stock. Refund/replacement may be required; those business rules await
their own phase. Catalog availability, including on_request, is descriptive.

Earlier conflicting SRS sequencing is superseded by PAY-001. The repository contains
no discoverable SRS/PDF; no claim is made to have inspected it. Formal source SRS
revision is future work. No checkout/payment tables, inventory reservations or stock
deduction are introduced by Phase 3.
