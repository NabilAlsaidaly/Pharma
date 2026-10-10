# Catalog schema

IMPLEMENTED: migration 2026_10_10_000002_create_catalog_tables, verified against development MySQL 9.7.0 metadata after forward migration. Compatible primary-image technique was preflight-tested on CI MySQL 8.4 before adoption. All tables are owned by Catalog, use utf8mb4/utf8mb4_unicode_ci, and have no dependency on Identity.

## Relationships

```text
brands.id 1 -> * products.brand_id
products.id 1 -> 0..* product_variants.product_id
products.id 1 -> 0..* product_images.product_id
products.id * <-> * concerns.id through concern_product
```

Every FK uses ON DELETE RESTRICT / ON UPDATE RESTRICT. No public deletion API or SoftDeletes workflow. Slugs are globally unique within their entity table, with the configured MySQL collation. Entity IDs are Laravel-generated UUIDv7 char(36), not auto-incrementing; pivot identity is the unique pair.

## brands

| Column | MySQL type | Nullable | Default | Generation |
| --- | --- | --- | --- | --- |
| id | char(36) | no | none / NULL for nullable | - |
| name | varchar(255) | no | none / NULL for nullable | - |
| slug | varchar(255) | no | none / NULL for nullable | - |
| logo_path | varchar(2048) | yes | none / NULL for nullable | - |
| description | text | yes | none / NULL for nullable | - |
| status | varchar(16) | no | hidden | - |
| sort_order | int unsigned | no | 0 | - |
| meta_title | varchar(255) | yes | none / NULL for nullable | - |
| meta_description | text | yes | none / NULL for nullable | - |
| created_at | timestamp | no | none / NULL for nullable | - |
| updated_at | timestamp | no | none / NULL for nullable | - |

| Index | Columns | Constraint | Query justification |
| --- | --- | --- | --- |
| brands_slug_unique | slug | UNIQUE | Visible brand detail/slug filter; globally unique brand slug |
| brands_status_sort_order_id_index | status, sort_order, id | BTREE | Visible ordered brand list |
| primary | id | PRIMARY | Entity lookup and joins |

Foreign keys: none.

