# Preliminary checkout requests

These are non-reserving requests for owner review, never confirmed orders or payment proof. Authentication uses the existing active Google Customer browser session, Sanctum and CSRF. Bearer authentication is unsupported. All responses use the existing safe API error envelope and X-Request-ID header.

## Create

`POST /api/v1/checkout/intents`, JSON, maximum body 16 KiB. Exactly four top-level fields:

```json
{
  "operation_id": "0199eb46-10a0-7000-8000-000000000001",
  "currency": "USD",
  "expected_cart_version": "1",
  "contact": {
    "full_name": "سمية أحمد",
    "phone": "+44 (123) 456-789",
    "governorate": "دمشق",
    "city": "دمشق",
    "address": "شارع التجربة 12",
    "notes": null
  }
}
```

operation_id is a client-generated UUID v4 or v7; uppercase hex normalizes to lowercase. Currency is exactly USD or SYP. Version is a canonical unsigned BIGINT decimal **string**, including zero, without surrounding whitespace. Contact text supports Unicode and surrounding whitespace normalization. Limits in characters: name 150, phone 40, governorate/city 120, address 500, notes 1000. Notes may be omitted or null. Required text contains a letter or number. Phone accepts international digits, an optional leading plus and ordinary separators, with at least three digits. Controls, markup and unknown fields are rejected. Geography is free text. Email cannot be supplied; it snapshots the current authoritative Customer email inside the transaction.

201 creates a request; an identical normalized retry returns 200 with the original ID and timestamp. Same operation with changed payload returns 409. Scope is Customer + operation_id. Changed email, Cart or Catalog after commit does not invalidate an identical retry. Different operations may create further requests, subject to attempt quotas.

Summary `data`: id, status=`submitted`, currency, item_count, requires_owner_review=true, payment_required_now=null, payment_instructions_available=false, is_confirmed_order=false, reserves_inventory=false, created_at. Contact and items are omitted from POST and list summaries. POST quota: 10 attempts/hour/Customer and 100/hour/IP, including rejected attempts and retries; existing personal read quotas apply to GET.

## History

`GET /api/v1/checkout/intents?page=1&per_page=12`: Customer-scoped summary data, meta and pagination links. Maximum per_page=48. Stable created_at descending, UUID ascending. No request body or other filters accepted.

`GET /api/v1/checkout/intents/{id}`: owned detail includes contact snapshot, source_cart_version and ordered items containing historical id, source_cart_item_id, product_id, nullable variant_id, quantity, line_order, requires_fulfillment_review_at_submission. No current Catalog names, prices or metadata are resolved. Hidden products remain historical identifiers only. Unknown and non-owned requests share 404. Updates/deletes/status transitions are unsupported.

409 covers empty/stale/oversized/ineligible selections, unusable authoritative email and operation payload conflict. 422 rejects invalid/forged input; 413 rejects oversized bodies; 401/403 protect identity; 419 protects CSRF; 429 includes protocol retry headers; 500/503 conceal database errors. Submission revalidates every line and requires a positive selected-currency price; on_request requires human fulfillment review. The Cart remains unchanged.

Development migrations have deliberately not been applied. These endpoints require the two new tables before they can operate; owner approval and a separate rollout are required.
