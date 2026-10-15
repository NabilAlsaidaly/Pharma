# Catalog archival and audit schema

Additive migration `2026_10_15_000001_add_catalog_archival_and_audit`; original migrations, restrictive FKs, price CHECKs and unique slug/generated primary-image indexes are unchanged. Not applied to development. down removes new audit table/columns/indexes; rollback discards audit history and is a separately controlled operational action, not a normal UI operation.

| Entity | Archival | Restore |
| --- | --- | --- |
| Product | nullable deleted_at, retain status/Brand/children/references | same UUID; existing Brand may be archived, P1 preserved |
| Brand | retain Products/statuses | possible public visibility impact explicitly confirmed |
| Concern | retain pivots/Products | same identity, no association duplication |
| Variant | retain parent/composite references; all physical children reserve pricing | original unarchived Product, null parent prices |
| Image | clear is_primary, retain file | unarchived Product and safe existing file; secondary |

Each of five tables adds unsigned BIGINT lock_version default1 and index(deleted_at,id). New write tokens bounded to16decimal digits/operational advance below10^15; no JS numeric conversion. Slugs remain unique across trash and current records; no archived slug reuse. Customer/Cart/Favorite/Checkout/identity/audit entities receive no SoftDeletes.

catalog_audit_events: UUIDv7 id, restrictive management_users Owner FK, entity_type24/entity_id UUID, action32, request_id100, nullable bulk UUID, JSON before_values/after_values, created_at. Index(entity_type,entity_id,created_at), index(bulk_id,id). Target is polymorphic typed identifier without destructive cascade. Exact decimal strings and concise explicit changed fields/association IDs; no serialized account/customer data. Append-only application interface, no editor/purge route. Trusted DBA capability is outside application enforcement; this is not a cryptographic tamper-proof ledger.

Both old generated primary constraint and CHECKs must pass isolated MySQL9.7 and CI8.4 tests; no generated-column alteration. New archival index measured by EXPLAIN, no claims about unmeasured production throughput. Full-row/SHOW CREATE/.env preservation evidence is kept in ignored local artifacts and report. Deployment must apply pending older and new migrations together only after explicit authorization/backup/target verification; no automatic rollout occurs here.