Enforced CHECK constraints:
- brands_status_check: (`status` in (_utf8mb4\'active\',_utf8mb4\'hidden\')).

## products

| Column | MySQL type | Nullable | Default | Generation |
| --- | --- | --- | --- | --- |
| id | char(36) | no | none / NULL for nullable | - |
| brand_id | char(36) | no | none / NULL for nullable | - |
| name | varchar(255) | no | none / NULL for nullable | - |
| slug | varchar(255) | no | none / NULL for nullable | - |
| short_description | text | yes | none / NULL for nullable | - |
| full_description | longtext | yes | none / NULL for nullable | - |
| status | varchar(16) | no | hidden | - |
| availability_status | varchar(16) | no | unavailable | - |
| is_recommended | tinyint(1) | no | 0 | - |
| usage_instructions | text | yes | none / NULL for nullable | - |
| suitable_for | text | yes | none / NULL for nullable | - |
| benefits | text | yes | none / NULL for nullable | - |
| price_usd | decimal(18,2) | yes | none / NULL for nullable | - |
| price_syp | decimal(18,2) | yes | none / NULL for nullable | - |
| created_at | timestamp | no | none / NULL for nullable | - |
| updated_at | timestamp | no | none / NULL for nullable | - |

| Index | Columns | Constraint | Query justification |
| --- | --- | --- | --- |
| primary | id | PRIMARY | Entity lookup and joins |
| products_brand_id_status_created_at_id_index | brand_id, status, created_at, id | BTREE | Brand products, filtering and brand FK prefix |
| products_slug_unique | slug | UNIQUE | Product detail by globally unique slug |
| products_status_created_at_id_index | status, created_at, id | BTREE | Visible newest listing with stable UUID tie-breaker |
| products_status_is_recommended_created_at_id_index | status, is_recommended, created_at, id | BTREE | Visible recommended newest list |

Foreign keys:
- products_brand_id_foreign: brand_id -> brands(id); delete/update restrict/restrict.

Enforced CHECK constraints:
- products_availability_check: (`availability_status` in (_utf8mb4\'available\',_utf8mb4\'on_request\',_utf8mb4\'unavailable\')).
- products_price_syp_check: ((`price_syp` is null) or (`price_syp` >= 0)).
- products_price_usd_check: ((`price_usd` is null) or (`price_usd` >= 0)).
- products_recommended_check: (`is_recommended` in (0,1)).
- products_status_check: (`status` in (_utf8mb4\'active\',_utf8mb4\'hidden\')).

## product_variants

| Column | MySQL type | Nullable | Default | Generation |
| --- | --- | --- | --- | --- |
| id | char(36) | no | none / NULL for nullable | - |
| product_id | char(36) | no | none / NULL for nullable | - |
| name | varchar(255) | no | none / NULL for nullable | - |
| price_usd | decimal(18,2) | yes | none / NULL for nullable | - |
| price_syp | decimal(18,2) | yes | none / NULL for nullable | - |
| availability_status | varchar(16) | no | unavailable | - |
| sort_order | int unsigned | no | 0 | - |
| created_at | timestamp | no | none / NULL for nullable | - |
| updated_at | timestamp | no | none / NULL for nullable | - |

| Index | Columns | Constraint | Query justification |
| --- | --- | --- | --- |
| primary | id | PRIMARY | Entity lookup and joins |
| product_variants_product_id_sort_order_id_index | product_id, sort_order, id | BTREE | Ordered detail variants and indexed correlated price aggregates; FK prefix |

Foreign keys:
- product_variants_product_id_foreign: product_id -> products(id); delete/update restrict/restrict.

Enforced CHECK constraints:
- product_variants_availability_check: (`availability_status` in (_utf8mb4\'available\',_utf8mb4\'on_request\',_utf8mb4\'unavailable\')).
- product_variants_price_syp_check: ((`price_syp` is null) or (`price_syp` >= 0)).
- product_variants_price_usd_check: ((`price_usd` is null) or (`price_usd` >= 0)).

## product_images

| Column | MySQL type | Nullable | Default | Generation |
| --- | --- | --- | --- | --- |
| id | char(36) | no | none / NULL for nullable | - |
| product_id | char(36) | no | none / NULL for nullable | - |
| path | varchar(2048) | no | none / NULL for nullable | - |
| is_primary | tinyint(1) | no | 0 | - |
| sort_order | int unsigned | no | 0 | - |
| primary_product_id | char(36) | yes | none / NULL for nullable | {"type": "stored", "expression": "(case when (`is_primary` = 1) then `product_id` else NULL end)"} |
| created_at | timestamp | no | none / NULL for nullable | - |
| updated_at | timestamp | no | none / NULL for nullable | - |

| Index | Columns | Constraint | Query justification |
| --- | --- | --- | --- |
| primary | id | PRIMARY | Entity lookup and joins |
| product_images_primary_product_id_unique | primary_product_id | UNIQUE | At most one primary image for a product; multiple null secondary keys |
| product_images_product_id_sort_order_id_index | product_id, sort_order, id | BTREE | Ordered image loading/primary fallback; FK prefix |

Foreign keys:
- product_images_product_id_foreign: product_id -> products(id); delete/update restrict/restrict.

Enforced CHECK constraints:
- product_images_primary_check: (`is_primary` in (0,1)).

## concerns

| Column | MySQL type | Nullable | Default | Generation |
| --- | --- | --- | --- | --- |
| id | char(36) | no | none / NULL for nullable | - |
| name | varchar(255) | no | none / NULL for nullable | - |
| slug | varchar(255) | no | none / NULL for nullable | - |
| status | varchar(16) | no | hidden | - |
| sort_order | int unsigned | no | 0 | - |
| created_at | timestamp | no | none / NULL for nullable | - |
| updated_at | timestamp | no | none / NULL for nullable | - |

| Index | Columns | Constraint | Query justification |
| --- | --- | --- | --- |
| concerns_slug_unique | slug | UNIQUE | Visible concern slug lookup/filter |
| concerns_status_sort_order_id_index | status, sort_order, id | BTREE | Ordered visible concerns |
| primary | id | PRIMARY | Entity lookup and joins |

Foreign keys: none.

Enforced CHECK constraints:
- concerns_status_check: (`status` in (_utf8mb4\'active\',_utf8mb4\'hidden\')).

## concern_product

| Column | MySQL type | Nullable | Default | Generation |
| --- | --- | --- | --- | --- |
| product_id | char(36) | no | none / NULL for nullable | - |
| concern_id | char(36) | no | none / NULL for nullable | - |

| Index | Columns | Constraint | Query justification |
| --- | --- | --- | --- |
| concern_product_concern_id_product_id_index | concern_id, product_id | BTREE | Reverse concern filtering and concern FK prefix |
| primary | product_id, concern_id | PRIMARY | Unique product/concern pair; detail associations and product FK prefix |

Foreign keys:
- concern_product_concern_id_foreign: concern_id -> concerns(id); delete/update restrict/restrict.
- concern_product_product_id_foreign: product_id -> products(id); delete/update restrict/restrict.

## Pricing and primary-image invariants

DECIMAL(18,2) values are non-negative; null/zero are permitted information, never an implicit free purchase or currency conversion. Active unpriced products remain visible (C2). P1 ownership is enforced by Product/ProductVariant Eloquent save transactions locking the parent; raw SQL/bulk updates must not bypass these persistence rules. Cross-table price ownership is not falsely claimed as a CHECK constraint. No public writes exist.

product_images.primary_product_id is a STORED generated CASE expression: product_id when is_primary=1, otherwise null. Its unique index enforces at most one explicit primary under concurrent writes. Booleans have 0/1 CHECK constraints. Primary lookup favors is_primary, then minimum sort_order/id; no primary selects the first ordered image, no metadata returns null.

No quantity, stock/reservation, payment or checkout field/table is present. Deletion workflow and optional future stock location remain deferred.
