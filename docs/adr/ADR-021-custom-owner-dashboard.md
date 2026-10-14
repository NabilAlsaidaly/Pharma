# ADR-021: Custom read-only Owner dashboard

## Status

Implemented on Phase 6B feature branch, pending owner review and merge.

## Context

ADR-006/020 establish a custom Laravel management panel and separate Owner identity. The approved Phase 6B scope authorizes real search/statistics and read-only inspection; business administration and visitor collection remain future work.

## Decision

Add ManagementDashboard as an explicit read-side module over existing MySQL business tables. Reuse Blade/Tailwind/Vite and existing private-prefix Owner authorization; retain workspace/account routes and add10 protected reads. Use a small named-route navigation registry, four explicit bounded search resources, aggregate real metrics, configured-zone bounded dates and accessible real daily chart. Contact snapshots appear only on protected request details.

Use composable Blade components and small native JavaScript for responsive navigation/current-page selection/date/loading behavior. No new frontend dependency, SPA, admin framework, search service, Redis, analytics collector, schema or business write. Preserve public Customer contracts and System Console independence. Explicit missing-source states differ from zero; DB faults are errors rather than invented successful metrics.

## Consequences

Contained search scans and Owner-global date index scans are documented scaling limits, with no unsupported index/latency claims. Related pages and selectors are bounded; no cross-page selection. Future staff/bulk/soft-delete/catalog/request-review/payment/analytics features need separate authorization designs. Development rollout and real account activation remain separately authorized operations. Regression/security/SQL/browser evidence accompanies PR delivery, and merge/deployment are not authorized.
