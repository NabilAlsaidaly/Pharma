# Simple delivery schema

Additive migration `2026_10_17_000003_create_delivery_workers.php` adds exactly two fixed tables. No historical migration edited, no second Zone table or new Zone activation column. Existing6E Zone/revision/published-pointer schema already supports the approved explicit informational publication.

| Table | Fields and guarantees |
| --- | --- |
| delivery_workers | UUIDv7 PK; name150,phone40,nullable notes1000,is_active BOOLEAN,positive unsigned lock_version,created/updated timestamps. Index(active,name,id) and(name,id). No phone/name uniqueness, password, account, assignment or hard-delete endpoint. |
| delivery_worker_changes | UUIDv7 PK; restrictive worker/Owner FKs; operationUUID/fingerprint64; enum save/disable/enable; previous/new unsigned version; narrowly scoped changed_fields JSON array; requestID100/time. UNIQUE(Owner,operation),UNIQUE(worker,new_version); timeline(worker,time,id) and global(time,id) indexes. CHECK(new>previous). No full PII snapshots. |

Worker CHECKs enforce positive version and active0/1. Database types/FKs/uniques/checks protect stored identity and basic integrity; application performs complete text/phone/UTF-8/allowlist/state/version/replay validation. No claim that arbitrary privileged raw SQL cannot edit journal/contact facts or violate all workflow rules. The journal supports durable replay/attribution, not event-sourced reconstruction or a legal retention promise.

Settings remains authoritative for Zone normalized geographic uniqueness (including archive),fees DECIMAL(18,2),private conditions and typed immutable revisions. Public readers join only exact same-Zone published revision. Unknown/null and explicit zero remain distinct. No geography matching/currency conversion/fee fallback/schema-level Order relation is introduced.

Only positively guarded `testing`+configured AND physical `sumaya_pharma_test` may be migrated/reset/rolled back by tests/workers/browser fixtures. Development/production migrations are prohibited for this delivery. Before6H, development independently verified3applied/8pending; adding this file leaves3applied/9pending if the physical baseline is unchanged. Reverify actual counts in the [implementation report](../reports/phase-06h-simple-delivery-implementation-report.md), never infer activation from source. Down drops journal before workers and destroys directory/history; it is not automatic production recovery.
