# Owner business settings web forms

Server-rendered Arabic RTL Blade under existing `{prefix}`, authenticated active Owner only, native CSRF/no-store and current idle/revocation boundary. Separate Settings and Content controllers/services, not public REST writes. Eleven new definitions extend29to40; route names preserve all existing destinations.

| Method/path | Name | Purpose |
| --- | --- | --- |
| GET store-settings | owner.settings.index | Actual readiness counts and grouped navigation |
| GET store-media/{id} | owner.settings.media | Owner-only binary preview from private local disk |
| GET store-settings/history | owner.settings.history |25/page attributed append-only changes/publication |
| GET store-settings/{group} | owner.settings.group | profile/currency/payment edit or zones25/page |
| GET store-settings/zones/create | owner.settings.create | New preparatory geographic record |
| GET store-settings/zones/{id} | owner.settings.edit | Current draft/revision preview |
| POST store-settings/{group}/{id?} | owner.settings.mutate | Typed Settings action |
| GET store-content/{type} | owner.content.index | banners/pages/faqs/policies bounded summaries/gallery |
| GET store-content/{type}/create | owner.content.create | Empty content form, no seeded business text |
| GET store-content/{type}/{id} | owner.content.edit | Current draft/private snapshot preview/history |
| POST store-content/{type}/{id?} | owner.content.mutate | Typed Content action |

UUID constraint on optional/existing IDs, allowlisted route types only. Singleton forms resolve the authoritative one row; no GET creates it. Query only page1..1000 and optional positive unsigned revision for private detail; unknown query422. Lists/history25; no whole historical loading. Private detail uses one RR SQL transaction across draft/history/selected snapshot. Revision attribution joins current Owner display name without credentials.

Mutation fields: operation_id UUID, expected_version canonical unsigned string below BIGINT max, action save/media/publish/unpublish/archive/restore; save values{} contains only fixed entity fields. Partial save preserves omissions, optional blank clears; complete minimum fields required. Publish/unpublish/archive/restore require confirm=1 accepted, media requires validated image file and no values; no other fields including Owner ID/path/publication pointer accepted. Forms generate independent operation UUIDs; replay of same normalized payload returns original target before stale check, changed payload409. Target/version/action/values/image digest bound in fingerprint. All six actions advance revision/audit only on new successful operation.

Publication explicitly selects a new immutable snapshot; draft edits preserve prior public pointer. Ordering is edited through sort_order then separately published; archive retains UUID/revisions, restore stays unpublished. Page/policy slugs are immutable/reserved; UI disables editing them and server rejects changed slug409. Payment/zones reject publish, always preparatory, no current Customer side effect or intake switch.

Successful commands redirect with truthful Arabic feedback. Validation422/conflict409 render private business-specific safe HTML without old-input flash; missing404/body413/CSRF419/throttle429/storage503/generic500.64KiB business body,8MiB multipart with media/source5MiB. URL controls, raster bounds, text/decimal limits in [security](../security/business-settings-security.md) and [catalog](../architecture/business-settings.md). No DELETE, batch unbounded edit, purge, public draft preview or operational/secret field.
