# Phase 6C Catalog write design gate

Date: 2026-10-15. Baseline: `a15753aa79b8bf1cde96d7e3fc11928522b776b7` (merged PR6). This gate precedes application behavior changes. CONFIRMED denotes inspected source; runtime evidence is recorded separately.

## Existing contracts and integration audit

CONFIRMED: six Catalog tables, five UUID entity models, restrictive FKs, globally reserved slugs, DECIMAL(18,2) CHECKs, generated unique primary-image key. Product save and Variant save serialize P1 on the Product row. Customer admission locks Customer/Cart, then ordered Products, Variants, Brands, then CartItems. Historical checkout detail reads IDs/contact snapshots without current Catalog joins. Favorites and Cart must retain their rows. No development checkout/identity migration is currently applied: eleven tables, one Customer, three migration rows; preservation captured read-only before baseline tests. Test setup positively checks physical sumaya_pharma_test.

Inspected ADR016/017/019/020/021, Catalog/schema/cart/checkout/dashboard/payment documentation and actual Catalog models/readers, guarded test base, Owner middleware/routes and raw management queries. Historical docs saying writes/soft deletion are deferred describe their original phase, not a conflict with this explicitly authorized milestone.

## Entity and mutation matrix

| Mutation | Tables/FKs | Invariant, locks, downstream impact and safe failure |
| --- | --- | --- |
| Product create/edit/status/prices | products -> brands; concern_product -> concerns | Explicit allowlist, hidden/unavailable defaults, decimal strings; existing save setters. Lock Product then referenced Brand/Concerns. Reject archived destination Brand/Concerns, stale version, invalid ownership or duplicate slug; all SQL/audit rolls back. Existing slug preserved unless explicitly supplied. |
| Variant create/edit/archive/restore | product_variants -> products; retained Cart/Checkout composite restrictive references | Lock original Product before child. No reassignment. Count ALL physical Variants for structural price ownership, including archived Variants. Explicitly clear parent prices before adding first child; never silently clear/copy. Archived last Variant does not convert Product to direct-price ownership. |
| Brand edit/archive/restore | brands <- products | Lock Brand only; never acquire Product/Customer/Cart locks afterwards. Preserve children/statuses. Archived Brand blocks public visibility/admission. Restore warning explains visibility impact. |
| Concern edit/archive/restore and associations | concerns <-> products through unique pivot | Preserve pivot rows on archive. Explicit association edits are atomic under Product then ordered Concern locks; new archived choices rejected. Hidden/archived Concern does not hide Product. |
| Images upload/reorder/primary/archive/restore | product_images -> products; generated unique primary_product_id | Inspect bounded raster outside transaction, generated immutable file name on public disk. Lock Product before images. Existing unique generated column retained unchanged. Archive clears is_primary before soft delete; restore as secondary, never displace primary. Deterministic existing fallback persists; retain stored bytes. Restore requires safe existing file. |
| Brand logo replacement | brands.logo_path | Same validated immutable upload. Old bytes retained; compensate only newly generated file on SQL failure. No garbage collector. |
| Bulk action | fixed eligible entity registry + audit | Cap100 synchronous records. Server preview resolves explicit IDs or whitelisted filters, exact IDs/versions/action/count held in bounded expiring session confirmation. Execution re-resolves filtered set and compares IDs/versions under deterministic locks; reject changed/oversized sets, never silently skip. Atomic Eloquent mutations + audit. |

## Soft deletion and restoration

All five Catalog entity tables receive additive nullable deleted_at plus explicit optimistic lock_version. No Customer/Cart/Favorite/Checkout/identity/audit soft deletion. No hard-delete/purge endpoint or destructive cascade. Slugs remain globally unique including trashed records. Recycle Bin is separate, bounded and authorized. Ordinary raw management queries must explicitly exclude deleted_at, including subqueries/joins; public Eloquent scopes handle entity deletion and Brand exclusion. Selection reader must retain ALL physical Variant count for structural admission while excluding archived selected Variants. Missing schema must not be interpreted as zero or trigger migrations.

Product restoration retains UUID/status and may remain non-public under archived Brand. Variant/image restore requires non-archived original Product; Variant requires parent null prices. Brand/Concern restoration preserves children/pivots. Repeated actions are safe; stale version produces an Arabic conflict, without false audit success.

## Audit, transaction and filesystem safety

Focused append-only catalog_audit_events records UUID event/Owner/target/action/request correlation/bulk correlation/concise changed before-after attributes with exact decimal strings, no customer PII/credentials. Audit and SQL changes share transaction; no audit mutation/delete HTTP endpoint. All business changes remain Catalog Application services, with thin Owner web interface. Product -> child -> Brand -> Concern ordering matches Customer admission; sort each set of IDs, no Customer/Cart locks in admin. Optimistic version under lock prevents lost browser edits independently of retry behavior. Real independent-process MySQL races must prove material overlap; sequential tests cannot establish concurrency.

Uploads: max5MiB, dimensions1..4096 each/max4,194,304 pixels (implementation resource cap tightened from the initial16,777,216 ceiling), max20 current Product images, request technical cap8MiB; validated JPEG/PNG/WebP/GIF only when runtime decode/re-encode support is available. Re-encode raster to eliminate client metadata/executable polyglots; reject executable/double names/SVG/path input. Actual support is verified before implementation. File write precedes short SQL transaction; failure compensates generated new path only. Crash between filesystem save and SQL commit can orphan new bytes; no distributed atomicity/worker claim. No existing file deletion or overwrite, including archive/logo replacement. Storage public serving/symlink is rollout prerequisite, not applied to production.

## Deferred owner decisions and scope

No policy approval is needed for conservative authorized operations above. Excluded: reparenting, referenced Variant-to-direct conversion, slug reuse, hard removal, automatic customer cleanup/publishing, inventory/payment/financial history changes, destructive media retention. If later evidence makes any required operation depend on those decisions, stop that affected path and report the smallest decision. No real Owner, .env edit, development migration, deployment, merge or Phase6D.

## Verification obligations

Retain all394 baseline cases; add meaningful validation/CSRF/authorization/stale-edit/media/archival/read-side/customer/audit/bulk tests. Demonstrate independent connections for required races including first Variant/prices, archived admission, Brand visibility, image primary contention, overlapping bulk and restore structure; report actual ordering/deadlocks. Measure13 specified paths at50/200 Products with bounded children and representative references, EXPLAIN relevant indexes. Browser375/768/1440 uses synthetic test DB/fake media only. Compare eleven-table full-row/schema/.env fingerprints after work. Exact final-head CI and one open/unmerged PR required before completion.
