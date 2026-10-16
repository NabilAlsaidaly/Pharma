# Published storefront read contract

Additive informational APIs under existing `/api/v1`, read-only GET/HEAD, existing success/error conventions. No Next.js implementation/final integrated frontend handoff. Owner mutation routes are private web forms. CORS/Sanctum configuration unchanged; no new cross-origin credential assumptions.

| GET endpoint | Data/visibility |
| --- | --- |
| storefront/configuration | data.store and data.currency_presentation, each null until explicitly published |
| storefront/media/{uuid}.png | PNG bytes only when a current published unarchived profile/banner revision references this asset; otherwise404 |
| storefront/banners | Published unarchived exact banner revisions ordered sort_order/id |
| storefront/faqs | Published unarchived exact FAQ revisions ordered sort_order/id |
| storefront/pages/{slug} | One exact published unarchived page snapshot |
| storefront/policies/{slug} | One exact published unarchived policy snapshot; supported identities privacy/terms/returns |

Revision values are canonical decimal strings, including beyond JavaScript integer precision. Success `{success:true,message:Arabic,data:...}`; collections add meta(current_page,per_page,total,last_page). Only collections accept page1..1000/per_page10,20,50(default20); all other query parameters rejected422. Item/config accept no queries. Unknown/nonexistent/unpublished item404; required schema missing503; actual DB failure sanitized500. Empty configured collections200/data[]/total0; no fake default business text/regions/amounts. Existing API error code/request_id behavior retained.

Store contains name/description/about/email/phone/address/hours (optional null), id/revision/published_at, logo_url null/string, text_format=text/plain and ordered social_links[]{platform,url,rel:noopener noreferrer}; missing platform links omitted, no empty icons. Currency projection contains first_currency USD/SYP, usd_label/syp_label, nullable symbols, id/revision/published_at. These are presentation preferences only; no prices, conversion, currency disablement, Checkout admission or financial commitments.

Banner fields title/description(null)/alt_text/link(null)/sort_order,id/revision/published_at,image_url. Known hidden/archived/missing Product/Brand/Concern destination is null, using at most three bounded batched visibility queries; no promise of a live Catalog destination. Public image_url is a Laravel storefront/media/{uuid}.png URL with publication eligibility checked on every asset request. Private bytes are not directly exposed under public storage. Owner preview uses protected store-media/{uuid}. No disk path field or per-record filesystem existence query in metadata lists. Published media existence validated on publication, operational deletion outside application remains a storage risk.

FAQ question/answer/sort_order/id/revision/published_at/answer_format=text/plain. Page title/slug/body/nullable SEO title/meta description/id/revision/published_at/body_format=text/plain. Policy title/slug/body/id/revision/published_at/body_format=text/plain. Long text bounded12000characters, FAQ answer6000, banner description500. Consumers must render as text, never HTML; URL links require safe attributes. JSON preserves escaped/plain text, not trusted HTML.

No draft/editor IDs/audit/fingerprints/operation IDs/contact snapshots/review notes/secrets/payment percentages/delivery fees serialize. Each result joins its exact publication pointer, so editing draft does not leak text/image/order. Archive/unpublish immediately remove eligibility; restore does not republish. Slugs remain reserved/permanent. Collection count/data can span concurrent publication; individual records remain committed valid snapshots. Configuration reads profile/currency in one RR snapshot, no cross-request global atomicity claim.

Caching: **Cache-Control:no-store** (Laravel may additionally mark private). Live SQL intentionally avoids stale publication/draft/CDN data; no Redis/shared cache, ETag or TTL implemented, no invalidation race. Revision IDs/immutable asset URLs can support a separately designed future cache contract. Browser/client rendering of prior responses is outside server invalidation. Operational schema/source rollout must be coordinated; unavailable storage never becomes invented successful defaults.
