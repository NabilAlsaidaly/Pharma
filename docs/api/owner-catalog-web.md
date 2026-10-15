# Owner Catalog server-rendered web forms

All paths relative to existing validated OWNER_PORTAL_PREFIX. Same Owner guard/CSRF/no-store protection, no Customer/public API.

| Method/path | Name | Contract |
| --- | --- | --- |
| GET catalog/{type}/create | owner.catalog.create | products/brands/concerns/variants; new Variant requires parent UUID query |
| POST catalog/{type} | owner.catalog.store | explicit create fields; optional generated slug, safe Product defaults |
| GET catalog/{type}/{id}/edit | owner.catalog.edit | current record, bounded related Variants25/Images20, parent immutable |
| PATCH catalog/{type}/{id} | owner.catalog.update | lock_version required, omitted fields preserved, nullable empty explicitly clears |
| GET catalog/{type}/recycle | owner.catalog.recycle | five entity types, trash only,25/page, q/parent filters and pagination <=1000 |
| POST catalog/{type}/preview | owner.catalog.preview | scope ids or matching; IDs1..100 distinct; typed action; whitelisted filters; no mutation |
| POST catalog/confirm | owner.catalog.execute | server-session token UUID and confirm=1;10minute expiry; atomic group/version check |
| POST catalog/{type}/{id}/media | owner.catalog.upload | products/brands only; image raster <=5MiB + lock_version; immutable PNG |
| PATCH catalog/products/{id}/images/{image} | owner.catalog.image | same original Product; image version, sort_order0..2147483647, is_primary boolean |

Totals26 Owner route definitions (prior17 retained plus9), independently protected. No DELETE/purge, no public mutation endpoint. Variant/Image archive/restore use the same typed preview/confirmation; images cannot be arbitrary create/edit records. Recycle filters q(name/slug; image UUID), status(eligible types), brand/availability/concern(Product), parent(children), archived. All-matching uses the same semantic filters as current lists, separate from pagination/sort; server resolves entire bounded set and rejects changes at execution. Exact count/type/action/visibility/retained-selection/restore impact and target identifiers appear before confirmation.

Product fields: brand_id,name,slug,descriptions,status,availability_status,is_recommended,usage_instructions,suitable_for,benefits,price_usd,price_syp. The Arabic UI selects Brand/Concerns by name, with GET brand_q/concern_q literal name filters (max100 characters) before editing. Directories are bounded50 plus retained Concern associations; current selections survive filters. Concerns use checkbox concerns[] (max100 distinct), with _concerns_present=1 distinguishing explicit empty selection from an omitted association edit. The request adapter also accepts concern_ids multiline/comma UUIDs. Empty selection detaches pivots only. Existing archived Concern associations can be retained on edit; new archived associations rejected. Brand fields: name,slug,description,status,sort_order,meta_title,meta_description; logo only via validated media endpoint. Concern: name,slug,status,sort_order. Variant: name,price_usd,price_syp,availability_status,sort_order; parent allowed only on create, immutable later. Unknown input attributes rejected on record edits/creates. Prices string/null within DECIMAL(18,2), no floats/exponents/negative/locale conversion.

Successful writes redirect to edit/list with Arabic feedback; invalid422 preserves no submitted sensitive fields in server flash, browser Back can retain original nonfile form; stale/ownership/unique conflict409 instructs refresh/review. Missing404, CSRF419, body413, storage/GD503. Production PHP/proxy body limits and public storage serving are rollout prerequisites. Archived records stay absent from ordinary dashboard/search/detail/public reads; existing preliminary-request history remains immutable and request counts unchanged. No JSON/public business contract changed.
