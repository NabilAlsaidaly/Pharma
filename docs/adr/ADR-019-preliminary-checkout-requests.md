# ADR-019: Persistent preliminary checkout requests

Status: Accepted for Phase 5A.2 implementation, pending owner PR review.

Context: Customers need a durable contact/selection request and safe lost-response retry without an approved payment policy, staff interface, pricing quote or confirmed Order.

Decision: Introduce only checkout_intents and checkout_intent_items and exactly three owned create/list/detail endpoints. Snapshot validated contact, current authoritative Customer email, historical selection IDs, quantities and review flags. Serialize new submissions with cooperating Cart writes using Customer-first transactions and locked Cart/Catalog revalidation. Customer-scoped UUID v4/v7 operations and canonical fingerprints provide durable idempotency; replay precedes current selection eligibility. Use restrictive relational constraints, nullable-selection uniqueness, atomic bulk items, bounded history and explicit nonfinancial resources. Preserve the Cart and PAY-001.

Consequences: Requests survive selection removal and Catalog visibility changes; detail intentionally exposes historical IDs without resolving hidden Catalog metadata. Restrictive FKs retain referenced Catalog rows. Distinct operations are permitted within attempt quotas. No order confirmation, payment, inventory reservation, editing, lifecycle transitions, expiry, staff workflow or notification is authorized. Schema rollout remains a separate owner-approved action; development migrations are deliberately deferred.
