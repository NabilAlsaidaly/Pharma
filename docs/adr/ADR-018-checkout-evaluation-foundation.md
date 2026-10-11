# ADR-018: Non-binding Checkout evaluation foundation

## Status

Accepted **only for Phase5A evaluation-only scope**, explicitly authorized by owner phase5.2.md. Number018 verified available on starting main6ff053a. Full Phase5 design and operational commercial decisions are not accepted by this ADR.

## Decision

Implement exactly one active Customer session-authenticated POST /api/v1/checkout/evaluations. Accept exact USD/SYP plus canonical unsigned-BIGINT Cart version strings and reject every other body/query field. Reuse existing auth/CSRF/body/throttle contracts and Orders -> Cart Application -> CatalogSelectionReader/CartEvaluator boundaries.

Cart exposes a complete bounded non-locking REPEATABLE READ snapshot, unchanged GET/pagination and writes. Selected-currency exact decimal estimates/current eligibility are non-binding; hidden metadata stays protected, on_request requires review, empty Cart blocked, stale version/cap overflow conflict. No lazy Cart creation or cleanup.

All commercial amounts null/flags false. No persistent Intent, Order, payment, staff, media upload, stock/reservation, migration, Redis, provider integration or frontend. Future purchase submission must revalidate and atomically bind current source/version; this estimate cannot authorize payment or fulfillment.

## Consequences

PAY-001 remains authoritative: verified required real funds plus final fulfillment before confirmed reservation, real stock allocation only if separately implemented. Method policies/rounding/recipient currencies/staff authority/refunds/delivery/inventory/Cart confirmation treatment remain future approvals. The historical design-gate fixed/no-advance clarification is preserved, not silently applied as payable runtime logic. Evaluation uses existing write-rate quota even though it performs no business writes; scaling review is separate.
