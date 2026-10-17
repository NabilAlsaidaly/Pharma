# Owner Delivery web forms

Native Arabic RTL Blade inside existing configurable private Owner prefix, existing web/CSRF/current active Owner/credential/idle/revocation middleware and no-store/private. Five new definitions extend63to68; one navigation destination extends15to16. No new authentication system, admin alias, Customer write or driver account.

| Method/path under prefix | Route name | Behavior |
| --- | --- | --- |
| GET delivery | owner.delivery.index |25/page directory, Zone-management/history/create links |
| GET delivery/workers/create | owner.delivery.create | Fixed new contact form |
| GET delivery/workers/{uuid} | owner.delivery.edit | Current private contact/version, scoped history and state action |
| POST delivery/workers/{uuid?} | owner.delivery.mutate | save/disable/enable only |
| GET delivery/history | owner.delivery.history |25/page change journal, optional worker UUID filter |

Directory query only page1..1000,q<=100(literal substring of name/phone; empty=no filter),active empty/0/1; order nameASC/idASC. History only page and optional existing worker UUID; unknown worker404. Edit/create no query. No arbitrary SQL field, unbounded list or entire history hydration. History contains attributed action/time/versions/changed field names; not old contact contents.

POST16KiB,60attempts/minute/current Owner, query forbidden. Fixed envelope: operation_id lowercase UUIDv4/v7, expected_version canonical unsigned decimal STRING below18446744073709551615, action save/disable/enable, optional values{name,phone,notes,is_active}, confirm required accepted for state actions and prohibited for save. Values forbidden for state actions. Unknown keys reject422. New save requires name<=150,phone<=40 and explicit BOOLEAN is_active, notes<=1000 optional/null. Partial saves preserve omitted fields, blank notes clear. Plain UTF-8 text, no controls/HTML; phone uses existing Checkout syntax with international digits/optional plus/ordinary separators/minimum3digits. Format is not verification. Name/phone are not unique. HTML select0/1 is narrowly converted to typed boolean at the form adapter; command layer remains strict.

Idempotency scope Owner+operation, permanent SQL uniqueness and canonical fingerprint over normalized target/version/action/values. Identical committed retry after current Owner validation returns original worker ID before stale checks; different payload409. New success advances version and journal atomically, including state changes; same-state enable/disable409. No hard delete or historical replay of old contact values. ReadMAX version supported; cannot advanceMAX. Guest/Customer redirects existing login; revoked403, native CSRF419, unknown404, stale409, validation422 without old-input flash, body413, quota429/Retry-After, missing schema503 and sanitized500.

Zone CRUD remains existing owner.settings routes, not duplicated here. Only zones now permits explicit publish/unpublish; payment still rejects both. Native consent describes informational public area/fees and keeps conditions private. Saving alone never exposes a Zone. Archive retains identity/history and restore remains unpublished. No POST assigns a worker, contacts anyone, creates a shipment or changes financial totals.
