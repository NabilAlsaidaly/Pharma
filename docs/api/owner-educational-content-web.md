# Owner educational content web routes

All routes are under the existing configured private Owner prefix, name`owner.education.*`, web/RequireOwner/EducationBoundary. No `/admin` alias, Customer write API or hardcoded prefix. Native session CSRF and credential/status/idle revocation, no-store/private response/validation/error, no-referrer and existing shell headers. Ten added route definitions:53→63, three navigation destinations12→15; historical inventory assertions only are updated.

| Method | Relative path | Name suffix |
| --- | --- | --- |
| GET | education/articles | index |
| GET | education/articles/create | create |
| GET | education/articles/{id} | edit |
| POST | education/articles/{id?} | mutate |
| GET | education/media/{id} | media |
| GET | education/categories | categories |
| GET | education/categories/create | category.create |
| GET | education/categories/{id} | category.edit |
| POST | education/categories/{id?} | category.mutate |
| GET | education/history | history |

UUID route constraints, named static create paths before dynamic IDs. Existing active Owner authorization rechecked in commands. Category edits do not unpublish Article. Article archive clears public pointer, restore unpublished; Category restore hidden. Reads do not create events/coordination rows. All business mutations use operation_id(UUID4/7),expected_version(canonical string),action. New aggregate has expected0 and permanent slug80; existing slug cannot change. Lifecycle actions require confirm1; publication additionally review_acknowledged1, and health_related needs health_acknowledged1 plus a saved source. The checkboxes truthfully record Owner action, not medical certification.

Article save form is multipart; values title/excerpt/category_id/editorial_scope/SEO/is_featured/sort_order, hiddenbody_document produced by the structured editor, bounded selectedProducts/Concerns with order, source rows and retained image rows. No manual JSON/UUID editing. Nested UI markers and canonical digit strings normalize only in the web adapter; Application commands accept strict typed arrays/booleans/integers. Unknown fields, forged Owner/paths, bad versions, invalid image membership422/409. No sensitive old-input flashing. A malformed JSON body_document fails422, not a fallback empty body.

Editor supports fixed block insertion/deletion/up/down, h2/h3, single-level bullet/number list item management, text-selection bold/italic/HTTPS/remove formatting and safe live preview. Textareas/paste produce text only; canonical document state, not rendered HTML, is submitted. Saved private preview, exact published saved preview and paged historical preview are distinct. Schema/inline image bounds and missing placement warnings explained in Arabic. Dirty form navigation warns; save clears dirty state. No-JS supports existing saved metadata/preserved body but cannot author new blocks; visible notice states this.

Images upload one per operation (source5MiB),rolecover/inline and mandatoryalt250. Save first, then upload, then insert retained inline images at block positions and save/publish. At most1cover+5inline current memberships. Remove a retained image only together with its body block; old public/history image remains until explicit publication changes membership. Private media route requires retained revision membership and current Owner; no paths leak.

Owner list page25, optional statepublished/draft/archived and literal titleq≤100; history/revision25. Directory controls product_q/product_page,concern_q/concern_page,category_q/category_page, each50/page andpage≤1000; current retained selections are added boundedly so later pages do not erase them. Save draft before directory search. revision query is a decimal string up to unsignedMAX; unknown fields422. Invalid actions/conflicts show escaped Arabic feedback without copying full content to session. Publish/unpublish/archive/restore native confirmation, optional accessible dialog enhancement with Escape/focus return.

Category creation needs slug/name; optional description500/active/order. Fields bounded and audit/revisions attributed. No default category or hard delete. History exposes only private actor/target/action/version/time/field-name data; body remains revision data. Owner write attempts60/min (failed/retry included),256KiB normal/10MiB upload. Proxy/PHP aggregate restrictions may be stricter; source5MiB/pixel constraints remain enforced. Compilation requires existing Vite bundle, no new frontend dependency.
