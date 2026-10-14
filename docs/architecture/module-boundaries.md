# Module boundaries

Identity, Catalog, Favorites and Cart are implemented through Phase 4; Orders adds Phase 5A/5A.2, ManagementIdentity adds Phase 6A and ManagementDashboard adds Phase 6B. Other modules retain their empty Domain, Application,
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
| Gifts | Gift-related business concerns; detailed requirements are deferred. |
| Consultations | Consultation-related business concerns; detailed requirements are deferred. |
| Content | Content-related concerns; detailed requirements are deferred. |
| Delivery | Delivery-related concerns; detailed requirements are deferred. |
| Payments | Payment-related concerns. Manual payments are the accepted baseline; workflow details are deferred. |
| Reports | Business reporting concerns; detailed requirements are deferred. |
| Notifications | Business notification concerns; channels and workflows are deferred. |
| Settings | Sumaya business settings; detailed requirements are deferred. Operational package settings belong to System Console. |

System Console is outside these modules at `packages/system-console/`. Its operational skeleton covers Health, Metrics, Backup, Cache, Queue, Scheduler, Logs, Settings, Maintenance, Actions and Support. It must never depend on Sumaya business models.
