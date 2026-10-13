# Phase 6A — Owner authentication implementation report

Evidence labels: **CONFIRMED** means observed; **POTENTIAL** means a risk hypothesis; **NEEDS VERIFICATION** means not demonstrated. Date: 2026-10-13. Branch: `feature/owner-authentication-foundation`.

## 1. Arabic Executive Summary

**CONFIRMED:** أُضيف أساس مستقل لهوية المالكة، مع تجهيز تفاعلي آمن وصفحات عربية للدخول والحساب وتغيير الاسم وكلمة المرور والخروج. بقيت هوية Google الخاصة بالعملاء منفصلة. لم تُطبّق ترحيلات على قاعدة التطوير، ولم يُنشأ حساب مالكة حقيقي، ولم تُنفّذ إدارة أعمال أو مدفوعات. التسليم عبر طلب مراجعة مفتوح وغير مدمج؛ الأدلة النهائية موثقة أدناه وفي وصف الطلب.

## 2. Approved Scope

Only ManagementIdentity and personal Owner access. No Filament, Nova, Livewire, Next.js management app, public provisioning, staff CRUD, recovery, 2FA, business dashboard/search, catalog writes, request management, payment/stock/fulfillment or deployment. User requested execution of phase6A.md; its project requirements were applied within that scope.

## 3. Verified Baseline

**CONFIRMED:** clean original worktree; remote main fetched and fast-forwarded to `39377c49af46534512de35eb548e6321973895b3`, the owner-merged Phase 5A.2 PR4. Its prior feature head was `8125af...`, historical evidence records 288 tests/1854 assertions and successful CI38380399209. This phase preserves the prior test files and physical isolated-test guards. The Phase 5 gate whole-file SHA256 remains `fd5c084bc74aa3354b6542d36f6553a533f7dc06b6d0d70b6c4c20436d66c730`.

## 4. Architecture Decisions

ManagementIdentity owns the feature; thin HTTP/console Interfaces invoke focused Application transactions and Infrastructure persistence/limiter/audit. Laravel validation stays in Application. ADR-020 extends ADR-006 while preserving ADR-004/012/014 and unwired System Console ADR-007. No speculative repository/service abstraction.

## 5. Owner/Customer Identity Separation

**CONFIRMED:** same email in both tables grants no authority; actual Google callback through a fake provider creates only a Customer, and its cookie cannot enter Owner workspace. Owner login creates no Customer; Customer APIs remain 401 without Customer authentication. Existing public Catalog regression passes. No real external Google login was repeated; existing owner-reported live evidence remains historical.

## 6. Guard and Provider Configuration

Default `customer` unchanged. Added explicit session `owner` guard and `management_users` provider/ManagementUser. Every protected path uses RequireOwner; controllers always select owner explicitly. Sanctum configuration/source and Customer authentication code are unchanged.

## 7. Owner Schema

One additive migration creates management_users with UUIDv7 PK, display_name, unique normalized email, password hash, role, active/disabled status, unsigned positive credential_version, generated unique nullable owner_slot, last_login_at and timestamps. No business table alteration, Customer FK, session table, remember token or recovery column. Unique owner_slot also excludes a second owner when the existing one is disabled. Raw SQL uniqueness1062 and version CHECK3819 verified.

## 8. Initial Provisioning

**CONFIRMED:** `php artisan owner:provision`, interactive only, hidden password and confirmation, no visible fallback or credential options/default seed. Missing schema, invalid values, noninteractive execution and repeat provisioning fail safely. Independent concurrent provisioners produce one complete owner and one refusal. Test fixture Customers remain equal before/after. Real provisioning is not performed.

## 9. Credential Hashing

Native Laravel bcrypt Hash::make/check and guarded hidden model fields. Explicit hashing treats even a hash-looking submitted passphrase as literal input instead of importing it; regression verifies this. Minimum12 Unicode characters, maximum72bytes/no NUL avoids native truncation; no arbitrary complexity policy. Real password/confirmation never serialized to worker payloads, command options, reports or `.env`. Test credentials are synthetic source fixtures only.

## 10. Configurable Route Prefix

OWNER_PORTAL_PREFIX has no default; `.env.example` is empty and real `.env` unchanged. Lowercase ASCII segment validation, bounded lengths, reserved routes and existing route collision checks. Twenty invalid/missing examples reject registration. Valid nested path accepted. Actual config:cache/route:cache subprocesses verify 7 configured routes versus0 missing/reserved. `/admin/login`, `/login`, signup and recovery have no aliases. Actual deployment value awaits the owner.

## 11. Authentication Flow

