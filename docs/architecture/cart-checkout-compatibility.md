# Cart and future Checkout compatibility

Cart selection persistence and estimated current USD/SYP evaluation are IMPLEMENTED. Checkout, Order, Payment, inquiry resolution and inventory allocation are NOT IMPLEMENTED.

PAY-001 remains binding: Cart -> future Checkout Intent with historical commercial snapshots -> required full payment or deposit -> manual proof/reference -> administrative verification -> availability recheck -> atomic Order confirmation and tracked stock allocation where applicable. Proof alone does not authorize an Order. Payment-policy configuration belongs to the future Business Management Panel, not reusable System Console.

Cart stores only ownership, Product/Variant IDs, integer quantities, aggregate version and timestamps. It stores no chosen currency, payment percentage, paid amount, price snapshot, delivery/address snapshot, reservation or stock flags. Cart acceptance and on_request estimates confer no immediate purchase/stock promise. Future Checkout must independently validate current eligibility and separately approved commercial quantity maxima, selected currency, payment-policy snapshots and concurrency rules; it must not treat a Cart estimate as a historical commitment.

No checkout/order/payment endpoints, migrations, administrative screen, Redis prerequisite or frontend are added. R2 deduplication is merely an option for a future approved design, not implemented infrastructure. The owner must approve the next phase separately.
