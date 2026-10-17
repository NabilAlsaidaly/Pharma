# ADR-027: Simple manual delivery foundation

Status: accepted for Phase6H implementation following the explicit2026-10-17 Owner approval of [A–D and section4](../reports/phase-06h-simple-delivery-design-gate.md). ADR027 was unused at the verified baseline. Supersedes only ADR024's preparatory-only restriction on delivery Zone publication; payment preparation and PAY-001 stay unchanged.

Reuse Settings' existing typed Zone tables, immutable revisions, published pointer, exact versions, command replay, audit and archive/restore. Enable explicit Zone publication for informational discovery only. Saving never publishes; existing preparations remain private. Current draft values never substitute for the published revision. No parallel Zone table, activation flag, geography taxonomy, fee calculation or address model.

Delivery owns the small private worker use case and HTTP adapters; Settings owns the narrow PublishedDeliveryZones projection. One public paginated GET exposes published informational values, no Customer write or geography matching. Existing Checkout/Gift address contracts, snapshots, review and committed retries remain independent of Delivery readiness. No delivery method or Zone identifier is added to them.

Workers are contacts, not identities. Two small tables: current fixed contact fields and an append-only command/change journal for attribution, exact versions and permanent operation replay. This journal contains field names/fingerprints, not full historical PII snapshots or an event-sourced model. No hard deletion, assignment, driver accounts, additional mutex table or generic HR workflow. Same Owner-X then target lock order as Settings; no Customer/Catalog locks acquired.

Private native Blade forms use existing Owner/CSRF/revocation and safe error boundaries. Public Zone data excludes preparation notes and all PII. Decimal strings retain independent USD/SYP/null/zero semantics and always require manual confirmation. Current Article/Gift/Storefront publication remains separate. Tests establish local/CI behavior, not medical, phone, geographic or production-capacity certification.

Phase6I separately owns final delivery/fee snapshot, payable totals, payment/deposit verification, optional manual assignment and fulfillment states. No automatic COD exception, Order, stock allocation, shipment, notification, Next.js, Redis, System Console, deployment or development/production migration.