Normalize identity, consume quotas before validation, native dummy/real hash check, locked active-role check and last-login update, then Owner-only login with ID/CSRF regeneration and version/activity snapshot. Unknown, wrong, disabled and restricted failures share one message. Fixed internal validation redirects work even with external Referer/JSON Accept; only email/display_name can be flashed, never arbitrary fields or credentials.

## 12. Login Rate Limiting

**CONFIRMED:**5/min identity+IP,20/hour IP, HMAC-SHA256 keys without plaintext identity, invalid body attempts consume limits, untrusted forwarded IP cannot bypass, different IP works. Success clears only relevant identity counter and keeps IP spraying count. 429 safe HTML/Retry-After; throttle audit one/source/hour. Tests use Laravel array cache; browser uses file cache. No Redis dependency. Distributed shared-store behavior is deferred.

## 13. CSRF Protection

**CONFIRMED:** actual PreventRequestForgery enabled explicitly in tests and browser preview: login, name, password and logout require CSRF. Missing tokens get419 without mutation. No new exemption. Owner exception responses remain HTML, separate from Customer JSON errors.

## 14. Session Lifecycle

Existing server-side session store; no remember-me or tokens. Login/password/logout/access rejection rotate ID and CSRF. Owner activity has30-minute idle limit without changing Customer lifetime. Production fails closed without HTTPS, Secure and HttpOnly. Positive HTTPS cookie Secure/HttpOnly/SameSite=lax and negative insecure/spoofed transport/settings assertions. Infrastructure-specific TLS/domain/proxy configuration is unverified.

## 15. Credential-Version Revocation

Decimal string-safe unsigned version checked against fresh MySQL state on each protected request, including cached guard users. Successful password save increments under lock in one transaction. Older session on independent connection is403 after committed update; current browser retains its new version. No file-session deletion claim; database logical revocation is separate from session-storage topology. Operations already authorized before a commit are not retroactively revoked.

## 16. Logout Semantics

Review regression initially reproduced an old-cookie replay: default `regenerate()` rotates ID but retains the old server record. All four Owner transitions now call `regenerate(true)`; the focused25-test/340-assertion authentication suite passes with stale-cookie refusal and Customer-key preservation. Final complete suite is still verified below before publication.

**CONFIRMED:** owner logout clears only owner keys, rotates ID/CSRF and preserves unrelated Customer data. Fresh request and replayed old cookie cannot restore Owner. Existing Customer logout/suspension/OAuth failure invalidate the entire shared session; actual callback+both guards+Customer logout regression demonstrates Owner cleared on the next fresh request. Other tabs require current CSRF after rotation.

## 17. Owner Authorization

Exact active owner only, no public signup or role management. Anonymous/Customer redirect; disabled, restricted, missing version and idle-expired sessions fail403, clear Owner and rotate. Row-locked account mutations revalidate role/status/version, preventing stale cached authorization from writing after a competing change. Model blocks demotion/deletion; trusted raw DBA privileges are not constrained by an application policy.

## 18. Profile Management

Arabic display name normalized/validated and escaped. Email visible as text only. Unknown fields, email, password, role, status, version and ID injection rejected with no Customer change; sensitive model mass assignment rejected. No administrative business settings.

## 19. Password Change

Current credential required and reverified; weak/mismatching/reused/oversized/NUL input rejected without hash/version change. Native new hash persists, old password fails, new password succeeds, current ID rotates and older version is denied. Competing row-lock writers produce one version2 and one403.

## 20. Deferred Email Change

No writable email field or endpoint. Future verified change requires approved email infrastructure and ownership verification.

## 21. Deferred Password Recovery

No forgot/reset routes, broker, token, email dispatch or backdoor. Secure exceptional operator access needs a separately approved procedure; not implemented.

## 22. Deferred 2FA

No authenticator, recovery code, trusted device or misleading enabled state. Dependencies and threat model require future approval.

## 23. Arabic UI and Accessibility

**CONFIRMED:** custom Blade/Tailwind4/Vite, Arabic lang/RTL, LTR credential fields, labels, required/autocomplete intent, focus styles, aria-invalid/described-by and status/alert feedback. Browser performed login, profile name save, password change, logout, old-password refusal, new-password success and fixed error redirect. Visual inspection at375/768/1440 widths: readable Arabic, wrapped mobile navigation and stacked/two-column profile, no visible clipping. Measured tablet document scrollWidth=innerWidth=768, empty password fields after change. Final browser console0errors/0warnings and compiled CSS/JS200. Local ignored screenshots: output/playwright/owner-login-mobile.png, owner-profile-mobile.png, owner-profile-tablet.png, owner-profile-desktop.png. Full screen-reader audit is **NEEDS VERIFICATION**.

