# Catalog / future Checkout compatibility

Catalog read foundation is IMPLEMENTED. All Cart/Checkout/Payment/Order operations
below are **NOT IMPLEMENTED — FUTURE PHASE**. PAY-001 is preserved (ADR-015).

Future consumers use product UUID, optional selected variant UUID, the configured
selected-currency price, product/brand visibility and declared/effective availability.
P1 keeps zero-variant products direct and variant products explicitly selected;
variant prices never inherit. USD/SYP are independent decimal strings or null.
Visible unpriced/zero-priced records are informational. A future purchase requires
a configured valid selected-currency price >0; null/zero cannot authorize purchase.
Variant ranges are display summaries, not a quote for the selected variant.

Catalog does not reserve inventory. Cart additions do not reserve inventory.
Checkout Intent and payment proof do not reserve inventory. Required full payment
or configured deposit must be verified administratively before order confirmation.
Recheck availability then atomically confirm and allocate inventory where tracking
exists. on_request does not promise stocked inventory.

Checkout/Orders own price, currency, payment-policy and historical order snapshots;
Catalog does not add these columns. Record actual paid amount/outstanding balance
for future partial payment. Changed availability may require refund/replacement,
whose rules are deferred. Stock location, quantity tracking, concurrency and allocation
must be designed at the future allocation boundary; no Catalog stock counter or service.
