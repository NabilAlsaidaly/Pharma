# Module boundaries

Identity, Catalog, Favorites and Cart are implemented through Phase 4; Orders adds Phase 5A/5A.2/6D, ManagementIdentity adds Phase 6A, ManagementDashboard adds Phase 6B, Settings/Content add Phase6E, and Gifts adds Phase6F. Other modules retain their empty Domain, Application,
Infrastructure and Interfaces boundaries; their responsibilities describe future ownership.

| Module | High-level responsibility |
| --- | --- |
| Identity | Implemented Google-only customer authentication through Socialite Infrastructure adapter, UUIDv7 persistence, dedicated customer guard and Sanctum sessions. See identity-and-authentication.md. |
| ManagementIdentity | Phase 6A implements the single Owner identity, interactive provisioning, separate guard, custom Blade login/workspace/profile/password/logout only. No business CRUD or System Console dependency. |
| ManagementDashboard | Phase 6B implements protected aggregate metrics, bounded global search and read-only Product/Brand/Concern/preliminary request lists/details. No writes, tracking, financial confirmation or System Console dependency. |
| Catalog | Implemented read-only Brands, Products, Variants, Image metadata and Concerns. Brand is primary grouping; prices/visibility/availability follow ADR-016. No purchase or stock operations. |
| Favorites | Implemented Product-level customer pairs, F1 hidden retention and private APIs. |
| Cart | Implemented lazy owned selections, exact current estimates, optimistic updates and atomic merges; no stock or commercial workflow. |
| Orders | Phase5A implements non-binding Checkout evaluation; Phase5A.2 persists owned preliminary contact/selection requests and historical snapshots through the Cart Application boundary. Confirmed Orders, financial/payment policy snapshots, verification and fulfillment remain deferred. |
| Gifts | Phase6F owns dynamic hybrid gift packages, independent prices, occasions, private versioned publication, preliminary immutable Gift Requests and private individual review. Catalog supplies current linked selection truth; no Cart/Checkout/payment/stock mutation. See gift-domain.md. |
| Consultations | Consultation-related business concerns; detailed requirements are deferred. |
| Content | Phase6E owns typed banner/page/FAQ/policy drafts, immutable revisions, explicit publication and safe published read APIs. No generic site builder or legal templates. |
| Delivery | Delivery-related concerns; detailed requirements are deferred. |
| Payments | Payment-related concerns. Manual payments are the accepted baseline; workflow details are deferred. |
| Reports | Business reporting concerns; detailed requirements are deferred. |
| Notifications | Business notification concerns; channels and workflows are deferred. |
| Settings | Phase6E owns typed identity/contact/social/currency presentation and preparatory payment/delivery revisions, authorized Owner changes and audit. Operational settings remain outside business UI. |

System Console is outside these modules at `packages/system-console/`. Its operational skeleton covers Health, Metrics, Backup, Cache, Queue, Scheduler, Logs, Settings, Maintenance, Actions and Support. It must never depend on Sumaya business models.

Phase 6C: Catalog/Application/Administration owns Catalog business writes/archival/media/bulk/auditing. Catalog HTTP Owner adapter uses the existing identity boundary and dashboard presentation; ManagementDashboard remains read-only. Customer identity, Cart, Checkout history and System Console ownership remain unchanged. See [Catalog administration](catalog-administration.md).

Phase 6D: Orders/Application/Review owns preliminary-request operational review, private notes/events and atomic bounded bulk actions. ManagementDashboard consumes read projections; existing Owner identity/Blade adapters provide access. Submission facts, Customer APIs, Cart/Catalog/payment/System Console remain unchanged. See [review architecture](preliminary-request-management.md).

Phase 6G: Content also owns first-class educational Articles/Categories, immutable structured-block revisions, revision-specific media/editorial relationships, explicit reviewed publication and safe read APIs. It preserves6E ContentTypes/RevisionedStore contracts. Catalog/ManagementIdentity remain authoritative; no Gifts/Checkout/Payments/Delivery/Consultations/System Console business mutation. See [educational domain](educational-content-domain.md).

Phase6H keeps Zone configuration/revisions/publication and the safe PublishedDeliveryZones adapter in Settings. Delivery owns the small private worker directory/history and public informational read adapter. Orders/Gifts own existing preliminary contact snapshots; confirmed fulfillment/payment/assignment stays6I. See [simple delivery](simple-delivery-foundation.md).