A browser failure initially revealed previous-URL contamination by a favicon request; fixed Owner validation responses use named internal routes, independently of Referer or browser background requests. An isolated preview static-file router was also corrected; it is ignored tooling, not a privileged application setup route. Rechecked login failure/success/password/logout after the fix.

## 24. Security Logging

Safe owner_security events include login_succeeded/failed/throttled, logout, password_changed, profile_name_changed and access_denied. Exact context: event, nullable owner_id, request_id. Tests spy on successful/failure/profile/logout context and forbid identity/credential values; model serialization hides hash/version/slot. Owner unexpected exceptions log type/request ID only and render generic500 with SQL sentinel absent. Failed attempts bounded by quotas; throttle logs additionally bounded one/IP/hour. External web server/APM log policy and retention remain **NEEDS VERIFICATION**.

## 25. Query/Performance Measurements

**CONFIRMED:** observed SQL counts with fresh guard resolution between requests, array session/cache: login2, home2, profile2, password-change4. Login locks by unique email plus last_login update. Protected requests include provider lookup plus explicit fresh-state read; password adds locked row read/update. Cached same-process guard counts were1/1/3, not used as realistic fresh-request proof. No N+1 or Catalog/history join and no invented latency benchmark. Native hashing is deliberately not removed for query/CPU optimization.

## 26. Real Concurrency Evidence

OwnerConcurrencyTest and tests/Support/owner-security-worker.php use independent PHP processes, actual distinct CONNECTION_ID values and positively verified sumaya_pharma_test.

| Race | Barrier and observed outcome |
| --- | --- |
| A provisioning | Parent locks empty unique owner-slot gap; SHOW FULL PROCESSLIST observes both blocked INSERTs; release yields one created/one refused, one complete hashed owner. |
| B old session | Worker caches identity/version1 before ready barrier; parent commits password/version update; resume forces fresh RequireOwner lookup and403. |
| C competing passwords | Parent row lock; both independent FOR UPDATE queries observed; release yields one version2 and one403, old credential no longer valid. |
| D disable | Worker identity/session ready before parent commits disabled state; resumed protected access403. |

The workers use array sessions to prove DB account-state/version enforcement, not shared file locking. Browser separately exercises real file-session rotation and current access. Sample exact local IDs/results recorded in ignored phase6a-full-tests.log; final run details are verified before commit. No real Customer data touched by these races.

## 27. Full PHPUnit Test Results

**CONFIRMED final local run:** `php vendor/bin/phpunit --colors=never` passes **351 tests / 2367 assertions**, 03:16.207,74MB, PHP8.4.26/MySQL9.7.0. Includes all prior288tests and63new Owner cases, including grouped required72scenarios and four real concurrency cases. Log: ignored storage/framework/testing/phase6a-full-tests.log. Assertion totals can vary slightly across allowed historical race outcomes; the exact CI result is separately recorded after observation.

## 28. Historical Regression Results

All prior288tests are retained unchanged: Google adapter/Customer identity, Catalog, Favorites/Cart, evaluation/snapshots, preliminary checkout ownership/idempotency/concurrency/privacy and payment boundaries. The approved gate SHA remains unchanged. Real external OAuth is not independently repeated; mocked provider/application path and historical regressions are the automated evidence.

## 29. Pint

**CONFIRMED final:** `php vendor/bin/pint --test` passes; `git diff --check` clean. No historical test formatting/source changes.

## 30. PHPStan

**CONFIRMED:** final Larastan/PHPStan level5 scan app and System Console passes with no errors, including post-review source changes.

## 31. Composer Checks

**CONFIRMED:** composer validate --strict, check-platform-reqs and audit succeed; no security vulnerability advisories. No composer.json/lock or vendor dependency changes. PHP8.4.26 local.

## 32. NPM/Vite Build

**CONFIRMED:** npm ci --offline --no-audit --no-fund installed87packages from new lockfile, npm run build succeeded Vite7.3.7 (58 modules), CSS45.27kB/JS51.52kB uncompressed, production npm audit --omit=dev found0 vulnerabilities. Existing dependency declarations unchanged; newly reproducible lockfile. Local npm11 install-script review warning for esbuild occurred but build passed; CI Node22 performs normal locked install/build. Development-tool vulnerability scan is not claimed by the omit-dev audit.

## 33. MySQL Compatibility

