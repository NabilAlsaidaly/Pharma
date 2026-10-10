# Module boundaries

Identity is implemented in Phase 2. Other modules retain their empty Domain, Application,
Infrastructure and Interfaces boundaries; their responsibilities describe future ownership.

| Module | High-level responsibility |
| --- | --- |
| Identity | Implemented Google-only customer authentication through Socialite Infrastructure adapter, UUIDv7 persistence, dedicated customer guard and Sanctum sessions. See identity-and-authentication.md. |
| Catalog | Brands, products, variants, images and concerns. Brand / Company is the primary product grouping; product type may be a secondary property or filter. |
| Favorites | Favorites for authenticated customers, persisted and managed by the backend. |
| Cart | Cart for authenticated customers, persisted and managed by the backend. |
| Orders | Order and checkout boundary. Future work must address availability, current prices, price and address snapshots, transactions, idempotency, state transitions, concurrency and efficient queries. |
| Gifts | Gift-related business concerns; detailed requirements are deferred. |
| Consultations | Consultation-related business concerns; detailed requirements are deferred. |
| Content | Content-related concerns; detailed requirements are deferred. |
| Delivery | Delivery-related concerns; detailed requirements are deferred. |
| Payments | Payment-related concerns. Manual payments are the accepted baseline; workflow details are deferred. |
| Reports | Business reporting concerns; detailed requirements are deferred. |
| Notifications | Business notification concerns; channels and workflows are deferred. |
| Settings | Sumaya business settings; detailed requirements are deferred. Operational package settings belong to System Console. |

System Console is outside these modules at `packages/system-console/`. Its operational skeleton covers Health, Metrics, Backup, Cache, Queue, Scheduler, Logs, Settings, Maintenance, Actions and Support. It must never depend on Sumaya business models.
