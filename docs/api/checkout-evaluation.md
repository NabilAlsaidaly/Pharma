# Checkout Evaluation API v1

`POST /api/v1/checkout/evaluations` evaluates the active authenticated Customer's stored Cart. Google/Sanctum **session-only** authentication, stateful CSRF and existing exact-origin CORS apply. Public Catalog browsing remains public. This creates no Cart, Checkout Intent, Order, payment or reservation.

JSON body only, with exactly two required fields; no query parameters:

```json
{"currency":"USD","expected_cart_version":"3"}
```

`currency` must be exact USD or SYP. Version must be a canonical decimal **string**, 0..18446744073709551615; JSON numbers, whitespace, leading zeros, signs and scientific notation fail422. Wire values are validated without silently trimming them. Unknown body/query fields fail422, including Customer/Cart IDs, prices, quantities, financial totals, payment/reservation flags and staff fields. Non-object/non-JSON bodies fail422. Customer identity comes only from the session.

Successful non-binding example (Cart/Item IDs are illustrative):

```json
{
  "success": true,
  "message": "تم تقييم السلة مبدئياً.",
  "data": {
    "currency": "USD",
    "cart": {"id": "019a0123-4567-7000-8000-000000000001", "version": "3", "item_count": 1},
    "selection_status": "requires_review",
    "items": [{"cart_item_id": "019a0123-4567-7000-8000-000000000002", "quantity": 2,
      "unit_price": "5.00", "line_estimate": "10.00", "reasons": [], "requires_fulfillment_review": true}],
    "pricing": {"priced_subtotal": "10.00", "estimated_items_subtotal": "10.00", "complete": true,
      "excluded_item_count": 0, "exclusion_reasons": {}},
    "blockers": [],
    "requires_fulfillment_review_count": 1,
    "commercial": {"payable_total": null, "required_advance": null, "delivery_fee": null,
      "quote_issued": false, "payment_instructions_available": false,
      "can_confirm_order": false, "reserves_inventory": false}
  }
}
```

`blocked`: empty Cart or at least one excluded selection. `requires_review`: all estimates complete, at least one valid-priced on_request selection. `valid`: nonempty, all selections visible/structurally valid/available/positively priced in chosen currency. **Valid is merchandise evaluation only; every commercial boolean remains false and every commercial amount null.** Price-only subtotal never implies free delivery or a payable obligation.

Each excluded item has exactly one primary reason in precedence: selection_inaccessible, selection_invalid, selection_unavailable, missing_price, zero_price. Blocked items have null unit/line estimates, never fallback prices. Global blockers are unique primary reasons in deterministic item order; empty Cart includes empty_cart. On_request uses its dedicated review boolean and can contribute to merchandise estimates. Review count includes only otherwise evaluable on_request items.

Independent USD/SYP prices use exact decimal strings, no conversion. Simple Product owns price; a variant Product needs the saved exact belonging Variant; no parent fallback/default/range. Hidden Product/Brand exposes only owned CartItem ID/quantity, generic reason, null prices and false review indicator: no Product/Variant/name/slug/image/availability metadata. Raw models are never returned.

Partial `priced_subtotal` sums valid selected-currency lines only; `estimated_items_subtotal` is null if any exclusion. `complete` means merchandise arithmetic completeness, not confirmation eligibility. An empty Cart has subtotal/complete estimate0.00, complete=true, but status blocked. Never-created Cart returns id=null/version="0"; persistent empty Cart retains its real ID/version. Neither is created or cleaned up by evaluation.

All items are returned without pagination, ordered created_at DESC then UUID ASC, within configured200-selection technical cap. A bounded201-row probe detects excess and returns409 `cart_evaluation_capacity_exceeded`, without a partial success. Cap is not a commercial purchase quantity limit. Version mismatch against the same coherent snapshot returns409 `stale_cart_version`, in `error.fields.selection`. No data is mutated on success or failure.

Errors retain safe envelope/request-ID: Guest/bearer-only401, suspended403, CSRF419, validation422, stale/cap409, oversized413, throttled429 with Retry-After; MySQL wait/deadlock503 follows existing conventions. Body limit16KiB. Existing shared personal-collections **POST/write** quota60/min/Customer applies, despite logical read-only behavior; read quota120/min remains unchanged. Framework sessions/CSRF/rate counters may perform operational work; no business writes.

Future frontend: fetch Cart version, choose exact currency, evaluate, display current nonbinding estimate/reasons. A later Cart or Catalog commit can make this response stale. Future purchase submission must independently revalidate and atomically bind its source version; it must never trust this evaluation as a quote, payment instruction, proof of funds or physical stock guarantee. No contact/address/method/deposit/dispatch input is accepted yet.
