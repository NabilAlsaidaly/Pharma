# Phase 6D review workflow design gate

Date: 2026-10-16 (Asia/Damascus). Base main f0a1690549b1b7a516182ae912afd9525fb53762; PR7 merged. Existing untracked storage/framework/lsp-74da1b7d667d6302.php is preserved and excluded from delivery.

## Explicit Owner decision — ACCEPTED

The Owner answered **«أعتمد السياسة كاملة كما وردت»** to the consolidated workflow question in this session before persisted status-changing implementation. Approval covers exactly the following matrix and operational restrictions.

| Current | Allowed next states | Meaning |
| --- | --- | --- |
| new / جديد | under_review, awaiting_contact | No review has begun; a missing review row is inferred new |
| under_review / قيد المراجعة | awaiting_contact, reviewed | Active administrative examination |
| awaiting_contact / يحتاج إلى تواصل | under_review | Manual clarification/follow-up required |
| reviewed / تمت المراجعة | under_review, with mandatory reason | Administrative examination complete; reopening is explicit |

All other transitions, including same-state new operations, are rejected. Exact committed operation retries replay before version validation. Completion and reopening are individual only. Bulk cap100: only eligible transitions into under_review or awaiting_contact; no completion/reopening, no implicit skipping or partial success. Preview captures explicit IDs/versions and does not include unseen new requests at execution. Any changed selected version/state/filter membership rejects the whole group.

Notes and manually documented follow-up are private append-only Owner-attributed entries. Follow-up contains Owner-entered text only; no channel/outcome fields or external messages, no provider delivery claim. Notes do not imply review started or completed. GET never creates a review row or false timestamps. Completing review does not confirm an Order or payment.

## Source/design findings

Original Orders submission locks Customer→Cart→Catalog→CartItems, then inserts immutable checkout_intents/items; committed Customer idempotency is checked before new eligibility. Review must not acquire Customer/Cart/Catalog locks or modify submission facts. Existing owner.requests.index/show are extended, not duplicated. Contact snapshots stay exclusively on protected detail; identifier search remains bounded and PII-free.

New one-to-one review aggregate (unique request FK) and append-only explicit event stream belong to Orders. Serialize writers by original Checkout Intent ID, then review, then events; original header is locked but never saved. Sorted request IDs for bulk. Version0 means absent aggregate; first genuine write creates it and advances to1. Each action increments version, including notes. Owner-scoped operation UUID binds request/kind/target/note/expected version; identical retry returns committed event, changed payload409. Original Customer operation UUID is separate.

Review status is separate from submitted-only Checkout status. First state action sets review_started_at, reviewed sets review_completed_at, reopening clears current completion timestamp while history retains the prior event. Last activity includes all actual review events; last reviewer identifies a state-changing Owner. No historical backfill or notification. Event bodies are private, never copied into generic logs or Customer JSON.

Technical bounds: note/reopening reason2000Unicode characters, body existing16KiB, pagination25 timeline/10|25|50 list, page<=1000, dates paired<=93days with configured-zone half-open UTC boundaries, bulk100/one10minute session preview, throttled authenticated writes. Restrictive FKs and database uniqueness enforce one aggregate/operation, no cascading deletion.

Before behavioral activation: verify actual physical test DB and preservation hashes; rerun baseline. Additive migration only in test DB. Owner-global date index must be justified by actual EXPLAIN; no historical migration edits. Read-side schema absence yields unavailable/503 rather than false review counts. No unresolved commercial policy blocks this accepted administrative scope. PAY001, immutable Customer APIs, Catalog/Cart rules, Redis/System Console and payment/stock/notification boundaries remain unchanged.
