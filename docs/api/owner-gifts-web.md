# Owner Gifts web forms

Existing configured private Owner prefix, guard/CSRF/session revocation/security headers/no-store. New13 definitions extend40→53; three navigation destinations extend9→12, under الهدايا. No admin package, new app, System Console or public Owner API. Native Arabic RTL Blade/Tailwind and small native JS only.

| Method/relative path | Route name suffix under owner.gifts. | Purpose |
| --- | --- | --- |
| GET gifts/packages | index |25/page current/latest summary including archived |
| GET gifts/packages/create | create | New draft form |
| GET gifts/packages/{id} | edit | Current draft, private preview, history25 and optional saved revision snapshot |
| POST gifts/packages/{id?} | mutate | Explicit save/media/publish/unpublish/archive/restore command |
| GET gifts/media/{id} | media | Private retained image binary preview |
| GET gifts/occasions | occasions |25/page Owner occasion list |
| GET gifts/occasions/create | occasion.create | New hidden occasion form |
| GET gifts/occasions/{id} | occasion.edit | Edit/archive/restore |
| POST gifts/occasions/{id?} | occasion.mutate | save/archive/restore |
| GET gifts/requests | requests | PII-free filtered request summaries |
| GET gifts/requests/{id} | requests.show | Private original snapshot, separate review and timeline25 |
| POST gifts/requests/{id}/review | review | Individual transition/note/follow_up |
| GET gifts/history | history | Generic attributed field-only audit25 |

Package form fields values[name,short_description,description,suitability,price_usd,price_syp,availability,sort_order]; slug only new/permanent. Components UI rows have type, selection=`ProductUUID|VariantUUID-or-empty` for Catalog or manual name/description, quantity/order canonical integer form strings. Adapter resolves selection into IDs, rejects unexpected row fields and normalizes digits before domain validation. JS adds/removes fields without raw JSON or manual UUID entry; quantities/order editable with keyboard. Empty-set marker fields distinguish explicit removal from omitted preservation. Images rows carry existing media_id, alt_text/order/primary checkbox, no new arbitrary path. At most one primary, deterministic fallback. Optional occasion_ids[] capped20.

Package GET allows page, revision canonical string, catalog_q/variant_q/occasion_q<=100 literal name filters, catalog_page/variant_page/occasion_page1..1000. Directories50 Products/page,500 current Variants/page,100 active Occasion matches/page plus retained selections, bounded independent physical-Variant query prevents truncation creating direct selection. Product pagination reaches records beyond the first50 even if names match; Variant filter/page can narrow a large Product's options. Save before search refresh to preserve unsaved form values. New inactive/archived links rejected; retained invalid links shown for correction and block publish. Private historical snapshot never populates the editable draft form.

Every mutation carries operation_id UUIDv4/v7, expected_version canonical string and fixed action; lifecycle actions require accepted confirm. Save can persist incomplete publication prerequisites; publish validates full image/component/price/occasion/current-Catalog eligibility. Native explicit confirmation checkbox plus optional JS native dialog, cancel/Escape and focus restoration; server confirmation remains authoritative if JS unavailable. Upload uses one image file and alt_text, preserves prior files/publication. Publication/reorder/image removal require separate explicit publish to affect customers.

Occasion values name/description/order/is_active0|1, version/operation and fixed action; archive/restore accepted confirm. Restore hidden. Gift review body operation_id,expected_version,kind transition|note|follow_up,target approved enum only for transition, plain note<=2000 required on note/follow-up/reopen. Approved ReviewStatus matrix, no bulk. Writes attributed/replayed/version checked under Owner-first SQL locks; original request never saved.

Request list accepts page/per_page12|25|48, identifier q2..36 hex/hyphens, currencyUSD/SYP, review_status, paired from/to date<=93days through today. Blank optional native form filters omitted; unknown keys422. Dates in configured app timezone translated to UTC half-open bounds. Identifier prefix only; no contact/body search. Detail uses page/per_page validation; timeline25/page. Occasion/audit lists accept same bounded pagination only. Package/history/list GETs never create review or default business data.

Success redirects with truthful Arabic flash. Error422 business validation,409 stale/op/unique/transition conflict,404 unknown,403 denied session,419 CSRF,413 body limit,429 Retry-After,503 unavailable schema, sanitized500. Gift validation view has safe Arabic copy and private headers, no sensitive old-input flash. Owner attempts60/min shared for all Gift mutations, nonmedia256KiB/media10MiB; source5MiB/image safety inherited technical GD checks. No automatic publishing, request mutation, payment/stock/recipient message or development migration.
