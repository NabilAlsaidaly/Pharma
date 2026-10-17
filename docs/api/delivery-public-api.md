# Informational delivery Zones API

`GET /api/v1/delivery/zones` is the only new public Delivery endpoint. Guest browsing allowed. No public workers, writes, selection, address verification, quotation or dispatch endpoint. A missing entry does not deny delivery or preliminary request admission. No national coverage or special Hama restriction is inferred.

Only query page(integer1..1000) and per_page(integer1..48,default12); all other keys422, including governorate/city/id/Zone selectors. No geographic matching or search. Stable published sort_orderASC then UUIDASC. Empty current publication yields data[] and normal pagination. Missing Settings Zone/revision/audit schema503. GET/HEAD body at most16KiB, empty or tolerated empty JSON{} /[] only; nonempty422, oversized413. No new public read quota beyond existing server/proxy controls.

Standard success/message/data/links/meta and X-Request-ID; success and errors no-store/private.405 for writes, safe422/413/503/500 envelopes. Internal SQL/paths/bindings never returned or logged as request content.

Each data item has exactly: id UUID, revision unsigned decimal STRING, name, governorate, city STRING|null, fee_usd and fee_syp each DECIMAL(18,2) STRING|null, fees_are_informational=true, manual_confirmation_required=true. No conditions, Owner/worker IDs/phones, operation fingerprint, draft values, archived metadata or Customer information.

Synthetic example of one item (not a seeded service area or fee):

```json
{"id":"019a0123-4567-7000-8000-000000000001","revision":"2","name":"منطقة اختباريّة","governorate":"محافظة اختباريّة","city":null,"fee_usd":null,"fee_syp":"0.00","fees_are_informational":true,"manual_confirmation_required":true}
```

null means fee not configured/to be confirmed, never free.0.00 means explicit prepared free according to that publication; a positive string is informational and still needs manual confirmation. No conversion, shipping-inclusive total, payment obligation/deposit or guaranteed coverage. Both fees may be null. Governorate-only and city entries coexist independently, no priority/fallback or text equality certification.

All displayed values come from the same exact published revision; a draft edit preserves the old public snapshot, republish changes it atomically, unpublish/archive removes it, restore remains private. An already-admitted read may return its coherent prior transaction snapshot; no promise to recall a response already sent. No current fee is inserted into Cart/Checkout/Gift snapshots. See [future6I boundary](../architecture/delivery-orders-integration-boundary.md).
