# Catalog query design

IMPLEMENTED by EloquentPublicCatalogReader, measured on local MySQL 9.7 test fixtures.

| Path | Purpose / builder | Result cardinality | Observed SQL count |
| --- | --- | --- | --- |
| Brand list | active, sort_order/id, paginate | <=per_page | 2 |
| Brand detail | active, unique slug, firstOrFail | 0/1 | 1 |
| Product list | active product + EXISTS active brand; eager brand/primary image; variant aggregate subqueries; paginate | <=per_page | 4 |
| Product detail | same scope + slug; eager variants/images/active concerns | 0/1 product plus its children | 6 |
| Brand products | visible brand lookup, parent-ID filter, same product query | <=per_page | 5 |
| Concern products | visible concern lookup, EXISTS pivot/active concern, same query | <=per_page | 5 |
| Recommended | same visibility + is_recommended=true | <=per_page | 4 |
| Search | name LIKE bound literal substring, same query | <=per_page | 4 |
| Concern list | active, sort_order/id, paginate | <=per_page | 2 |

Product listing with two products and thirty products both used four queries, including
Brand/Image relations and variant price summaries. Detail count remained six after
adding ten unrelated products with children. These are fixture measurements, not
universal budgets. Tests enable lazy-loading prevention and validate SQL-side filtering
and limit; pagination defaults 12, maximum 48. Query counts include pagination COUNT.
List selects only its required product columns; long descriptions/detail relations are
not loaded into list results. There are no DB lookups in resource loops.

Actual indexes and FK/constraint definitions are in ../database/catalog-schema.md.
Visibility, newest ordering, parent filters, recommendations and ordered child reads
have query-driven composites; no redundant single-column FK indexes remain in actual
MySQL metadata. The pivot has forward PK and reverse index. Name sorting may filesort;
availability filtering has no speculative low-selectivity standalone index.

EXPLAIN FORMAT=TRADITIONAL for the filtered name/brand list on 21 fixture products:
brand slug used brands_slug_unique (const), parent identity used PRIMARY (const),
products used products_status_created_at_id_index (ref, estimated 21 rows, Using where).
Five correlated variant summaries used product_variants_product_id_sort_order_id_index
(ref; COUNT used index). A filesort was observed in the small plan. Estimates are not
measured scanned rows. Index selection varies with statistics, volume and predicates.
Explicit traditional format keeps observation compatible across MySQL versions.

Substring LIKE (including Arabic names) is not made index-efficient by an ordinary
BTREE. Wildcards are escaped with !; values remain bound. No full-text/search engine,
Redis, query cache or fabricated latency benchmark. Five correlated aggregate probes
per candidate product are bounded by indexes but can cost more as variants/offsets
grow; profile before replacing them with a grouped bulk query.
Offset pagination COUNT, large offsets, eager child collections on detail, substring
search and payload size remain scaling considerations. No arbitrary child truncation
is introduced without a business/API decision.

**CAPACITY ASSUMPTIONS NOT YET APPROVED.** Production performance is not claimed.
