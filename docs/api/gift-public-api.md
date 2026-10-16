# Public Gifts API v1

Additive Laravel APIs only; Next.js implementation and the final consolidated frontend handoff remain deferred. Existing success/error/request-ID conventions apply. No-store/private is deliberate live-publication behavior, including binary images; no browser-selected business rules or public mutation CRUD.

| Method/path | Query | Result |
| --- | --- | --- |
| GET /api/v1/gifts/packages | page1..1000, per_page1..48 default12, optional occasion_id UUID, optional availability available/on_request/unavailable | SQL-paginated currently eligible published packages, sort published sort_order then package UUID |
| GET /api/v1/gifts/packages/{slug} | none | Exact currently eligible published revision; unknown/draft/unpublished/archive/ineligible404 |
| GET /api/v1/gifts/occasions | page1..1000, per_page12/25/48 default25 | Active unarchived Owner-defined occasions, order/UUID; optional package selection still restricted to its associated current publication |
| GET /api/v1/gifts/media/{uuid}.png | none | PNG only when current eligible published package references it; otherwise404, even known private UUID |

Unknown query keys422; GET bodies must be empty (empty JSON object/array tolerated); oversized raw body413. Valid nonexistent occasion filter yields empty package list. Hidden occasion filter yields empty. No automatic presets, translations, currency conversion, arbitrary search/order/filter, public preview or recipient information.

Package list fields: id, slug, name, short_description, published_version decimal STRING, prices {USD:string|null,SYP:string|null}, availability (declared package state), eligibility per currency (eligible/requires_fulfillment_review/currency_price_unavailable), primary_image null or {id,url,alt_text,is_primary,sort_order}, occasions [{id,name}]. Primary is explicit if set, otherwise order/media UUID. Detail additionally description, suitability, body_format=text/plain, images[], components[{type,product_id,variant_id,name,variant_name,description,quantity,sort_order}]. Component text is saved publication text, not a live Product-name/price join; no component prices or private paths/editor/audit identifiers.

Package/component unavailable or inaccessible Catalog identity withholds the whole package/media; on_request remains visible and flags review for each positively configured currency. Physical archived Variant ownership cannot fall back to direct Product selection. Component missing individual prices does not block Gift eligibility. Gift null/zero chosen-currency price is explicit ineligibility, not a free-purchase offer. Positive price and eligible means preliminary-request eligibility only, never stock/payment confirmation.

Lists use standard Resource pagination data/links/meta plus success=true/message. Detail uses data object plus success/message. Empty lists and missing schema503 differ. Each eligibility SQL statement admits its snapshot; immutable revision children protect content consistency afterwards, but later Catalog/publication changes cannot retroactively retract an already-admitted response. Count/page may span concurrent publication; not a global point-in-time export. Consumers render text safely, never innerHTML; escaped markup cannot be used as executable layout.

Errors:422 VALIDATION_FAILED;404 RESOURCE_NOT_FOUND;413 PAYLOAD_TOO_LARGE;503 SERVICE_UNAVAILABLE for missing Gift schema or exhausted MySQL lock retries; sanitized500 INTERNAL_ERROR. Protocol headers and X-Request-ID preserved. No examples here are presented as captured production responses.
