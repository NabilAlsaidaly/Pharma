# Customer Gift Request API v1

Existing active Google/Sanctum Customer **stateful session** plus native CSRF and exact-origin CORS. No bearer/recipient login or Customer ID/email input. Same-site HTTPS topology from ADR014 remains required. These routes create/read immutable preliminary requests only.

| Method/path | Input | Semantics |
| --- | --- | --- |
| POST /api/v1/gifts/requests | JSON object below, no query |201 new,200 identical committed retry |
| GET /api/v1/gifts/requests | page1..1000, per_page12/25/48 default25; no nonempty body | Owned summary collection, created_at DESC/id ASC |
| GET /api/v1/gifts/requests/{uuid} | no query/nonempty body | Owned original snapshot; unknown/nonowned identical404 |

Submission fields: required operation_id UUIDv4/v7 (lowercased), package_id UUID, expected_published_version canonical unsigned decimal STRING (0..18446744073709551615; zero cannot match a published revision), currency exact USD/SYP, recipient object. Optional occasion_id UUID|null (must be currently active and associated with reviewed publication), sender_display_name<=150|null, message<=1000|null. recipient requires full_name<=150, phone<=40, governorate/city<=120 each, address<=500; notes<=1000 optional/null. Surrounding whitespace trimmed, blank optional text becomes null, Unicode/plain Arabic accepted, required text contains letters/numbers; phone supports optional leading plus/digits/ordinary separators and>=3 digits. No geography restrictions or delivery-zone fees. No HTML/controls/attachments. No quantity, composition, prices, commercial/review state, arbitrary nested fields or authoritative email accepted.

Illustrative request (not observed production data):

```json
{"operation_id":"019a0123-4567-7000-8000-000000000001","package_id":"019a0123-4567-7000-8000-000000000002","expected_published_version":"3","currency":"USD","occasion_id":null,"sender_display_name":"اسم عرض","message":"رسالة اختيارية","recipient":{"full_name":"مستلم تجريبي","phone":"+963123456789","governorate":"دمشق","city":"دمشق","address":"عنوان تجريبي","notes":null}}
```

One package/quantity1/recipient. New submission rechecks locked active Customer/account email, current published exact revision, package/linked Catalog availability and positive selected Gift currency price. On-request sets requires_fulfillment_review=true. No Cart/Checkout/stock/Order/Payment/Shipment/Notification mutation. Historical display_price_snapshot is an exact decimal string, nonbinding and not a final payable or shipping-inclusive amount; no deposit/balance is computed.

Summary (POST/list): id,status=submitted,package_name,currency,display_price_snapshot,requires_fulfillment_review BOOLEAN,created_at plus requires_owner_review=true,is_confirmed_order=false,reserves_inventory=false,payment_verified=false,delivery_scheduled=false,price_is_binding=false,shipping_included=false. No recipient, message, account email, operation fingerprint or Owner note. Owned detail adds original package_id,published_version STRING,occasion_id/name,account_email,sender_display_name,message,recipient_name/phone/governorate/city/address/notes,components[] historical typed names/IDs/quantities/order, same commercial flags. No review row/status/events/Owner identity/internal note; current Catalog is never resolved to rewrite history.

Customer+operation fingerprint excludes current account email but includes normalized submitted fields and exact requested version. After active Customer locking, matching committed replay wins before current eligibility/version/occasion/email checks, returning original ID/time/snapshot. Changed payload409; distinct operation may create another request. Authentication/suspension still denies replay. Lost-response retries consume attempt quota. Fingerprint is durable SQL, not cache-only.

Body16KiB; submission10 attempts/hour/Customer plus100/hour/IP; GET shared personal quota120/min;429 Retry-After. Unknown fields422, invalid JSON/objects422, CSRF419, Guest401, suspended403 ACCOUNT_SUSPENDED, unknown/nonowned404, stale/reused-operation/ineligible/invalid-current-occasion409 RESOURCE_CONFLICT, oversized413, missing schema/lock exhaustion503, sanitized500. Error envelopes never copy exception/SQL/path/PII, include request_id. Successful/error Gift responses no-store/private. No edit/delete/cancel/request-status mutation endpoint. Owner review completion is separate internal workflow and does not confirm a commercial order.
