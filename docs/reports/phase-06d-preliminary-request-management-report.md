# Phase 6D — Preliminary request management

Date: 2026-10-16 (Asia/Damascus). CONFIRMED = directly verified; POTENTIAL = reasoned risk; NEEDS VERIFICATION = unavailable/unexecuted evidence. Exact final self-referential commit/CI proof is independently recorded in the PR/final delivery after the documentation evidence commit.

## 1. Arabic executive summary

أُضيفت مراجعة داخلية للطلبات المبدئية بالحالات المعتمدة، وملاحظات خاصة ومتابعة يدوية وسجل نشاط دائم، وإجراءات جماعية مؤكدة ومحدودة. تبقى بيانات العميل والأصناف وحالة الإرسال الأصلية محفوظة دون تعديل. اكتملت اختبارات مركزة وسباقات MySQL وفحص المتصفح، ونجح التشغيل الكامل بـ511 اختباراً و4498 تحققاً؛ يبقى CI النهائي شرطاً مستقلاً قبل إعلان اكتمال التسليم. لا يوجد تأكيد شراء أو دفع أو مخزون أو إرسال رسائل.

## 2. Starting main SHA

CONFIRMED fetched main f0a1690549b1b7a516182ae912afd9525fb53762, merged PR7 including FIX6C001. Local original source unchanged before activation. Baseline direct PHPUnit459tests/3991assertions,04:39.167/82MB with JUnit; first artisan wrapper ended without final summary and is not claimed as successful.

## 3. Feature branch

feature/preliminary-request-management from verified main. Existing untracked storage/framework/lsp-74da1b7d667d6302.php preserved/excluded; SHA2568d0f5b931fe0a63f90d8d515c77c4219bca53b08dfc9ff747ef17f5d264fe97f. No reset/force push or unrelated user-file modification.

## 4. Final feature SHA

Recorded after source/evidence commits and independent exact-head verification in PR/final delivery. This report cannot embed its own commit hash; source success must not substitute for final documentation-head CI.

## 5. PR URL

