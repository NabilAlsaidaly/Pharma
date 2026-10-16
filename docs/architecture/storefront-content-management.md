# Storefront content management

Content/Application owns a fixed typed catalog of banners, pages, FAQs and policy documents. Four distinct aggregate tables and four corresponding typed revision tables avoid a generic HTML/site-builder/EAV model. Each has server UUIDv7, version, publication pointer, archive/timestamps, owner-attributed immutable revisions and fixed actions. [Schema](../database/storefront-content-schema.md), [API](../api/public-storefront-content.md), [web forms](../api/owner-business-settings-web.md).

| Entity | Fields and limits | Publication |
| --- | --- | --- |
| Banner | title160, optional description500/link500, required alt250, sort0..999999999, server image path | Requires title/alt/order and existing processed raster; draft edit preserves prior published content/order/image |
| Page | title160, permanent unique slug80, plain body12000, optional SEO title160/description300 | Explicit confirmed current snapshot; old published revision remains during editing |
| FAQ | question300, answer6000 plain text, sort0..999999999 | Explicit publication; deterministic published order then UUID, bounded pagination |
| Policy | title160, permanent privacy/terms/returns identity, plain body12000 | Owner-supplied legal text only; no template, acceptance logging, retrospective changes or legal claims |

Bodies are text/plain, not Markdown/HTML. Blade always escapes and preserves line breaks. Public JSON declares body/answer format and clients must use text rendering, never innerHTML. HTML/script/control/encoded-angle injections are rejected; ordinary ampersands/quotes are plain data. No machine translation, iframe, scripts, embeds, tracking pixels or scheduled publishing.

Save, media, publish, unpublish, archive, restore are explicit operation-identified, version-checked actions. Reorder edits the sort field and is published separately; no silent public reshuffle. Archive removes current public pointer; restore never republishes. Every revision remains privately inspectable25/page. Permanent slugs remain reserved even in archive, preventing two live documents sharing an old URL during draft editing.

Banner destination is a constrained internal path or HTTPS URL without credentials/control/port. Known /products/{slug}, /brands/{slug}, /concerns/{slug} destinations are batched against current Catalog visibility; hidden/archived/missing destination becomes null while banner/image remain public. Other accepted internal paths are informational future-storefront paths, not proof that a Next.js route already exists. No untrusted URL is fetched.

Public list/item queries join the exact published revision in one select. No draft fields, editor IDs, internal events, disk paths or private metadata serialize. Collection totals and page data are separate queries and may span a concurrent publication; each returned row is a committed valid snapshot. Settings profile/currency projection uses one RR transaction. No public draft-preview endpoint. All new image bytes use the existing private local disk. Owner-only binary preview reuses session/private prefix/no-store; the public media endpoint checks current published profile/banner references in one indexed SQL statement before reading bytes. Draft/replaced/unpublished paths return404. An already-authorized in-flight response is not retroactively recalled by later unpublication. Old bytes remain privately inspectable; no per-banner filesystem probe in public metadata listing.

The future Next.js app owns layout only. Laravel owns publication selection and business truth. No Next.js implementation or final frontend API handoff is included.
