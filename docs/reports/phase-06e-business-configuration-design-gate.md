# Phase 6E business configuration design gate

Date: 2026-10-16, Asia/Damascus. Starting verified main: `7427c8a7032973792e0cd77f2a2abaa07157ab78`. Status: **APPROVED**. Phase 6D approval was not substituted for this gate.

## Consolidated decision

1. Payment policies are immutable **preparatory revisions only**. Full payment is exactly `100.00`; deposit is a decimal string from `0.01` through `99.99`, maximum two decimal places, never a float/exponent. No default percentage or payment method is invented. An explicitly selected prepared revision is not an effective Checkout policy. Only the protected Settings workspace consumes these revisions until a separately approved payment integration. PAY-001 remains authoritative.
2. Delivery zones are Owner-entered governorate plus optional city/area and display label, stable UUID, deterministic order, archival and immutable revision history. Normalize surrounding/repeated whitespace and reserve the normalized governorate/city pair even when archived. No inferred geographic matching, overlap priority or fallback zone. A zone may exist with both fees absent. Each currency has an independent nullable fixed decimal fee, precision two decimals, range `0.00`–`9999999999999999.99` (DECIMAL(18,2), technical storage ceiling). Null means unknown; explicit zero means a deliberately prepared free fee. Negative/exponent/excess precision rejected. No delivery fee, eligibility or coverage applied to current Cart/Checkout or exposed as live shipping service.
3. Recommended existing-flow decision: **defer intake pausing entirely**. Do not create an intake toggle or change preliminary submission/replay behavior. Real supported publication controls (publish/unpublish and archive/restore) are the effective controls for content. If Owner instead explicitly authorizes intake pausing, document and approve its exact transaction boundary before implementing that dependent feature.
4. Informational configuration: public name, short description/about, optional safe raster logo, email/phone/address/opening-hours text and fixed approved social/contact links (Facebook, Instagram, WhatsApp, TikTok, YouTube). No invented actual business values. Settings publishes only explicitly approved public values; optional absent fields are null/omitted as documented. Opening hours are display text, never automatic admission hours. Consumer: explicit Laravel `/api/v1/storefront` read projections and protected Owner preview; no Next.js code.
5. Currency presentation contains order and bounded labels/symbols for existing USD/SYP only. Consumer: public configuration projection. Both currencies and stored prices stay independent; no conversion, price changes or currency disablement.
6. Content comprises banners, pages, FAQ and legal-policy documents with private drafts, bounded plain text (escaped by Blade; API declares text/plain), immutable revisions and an explicit current published revision. Editing a draft preserves the published snapshot. Publish/unpublish, archive/restore and ordering are explicit version-checked audited operations. Restoring returns unpublished. Banner publication requires securely processed raster image and alt text. Links use safe internal paths or HTTPS without credentials/control characters; a Catalog destination is excluded if hidden/archived. No scheduling, remote retrieval, custom HTML, scripts, embeds or automatic legal text. Owner supplies and approves all policy text through explicit publication. Consumers: corresponding narrow read-only published Laravel APIs, Owner previews/history.

## Exact activation categories

| Category | Catalog | Consumer | Effect on existing Customer business behavior |
| --- | --- | --- | --- |
| A — informational/public | Identity/contact/social, currency presentation | Explicit public configuration projection after publication | Additive informational reads only |
| A — published content | Banners, pages, FAQ, store policies | Narrow published-content APIs | Additive reads; drafts never public |
| B — prepared, not effective | Full/deposit policy revisions | Protected Owner settings/history | None; no payment requirements on existing requests |
| B — prepared, not effective | Zones and independent nullable fees | Protected Owner preparation/history | None; no current shipping eligibility/totals |
| C — deferred recommendation | Intake pause | No consumer/toggle unless separately approved | Existing submission and committed replay unchanged |

## Persistence and safety contract

Typed explicit groups and fields, no arbitrary settings-key/EAV editor. Domain boundaries: Settings owns configuration/payment/delivery preparation; Content owns published content; Delivery/Payments execution remains deferred; ManagementDashboard only provides shell/navigation; Orders retains immutable submission/review. Additive schemas, UUIDv7, restrictive FKs, unique identities, string-safe versions and decimal checks. Owner-scoped operation identity/fingerprint replay precedes stale-version rejection, state/revision/audit commit in one short transaction. Stable target-first lock order; no Customer/Cart/Catalog writes and no network/image decoding under SQL locks. Media immutable generated paths, bounded GD re-encoding, old references retained, fresh-file compensation on SQL failure; filesystem/SQL crash atomicity not promised.

Public reads use explicit projections, bounded pagination and live publication selection without shared stale draft caches. Missing schema returns503, missing/unpublished document404, empty configured collections are honestly empty. Owner guard/private prefix/CSRF/no-store/current revocation retained. No secrets/.env editing, new dependencies, Redis/queues/System Console, real Owner or development/production migration. All SQL execution/migration tests positively guard physical `sumaya_pharma_test`; before/after development fingerprint required.

## Approval record

Owner explicitly answered on2026-10-16: «أعتمد المقترح كاملاً مع تأجيل إيقاف الاستقبال». This approves decisions1–6 including payment/delivery preparation, two-decimal bounds/null/explicit zero, informational publication and complete deferral of intake pausing. No intake switch or admission-code change is authorized or implemented. Complete Phase6E delivery remains subject to actual verification.
