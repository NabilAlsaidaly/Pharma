# Favorites foundation

Status: IMPLEMENTED, Phase 4, owner-approved ADR-017. Verification evidence is in the [Phase 4 report](../reports/phase-04-favorites-cart-foundation-report.md).

Favorites belongs to the Customer and references a Product, independently of its Variants. `FavoriteOperations` coordinates listing, membership, addition and removal; thin HTTP adapters translate the existing success/error envelopes. Composite persistence uses Laravel's query builder, without a synthetic Favorite UUID or an Eloquent model that cannot honestly represent its compound key.

Identity supplies the authenticated customer guard; every query includes that Customer ID. `auth:sanctum` and `EnsureActiveCustomer` protect all operations. No body/query ownership field is accepted. Product listing reuses `Product::publiclyVisible()` and the existing compact Catalog Product resource. Membership of hidden and nonexistent Products returns the same 404.

F1 preserves relationships when Product or Brand becomes hidden. Visibility filtering happens before pagination, including its total count. Reactivation restores visibility automatically. GET never deletes records. Unpriced and unavailable Products may be favorites. Removing an owned pair is idempotent, including when hidden or absent.

Addition/removal use short transactions, lock the existing Customer row first and rely on the pair primary key as final duplicate protection. Addition reads current Catalog visibility through its focused selection port under ordered locks. Duplicate additions retain the original timestamp. Reads are paginated, default 12/max 48, newest Favorite first with Product ID tie-breaking. Observed listing queries: four with both two and fifty records. This measurement includes related Brand and primary image, not production throughput evidence.

See [API](../api/favorites.md), [schema](../database/favorites-cart-schema.md) and [Catalog integration](cart-catalog-integration.md).