**CONFIRMED local:** MySQL9.7.0 REPEATABLE-READ, native unique/generated/check constraints and independent races. CI uses official MySQL8.4 image; exact compatibility result recorded after its run. No claim of8.4 validation before observed CI.

## 34. Development Database Preservation

**CONFIRMED:** read-only transaction captures sorted complete rows plus SHOW CREATE TABLE digests for all11tables and `.env` SHA before/after. Deep equal JSON files and metadata SHA256 `291dd66b3adc84a507a82565465c0065ad987af39d1852a0d9ce476750d7e75d`. Development sumaya_pharma contains1Customer,3migration rows, other9tables empty. Hashes verify data/schema/settings unchanged without exposing PII. No management_users or pending checkout schema was added there. Same metadata matches prior phase evidence.

## 35. Confirmation of No Development Migration

**CONFIRMED:** no development migration/reset/rollback, real Owner provisioning, OAuth credential regeneration or infrastructure modification. The prior two checkout migrations remain unapplied there; this new migration is also pending. Tests migrate only physical isolated sumaya_pharma_test. Separate rollout authorization, target verification and secure credentials are required after review.

## 36. Route Inventory

Exactly seven named web routes under the valid prefix: GETlogin, POSTlogin, GEThome, GETprofile, PATCHprofile, PUTpassword, POSTlogout. Five protected routes use RequireOwner; POSTlogin uses quotas; all use web session/CSRF and safe responses. Default configuration registers0Owner routes. Existing web OAuth/API/Public Catalog inventory preserved. See [complete contract](../api/owner-web-interface.md).

## 37. Files Created

ManagementIdentity Application(OwnerCredentials, ProvisionOwner, OwnerLogin, UpdateOwnerAccount), Infrastructure(ManagementUser, OwnerAudit, OwnerLoginLimiter), Interfaces(console command, routes, two controllers, request, three middleware); config/owner_portal.php; additive migration; owner layout/login/home/profile/error views and owner-field component; five Owner test classes and guarded independent worker; package-lock.json; architecture/security/web docs, ADR-020 and this report. Browser/preservation helpers and artifacts ignored, not shipped.

## 38. Files Modified

auth config, bootstrap command/exception wiring, routes/web registration, empty-prefix `.env.example`, CI Node/install/build, .gitignore for browser artifacts, documentation indexes, Customer boundary/session note and CI instructions. Existing business migrations, Customer source, API source, test safety guards, composer manifests/locks, PAY-001 and preserved gate are unchanged.

## 39. Dependencies Changed

No dependency declaration added/removed. New npm lockfile resolves existing frontend ranges. No admin framework, email, Redis, external authentication/provider or background service.

## 40. ADR and Documentation Changes

ADR-020; architecture/owner-authentication.md; security/owner-access.md; api/owner-web-interface.md; this46-section report; docs/README, architecture index/module boundaries/Customer identity notes, deployment CI notes. Future6B/6C/6D and financial boundaries explicitly documented.

## 41. Git Commits

Feature branch based on39377c49. Implementation/docs commit is followed by a documentation-only CI evidence commit. Source commit will be recorded after publication; final SHA and exact-head verification belong in PR description and delivery, avoiding a self-referential commit hash in its own report.

## 42. Final CI Run

**NEEDS VERIFICATION:** PR not yet published at this report draft. Source-head CI and subsequent final documentation-head CI must both succeed before delivery. Final exact head/run/job link will be recorded in the PR description after checking; this draft claims no CI success.

## 43. Pull Request URL

Pending creation against main. Must remain OPEN/UNMERGED; no deployment/Phase6B.

## 44. Known Limitations

**POTENTIAL:** per-node file cache cannot enforce cluster quotas; shared session/limiter storage required if deployment becomes multi-instance. Already-authorized requests cannot be retroactively undone. Shared-cookie CSRF rotation can expire another tab's forms; Customer full-session invalidation clears Owner too. Native dummy hashing adds CPU cost while quotas bound source attempts. Trusted DBA or server access can bypass application policies. Private prefix visibility in HTML/access logs is expected, never a substitute for authentication.

**NEEDS VERIFICATION:** actual production TLS/proxy/domain/session settings, deployment, real account provisioning, external OAuth repeat, external logs/retention, full accessibility audit and recovery operations. No emergency bypass is shipped.

## 45. Future Phase Boundaries

6B custom dashboard/navigation/search/table foundations;6C Catalog visibility/prices/variants/bulk actions/safe soft delete;6D preliminary request inspection/review. Future manual methods (Sham Cash, Al-Haram, QR/recipient settings, COD/full/deposit/zero-deposit), evidence verification and financial confirmation remain unimplemented. PAY-001 controls confirmed funds/fulfillment, any explicit COD exception and actual stock reservation mechanism after approval. Email verification/change, recovery,2FA and staff permission management also deferred.

