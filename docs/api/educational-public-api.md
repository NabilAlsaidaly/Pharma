# Educational public REST contract — Phase6G

Laravel owns publication/validation/visibility. These additive public GET routes use `/api/v1/education`. No Customer session required, no public writes, no CORS/auth changes or Next.js implementation. Current database schema must exist; missing schema503 rather than misleading empty content. Requests carry the existing X-Request-ID and Arabic error envelope; success/error/binary responses are no-store/private.

| Method/path | Query | Result |
| --- | --- | --- |
| GET articles | page,per_page,category,concern,featured,q | Exact published summary collection |
| GET articles/{slug} | none | Exact current published Article detail, unknown/unpublished/archive404 |
| GET categories | page,per_page | Current active/unarchived editorial categories, no fabricated Article counts |
| GET categories/{slug}/articles | Article filters except category | Same summary collection scoped to current visible category; unknown/hidden/archive404 |
| GET media/{uuid}.png | none | image/png only for current published Article revision membership; known draft/replaced/archive/unpublished404 |

Article/category slugs permanent lowercase ASCII alphanumeric segments separated by hyphens, max80. Concern slug follows existing Catalog (up to255). Media UUID server-owned. Unknown query keys422. page integer1..1000, per_page1..48 default12. featured0/1 only. category/concern resolve current visible entities or404; they do not expose hidden metadata. q trim; empty=no filter, otherwise2..100 Unicode chars. Literal escaped substring of published title/excerpt only; %/_ are literal, no body/semantic/clinical search. No sort parameter: sort_orderASC,published_atDESC,ArticleUUIDASC. GET body empty or the existing tolerated empty JSON object/array only, bounded16KiB; malformed/nonempty422, oversized413. No new public read rate quota beyond existing application/proxy behavior.

Paged response is standard JsonResource pagination: successtrue, Arabicmessage,data array,links(first/last/prev/next),meta(current_page/from/last_page/links/path/per_page/to/total). Empty collectiondata[]/total0. Summary fields:

| Field | Type/meaning |
| --- | --- |
| id,slug | string aggregate UUID/permanent identity |
| revision | unsigned decimal **string**, never parse as JavaScript number |
| title,excerpt | title string≤160, nullable excerpt≤500, no body in summary |
| category | null, or {id,slug,name,description}; label/description from Article publication, live visible-category gate |
| cover | null or {id,url,alt,width,height}; current publication only |
| is_featured,sort_order | boolean / integer0..999999999, published snapshot |
| published_at | server SQL timestamp of this explicit publication revision, `YYYY-MM-DD HH:MM:SS` in UTC (existing config/app.php timezone); no client-entered/backdated publication date |

Detail envelope: `{success:true,message:"المقال المنشور.",data:{...}}`. All summary fields plus body_format=`structured_blocks`,body,nullable seo_title≤160/meta_description≤300,related_products[],related_concerns[],sources[]. Products contain id/name/slug/availability_status from currently visible Catalog (including unavailable/on_request), no price/Variant/image/clinical claim. Concerns id/name/slug current visible only. Membership/order from immutable publication; current entity rename may update linked names/slugs. Hidden/archived Product/Brand/Concern omitted completely without fallback. Article remains published. Sources contain title,url,note(nullable),sort_order, in saved order; accepting HTTPS syntax is not proof of source credibility. No Owner ID, review credential/clinical certification, internal action/fingerprint, draft version, path or raw model output.

## Structured body schema1

`body = {schema_version:1,blocks:[...]}`. Schema version is separate from Article revision. Stored image media_id is resolved by Laravel to a safe image descriptor in the response; Next.js must not pick images from any other revision or infer permissions.

```json
{
  "schema_version": 1,
  "blocks": [
    {"type": "heading", "level": 2, "runs": [{"text": "عنوان فرعي اختباري", "bold": false, "italic": false, "href": null}]},
    {"type": "paragraph", "runs": [{"text": "نص تختاره المالكة", "bold": true, "italic": false, "href": null}]},
    {"type": "list", "style": "ordered", "items": [
      [{"text": "عنصر أول", "bold": false, "italic": true, "href": null}],
      [{"text": "مرجع اختباري", "bold": false, "italic": false, "href": "https://example.org/reference"}]
    ]},
    {"type": "image", "image": {
      "id": "01999999-0000-7000-8000-000000000001",
      "url": "https://backend.example.test/api/v1/education/media/01999999-0000-7000-8000-000000000001.png",
      "alt": "وصف تكتبه المالكة", "width": 480, "height": 320
    }, "caption": null}
  ]
}
```

Example values are synthetic documentation only, not seeded content or real sources. paragraph has runs; heading level2/3 only; list styleordered/unordered, items array of runs, no nesting/custom start. Each run text+strictbooleanbold/italic+nullableHTTPS href. No HTML/attributes/styles/Markdown. Image is a fixed block with safe descriptor and nullable caption≤500. Images optional; up to5inline plus separate optionalcover, alt mandatory≤250. Text total≤40000 (including captions),100blocks,50items/list,20runs/region; canonical stored document192KiB. No full body/internal images in list summaries.

Future Next.js displays fixed components p,h2/h3,ul/ol/li,figure/img/figcaption,strong/em/a using escaped React text. Preserve paragraph line breaks; no innerHTML/dangerouslySetInnerHTML/Markdown parser or generic props spreading. HTTPS links use safe attributes if opening new windows; no remote preview is required. Unsupported schema must produce a clear rendering error, not guessed formatting. Laravel owns all business/medical-review/visibility decisions; body is not a source of purchase logic. This focused contract is not the deferred consolidated frontend handoff.

Errors follow existing `{success:false,error:{code,message,fields:{}},request_id}`;403/404/405/413/422/429/503/500 are sanitized as applicable. No clinical/factual guarantee is encoded by a successful response. Detail uses a coherent RR read snapshot; later Catalog hide or unpublication cannot retract already admitted bytes. Media URL knowledge alone does not authorize a read; public binary endpoint rechecks current membership.