CONFIRMED exactly one [Phase6D PR #8](https://github.com/NabilAlsaidaly/Pharma/pull/8) against main, OPEN/UNMERGED. No deployment or subsequent milestone.

## 6. Design gate and Owner approval

CONFIRMED [design gate](phase-06d-review-workflow-design-gate.md) completed before persisted workflow activation. On2026-10-16 Owner explicitly answered «أعتمد السياسة كاملة كما وردت» to the consolidated proposal covering exact four states/matrix, reason-required reopening, individual completion/reopening, bulk100 restricted targets and private manual text-only follow-up. No unapproved transition/channel/outcome invented. [ADR023](../adr/ADR-023-preliminary-request-review.md), numbering verified unused.

## 7. Exact implemented review states

new=جديد (inferred without row or persisted by a genuine note); under_review=قيد المراجعة; awaiting_contact=يحتاج إلى تواصل; reviewed=تمت المراجعة. Completion is administrative examination only. Variant/Cart/payment statuses are unrelated.

## 8. State transition matrix

| From | Permitted next | Restriction |
| --- | --- | --- |
| new | under_review / awaiting_contact | Individual or eligible bulk |
| under_review | awaiting_contact / reviewed | reviewed individual only |
| awaiting_contact | under_review | Individual or eligible bulk |
| reviewed | under_review | Individual with mandatory reason |

Other/new same-state operations rejected; identical committed operation replays without another transition. All16matrix combinations tested against an independent expected allowed set.

## 9. Separation from submission status

Original checkout_intents.status remains submitted-only enum, never saved by review services. Review table is separate, no Order/financial/stock/notification state. UI explicitly states «اكتمال المراجعة لا يعني تأكيد الطلب أو استلام الدفع».

## 10. Review aggregate design

Unique one-to-one request review, UUIDv7, approved enum/version and actual first-start/current-completion/last-activity/last state-changing Owner. Missing aggregate=new/version0; no backfill/GET writes. Notes create a genuine new-state aggregate without false start/completion timestamps. Reopen clears current completion while previous completion event remains.

## 11. Database migration details

One additive2026_10_16_000001_create_preliminary_request_reviews.php, two new tables and Owner-wide created_at/id index. Restrictive request/Owner FKs, composite review/request FK, unique request aggregate and Owner-operation identity. Original migrations/Customer schema/Checkout submitted enum untouched. Applied only to guarded test DB. Down destroys review/event history; backup/retention approval needed before rollback. [Schema](../database/preliminary-request-review-schema.md).

## 12. Internal notes

Append-only typed note events,2000Unicode characters, Author stable ID/timestamp, escaped HTML, control/length/empty validation. Customer notes remain original contact facts. No edit/delete note endpoint or mutable review_notes blob. Timeline25/page newest timestamp/UUID first. Existing current display name joined without credentials.

## 13. Manual follow-up semantics

Separate follow_up_recorded event with bounded private Owner text and actual documentation time. Describes entered manual work only; no channel/outcome fields, provider integration, delivery confirmation, reminders or automatic messages. Notes and follow-up advance activity/version without implying review completion.

## 14. Audit events

Six fixed event types: note_added, follow_up_recorded, review_started, status_changed, review_completed, review_reopened. Request/review/Owner IDs, exact previous/result states, note when applicable, operation/fingerprint/resulting version/bulk/request correlation and timestamp. Event and aggregate commit atomically; no generic HTTP-body JSON, copied contact or credential. Append-only HTTP boundary, no cryptographic tamper-proof/DBA claim.

## 15. Idempotency

Owner+operation UUID database unique, canonical SHA256 binds target/kind/state/note/expected version/bulk. Committed identical replay returns same event before stale check, changed payload409. Customer operation identity remains independent. Bulk per-target operation IDs derive from server preview; committed whole-group repeat is idempotent within retained expiry, no duplicate events or unbounded session history.

## 16. Optimistic concurrency

Absent0, every successful note/follow-up/transition increments exact string-safe unsigned BIGINT version. Canonical string bound and overflow reject. New actions need current version; conflict409 creates no false successful audit and asks refresh. Transaction retry3 does not replace version validation. Reopening reason checked server-side and native required field.

## 17. Actual lock order

Individual: immutable Checkout header FOR UPDATE (never saved), prior Owner-operation lookup FOR UPDATE for replay, then existing review FOR UPDATE, aggregate save/event insertion. Bulk: all captured headers sorted UUID first, all reviews sorted request, complete-set/version/transition validation, then same service per target/prior-event check. No Customer/Cart/Catalog locks in review operations. Customer replay retains its original Customer→Checkout ordering without a reverse acquisition. Short SQL only, no network or rendering under locks. Detail uses one nonlocking committed REPEATABLE READ snapshot.

## 18. Original Checkout immutability

Tests deep-compare complete header/item JSON including original IDs/contact/source version/fingerprint/quantities/order/flags/status/timestamps. Browser proof separately hashes9original business tables before/after real form/bulk work: identical. Review does not resolve live Catalog as historical names/prices. Historical IDs remain readable when Products/Variants archived.

## 19. Customer retry compatibility

Actual Customer API data before/after review/notes/completion/archive/Cart removal remains identical; list/detail reveal no private event/note. Original committed operation returns same request/created=false; changed payload conflicts. Another Customer receives404. Existing Google/Cart/Checkout regressions retained; submission/history/resource source unchanged. No confirmed Order/clear Cart action.

## 20. Request list

Existing owner.requests.index extended in place. Safe UUID/submission date/currency/item count/submitted state/separate review state/original fulfillment flag/last activity/detail link; no contact snapshots. List10/25/50,stable created_at+id ordering,page<=1000. GET infers missing reviews without writes. No competing requests list.

## 21. Request detail

Existing owner.requests.show extended: original contact snapshot; historical item IDs/quantity/order/fulfillment flag with25/page; current administrative status/reviewer/timestamps; note/follow-up forms; paginated private chronological activity. No financial totals or current Catalog price inference. Contact not embedded in hidden attributes/JS. Review dates, UUIDs/currency LTR.

## 22. Search/filter behavior

Whitelisted identifier q2..100: exact UUID or safe hex-prefix; nonidentifier text yields no request match, never scans PII. Separate review status, submitted-only status, currency, original fulfillment, reviewed/not-completed, paired bounded submission dates and last-activity dates. SQL-bound; unknown filters/invalid ranges422; validated filters retained in pagination. Global search remains safe identifiers and original summary projection, no note-body/contact expansion.

## 23. Protected contact display

Only protected detail discloses original name/phone/email/governorate/city/address/Customer note. List/search/metrics/bulk target confirmation contain no contact or private note. No lookup/export/Customer-directory feature. Existing explicit Customer resource remains unchanged and Customer scoped.

## 24. Bulk selection

Individual/multiple/visible page versus explicit all-filtered across pages, clear/reset on navigation/history, no storage. Real browser25page versus27matching and clear reset count/scope. Server resolves cap100+1 maximum preview rows, validates whole eligible set. IDs/versions captured explicitly; later new Customer requests excluded, as real independent-connection test demonstrates.

## 25. Bulk confirmation

Exact type/count/current states/proposed state/target IDs and no-commercial-effects warning. One session token10minutes, explicit accepted checkbox, exact server-bound preview only. Changed selected state/version/membership rejects all. Invalid/expired/replaced tokens rejected; identical committed token replays without new events. No bulk completion/reopening/notes/financial action.

## 26. Bulk operation limits

100 synchronous requests. Technical ceiling, not commercial approval. All original headers/reviews lock in stable order; no partial skipping. Real SQL failure after first target and overlapping individual/bulk tests verify transaction behavior. No queue/unlimited promise; work scales within cap.

## 27. Dashboard metrics

Existing original submitted counts/creation chart preserved. Two new SQL aggregates: total/current review counts (missing row counts new), original fulfillment-flag request count; selected-period event count and distinct request count. Separate reviewMetrics projection preserves historical metrics array contract. Shared per-request schema snapshot avoids redundant metadata query. No revenue/stock/conversion inference.

## 28. Submission-versus-review dates

Dashboard presets/date infrastructure retained. Configured application zone (currently UTC), half-open UTC boundaries with existing DST tests. Submission counts use original created_at; old request reviewed today is not newly submitted. Activity counts use event timestamps; events and distinct affected requests shown separately. List activity range uses actual latest activity. Review date filtering never uses Customer contact.

## 29. Privacy/security

Owner-only no-store/referrer/no-sniff/CSRF protection, strict allowlists, plain text escaped, no private localStorage, generic errors and no old-input note flash. Owner exception log class/request ID only; real SQL error with synthetic private note verifies no leaked SQL/contact/body. No public review/Customer JSON additions. [Security](../security/preliminary-request-management.md).

## 30. Owner authorization

Same validated private prefix and exact active Owner guard/RequireOwner/idle/credential revocation/production transport. Server derives stable Owner ID. Guest/Customer and disabled/restricted/idle/revoked identities denied all5request destinations; real CSRF checks every POST. Three new POST definitions extend26to29, old named GETs preserved. No conventional admin alias/staff model.

## 31. Rate limiting

60review-write/preview/confirmation attempts per60seconds per authenticated Owner, invalid/replay included;429 Retry-After. Existing cache store, no Redis requirement. Existing16KiB shared body cap preserved;2000character notes,100bulk, bounded pages/dates. Multi-node shared cache remains an operational prerequisite; Customer quotas unchanged.

## 32. Real MySQL concurrency findings

CONFIRMED initial7cases/61assertions using independent processes, physical connection IDs, held row barriers and actual blocked SQL. First creation: one aggregate/event, stale other409. Competing transitions: one commit/one409. Identical concurrent note: both return same event, exactly one row. Customer retry while review commits: same original immutable request. Bulk/individual: exactly one operation succeeds, no partial batch. New Customer submission while preview pending: explicit captured set only. Reader pauses after first snapshot read, writer commits, resumed detail returns coherent prior state/history; next read sees new state. One outer attempt per worker, no observed deadlock/retry in these controlled cases. Final eight-scenario run also confirms distinct concurrent notes: one stale409, first append retained, later fresh-version append succeeds. All worker attempts1; same eight-case results repeated in final full run. No sequential work called a race.

## 33. Query counts

CONFIRMED empty list4. At50/200/1000synthetic requests, exact equal counts across all ten real HTTP paths: list5,filtered5,detail9,timeline9,overview11,search7,note8,transition8,preview4,one-target bulk execution12. Dataset has inferred new/persisted states/multiple notes/multiple items/archived Product refs/fulfillment flags. Original baseline dashboard9→11 due two actual aggregates, test bound adjusted only for overview; other historical bounds retained. Count equality does not prove equal cost/latency or production capacity; bulk work bounded linear.

## 34. Index plans

CONFIRMED before Owner-wide date query used covering scan of Customer-first index plus sort. New additive checkout_owner_created_id_index after: local covering range scan; at1000rows measured plan uses that covering index with25rows/Using where;Using index. Source CI on MySQL8.4.11 independently confirms checkout_owner_created_id_index, type=index, rows25, Using where;Using index, and all ten HTTP counts at50/200/1000 equal the local measurements. Existing status/activity/event request-time indexes support predicates/history; joins/count costs may still scale. No contact index or unmeasured throughput claim.

## 35. Responsive/browser evidence

CONFIRMED real CLI Playwright/native CSRF on physically guarded test DB and temporary local server, no real Owner. Actual reason-required reopening; Arabic note containing literal script escaped/not executed; manual follow-up; contact/under-review/reviewed transitions; timeline page2; new filter/page25/all27/clear; keyboard-confirmed27-record bulk; real metrics27under_review/1reviewed/63events across28requests. Original9business-table hashes identical afterward. Five page states (list/detail/history page/confirmation/overview) at375/768/1440: document scrollWidth exactly viewport. Console0errors/0warnings; screenshots output/playwright/phase6d-list-375.png,review-768.png,confirm-1440.png,overview-1440.png visually reviewed. Fixtures/helpers/screenshots ignored, not shipped. Server/browser stopped before final SQL tests.

## 36. Accessibility findings

Semantic RTL forms, labels, native validation and Space consent, LTR identifiers/currencies/dates, contained keyboard-scrollable tables, live selection counts. Mobile menu Tab/Escape restores focus to toggle (aria-expanded=false). Fixed obsolete read-only request copy and review-date direction found in browser review. Native zoom/full screen-reader/complete contrast audit remain NEEDS VERIFICATION; no full conformance claim.

## 37. Historical regression results

Baseline459/3991 executed before activating new migration/routes. Historical tests retained. Only route inventory26→29, cache inventory29 and overview query allowance9→11 updated for actual new capabilities; no suppressions, removed cases or altered business expectations. Customer/Catalog/Cart/Checkout/submission/identity source unchanged. Original historical ADRs/migrations preserved.

## 38. Total PHPUnit tests

CONFIRMED final full511passed/4498assertions,04:57.436,86MB,PHPUNIT_EXIT=0 and JUnit zero errors/failures/skipped. All459historical cases retained plus52new cases. Earlier full509/4479 passed before the final two substantive tests and strict filter-type regression. Final narrow SQL-injection-as-plain-note test separately rechecked after text expansion; no implementation change thereafter.

## 39. Total assertions

CONFIRMED actual final local full4498assertions/511tests; source CI independently passed511tests/4498assertions in134.51seconds. Initial focused50/479 and historical459/3991 are independently executed results, not substituted final counts. Documentation-head CI is independently verified after this evidence commit and recorded in PR/final delivery.

## 40. Pint result

CONFIRMED --dirty and --test pass on current implementation; final verification must stay green. No unrelated formatting.

## 41. PHPStan result

CONFIRMED existing level5/512M passes; corrected actual string-cast version annotation and redundant nullsafe access. No rule/baseline/ignore change.

## 42. Composer validation/platform/audit

CONFIRMED validate --strict, check-platform-reqs and audit pass; PHP8.4.26, no advisories. Composer files/dependencies unchanged.

## 43. Vite result

CONFIRMED locked npm ci --offline --no-audit --no-fund,87existing packages; Vite7.3.7/59modules build. CSS52.71kB/gzip11.71,JS54.49kB/gzip20.60. Existing npm11 esbuild install-scripts advisory nonblocking. No NPM lock/dependency/CDN change.

## 44. MySQL compatibility

CONFIRMED local9.7.0/REPEATABLE-READ and source CI MySQL8.4.11/PHP8.4.26. Additive schema/real constraints/aggregates/independent races exercised on both environments. CI logs independently show seven overlapping-worker cases plus coherent snapshot reader/writer (connections561 through589), attempts1, committed replay sharing one event, stale conflicts and captured-set isolation. Original generated Catalog/Checkout keys unchanged. Final documentation-head CI is separately verified externally.

## 45. Database preservation

CONFIRMED before actual development sumaya_pharma11tables/1Customer/3migration rows/other9empty; sorted full rows/schema/.env metadataSHA256291dd66b3adc84a507a82565465c0065ad987af39d1852a0d9ce476750d7e75d. Actual configured and physical test DB checked before every migration-based suite and worker. CONFIRMED final after deep comparison: every sorted row/schema/.env hash matches; after metadataSHA256 is identical. Three development migrations remain applied and five prior/new migrations remain pending. No development/production migration/reset/seed or .env write.

## 46. No real Owner provisioning

CONFIRMED only synthetic guarded test Owners. No real account/Customer/media/.env/Google change. Existing untracked LSP helper retained unchanged and not shipped.

## 47. CI URL/status

CONFIRMED source head1de487f8a4a4c5073fa74c6f6808b360043c7f6b, [CI run30](https://github.com/NabilAlsaidaly/Pharma/actions/runs/38911607630), quality job117008564872: every step successful, including locked build, formatting, PHPStan,511tests/4498assertions and security audit without advisories. PR#8 remains OPEN/UNMERGED. Final documentation evidence-head SHA/run/job/result independently verified and recorded in PR/final delivery after this commit; report file cannot contain its own hash. No merge/deployment.

## 48. Created/modified files

Created Orders Domain ReviewStatus, Application ReviewRequestAction/ReviewFilters/BulkReviewRequests, two persistence models, OwnerRequestReviewReader, OwnerRequestReviewController/ReviewMutationRequest/ReviewWriteThrottle; additive migration; four Owner request views; six RequestManagement test classes plus independent worker; gate/ADR023/architecture/database/security/web/this53section report.

Modified private route registration (two existing reads/three new writes), dashboard schema/read metrics/resource copy, error handling, existing native selection, overview/sidebar/table copy, relevant indexes and three historical route/performance test files. No Customer/Cart/Catalog business source, original submission/history/resource, historical migration, package lock, auth config, PAY001 or System Console change. Ignored fixture helpers/logs/JUnit/screenshots remain local only.

## 49. Dependencies changed

None. Laravel/Blade/Tailwind/Vite/native JS/current database/cache retained; no queue/Redis/admin framework or external API.

## 50. Known limitations

Bounded synchronous bulk100, date spans93days,page<=1000,timeline25; indexed counts/offsets still cost data-dependent work. Request details are coherent snapshots, not globally atomic across dashboard/chart requests. One pending10minute confirmation per session; a replacement preview supersedes prior token. Trusted DBAs/raw SQL can alter append-only tables; no tamper-proof claim. PII may be Owner-entered into private note text; no duplicated structured contact fields. Native zoom/full assistive-tech/production capacity not claimed. Shared distributed limiter/session storage requires operational configuration.

## 51. Deferred business decisions

Payments/verification/deposit/COD, confirmed Orders/financial prices/revisions, stock/allocation, delivery, notifications/reminders/channels/outcomes, staff, contact lookup/exports, cancellation/deletion and Customer tracking remain excluded. No unresolved decision blocks accepted operational review policy.

## 52. Rollout prerequisites

Explicit authorized schema rollout/backup/physical target verification for all pending prior+new migrations; source and schema deployment coordinated; real Owner provisioning separate; private prefix/TLS/cookies/proxy, database/session/cache permissions and shared multi-node limiter, access-log body exclusion and history retention. Rollback is destructive for review history. None applied to development/production here.

## 53. Merge-readiness assessment

Owner-approved policy implemented; focused/race/browser evidence confirmed. Full latest local tests, final preservation comparison and source CI pass; exactly one PR#8 is OPEN/UNMERGED. Source change comprises44files (29new/15modified). Ready for Owner review subject to independently successful documentation-head CI, recorded in the PR/final delivery before declaring completion. Rollout prerequisites in section52 remain separate operational work. No merge/deployment/subsequent milestone is authorized by delivery.