## 46. Owner Review Checklist

Review identity separation, single-owner DB invariant and hidden CLI; choose prefix only during separately authorized rollout; verify HTTPS/cookie/proxy/shared storage operational policies; review no-recovery limitation and shared-browser semantics; inspect Arabic screenshots/contract, version races, required regression gates and PR exact-head CI proof. Merge, real provisioning, development migration and future business stages remain owner actions requiring separate scope.

### Security matrix evidence map

Scenario numbers reference phase6A.md §26 and are grouped into meaningful tests. A=OwnerAuthenticationTest, P=OwnerProvisioningTest, C=OwnerConcurrencyTest, R=OwnerPrefixTest, K=OwnerCacheTest; method names below omit `test_`.

| Required scenarios | Evidence |
| --- | --- |
| 1,2,5,6,7,29,30,32,33,34 | A all_protected_routes_require_owner_and_do_not_expose_public_aliases; customer_and_owner_guards_are_isolated_even_with_matching_email; real_customer_oauth_session_stays_separate_and_customer_logout_clears_shared_owner_session. |
| 3,4,8 | A real_customer_oauth_session…; owner_login_never_authenticates_a_customer_and_public_catalog_is_unchanged; existing Guest Catalog tests. |
| 9,10,11,12,13,17 | P interactive_command_hashes_password_normalizes_identity_and_preserves_customers; hash_looking_password_is_hashed_as_literal_input_not_imported. |
| 14,71 | C two_simultaneous_provisioners_create_exactly_one_complete_owner; P real_constraints_single_owner_security_version_guarded_fields_and_owner_lifecycle. |
| 15,16 | P invalid_provisioning data set. |
| 18,60,61,62,63,64,65 | A all_protected_routes…; R missing_reserved_and_unsafe_prefixes_fail_closed data set; valid_segment_paths_and_public_route_collisions. |
| 19,20,21,22,23,24,56 | A generic_failures_disabled_nonowner_and_session_fixation; disabled_account_idle_expiration_and_nonowner_default_deny. |
| 25,57,58 | A real_csrf_login_profile_password_logout_and_no_store_headers; production_insecure_http_and_spoofed_https_fail_closed. |
| 26,27 | A identity_ip_and_ip_spraying_limits_without_email_keys; invalid_login_bodies_consume_limits_and_forwarded_ip_cannot_bypass; success_clears_only_identity_failures_and_keeps_ip_spray_counter. |
| 28,46 | A audit_context_never_contains_credentials_email_or_request_body; password_reauthentication_and_policy_fail_without_change; safe_html_error_does_not_leak_sql_or_customer_json_envelope; validation_always_redirects_to_fixed_owner_routes_even_with_external_referer_and_json_accept. |
| 31 | A disabled_account_idle_expiration…; C existing_cached_session_on_another_connection_is_rejected_after_committed_revocation(disable). |
| 35,36,37,38,39 | A password_reauthentication_and_policy_fail_without_change datasets; password_nul_and_multibyte_current_password_never_reach_hasher. |
| 40,41,42,43,44 | A password_change_revokes_old_version_preserves_current_and_rejects_old_password; C existing_cached_session…(password-change). |
| 45 | C competing_password_updates_serialize_and_cannot_restore_old_version. |
| 47,48,49,50,51,52,53 | A profile_name_only_email_readonly_escaping_and_source_customers_unchanged; P real_constraints…; P provisioning Customer preservation. |
| 54,55,59 | A customer_and_owner_guards…; logout_old_cookie_cannot_restore_owner_on_a_fresh_request; real_customer_oauth_session…. |
| 66 | K real_config_and_route_cache_preserve_fail_closed_registration (configured/missing/reserved). |
| 67,68,70 | Git source diff preserves routes/api.php, Customer source and historical migrations; full historical suite; additive management migration only; Phase5 gate SHA unchanged. Operational/source evidence complements tests. |
| 69 | Read-only development11-table before/after metadata and row/schema equality. No destructive test against development. |
| 72 | Preserved IdentityTestCase physical SELECT DATABASE guard and new C/worker equivalent before migration/use. K/R use no DB mutations. |

Additional regressions cover noninteractive command, missing schema, literal hash input, stale cookie replay, production cookie flags, fixed validation redirects, unknown-field old-input exclusion, Unicode byte bounds and safe HTML exceptions. No scenario counts are claimed as separate artificial test methods.
