# Educational Content additive SQL schema

Migration `2026_10_17_000002_create_educational_content.php` is new; historical migrations unchanged. Never applied to development/production by this task. MySQL9.7 local /8.4 CI are the verification targets; SQLite is not evidence. Restrictive FKs and normal SQL transactions, no cascading history deletion.

| Table | Authority/integrity |
| --- | --- |
| educational_owner_locks | Owner FK/PK coordination row; created only by mutation; X upsert serializes editorial commands while Owner uses compatible S revalidation |
| educational_articles | UUIDv7, globally unique permanent slug80, positive unsigned lock_version, nullable same-Article published composite FK, archive/timestamps, publication lookup index |
| educational_article_revisions | Article/Owner FKs, unique(article_id,version), unique(id,article_id), action, title160/excerpt500/body_document JSON, optional category FK+snapshot160/500, SEO160/300, scope enum, featured/order, explicit review/health acknowledgments,time |
| educational_categories | UUIDv7, permanent slug80 unique, name160/description500, active/order/version/archive/timestamps, visible listing index |
| educational_category_revisions | Category/Owner FKs, unique(category_id,version), typed immutable state/action/time |
| educational_revision_products | revision/Product FKs, unique(revision,Product), order/reverse indexes |
| educational_revision_concerns | revision/Concern FKs, unique(revision,Concern), order/reverse indexes |
| educational_revision_sources | revision FK,title200,HTTPS URL2048,canonical URL SHA256 unique within revision,note500,order/index |
| educational_media | immutable media UUID+Article FK,unique(id,article_id),digest,width/height,time; path is server-derived education/{id}.png, never client data |
| educational_revision_media | composite revision/Article and media/Article FKs,unique(revision,media),unique(revision,slot),role/alt250; cover slot0 or inline slots1..5, reverse media-visibility index |
| educational_content_events | Owner FK,unique(Owner,operation),typed target/action,exact previous/new versions,field-name-only JSON,fingerprint,request correlation,time,target/global history indexes |

CHECKs: positive aggregate/revision versions, event version advancement, order≤999999999, media dimensions1..4096 and pixels≤4194304, explicit cover/inline slot-role rule (non-null slot), JSON object containing integer schema1 and array blocks≤100. JSON checks cover coarse SQL shape; Application validation enforces full block/run/URL/text semantics and atomically matches image IDs with relational revision membership. JSON cannot itself have a direct media FK; it is never a binary access authority. Application also bounds relation/source counts and byte lengths. No claim every raw DBA edit is prevented by SQL alone.

Nullable category/scope/SEO/excerpt are intentional draft/optional states, not default business content. At most one cover plus five internal memberships follows unique bounded slots. Different Article/history images cannot be attached by supplying an arbitrary UUID; commands permit current draft image IDs only, and composite SQL ownership remains a backstop. Sources and Catalog memberships belong to revisions, not shared mutable pivots. Body JSON is the narrow versioned presentation exception, not an EAV or universal CMS entity.

down drops the published pointer FK before reverse dependency-order tables. It destroys educational history and is not an automatic operational recovery procedure. Migration/down are tested only behind physical test guards. Future rollout requires backup and authorized outstanding migrations in order; coordinated source/schema activation is separate.
