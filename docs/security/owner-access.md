# Owner access operations

See the [architecture](../architecture/owner-authentication.md) and [web contract](../api/owner-web-interface.md). No real Owner account is provisioned by Phase 6A delivery, and no development migration is applied.

## Authorized setup after review

An operator must separately authorize the schema rollout and verify the target database, backup/recovery policy and server access before applying migrations. Never use `composer setup` as a shortcut. Set a private OWNER_PORTAL_PREFIX in the target environment; the example intentionally has an empty value and no default. Prefix segments must start with a lowercase ASCII letter, contain lowercase letters, digits or hyphens, and have 3–40 characters. Total length is at most 100 characters. Nested segments are permitted. Reserved public/API/auth/asset segments, leading/trailing slashes, traversal, percent escapes, wildcards and existing route collisions are rejected. There is no `/admin/login` or `/login` alias.

Rebuild configuration and route caches after selecting/changing the prefix:

```text
php artisan config:cache
php artisan route:cache
php artisan route:list --name=owner
```

Verify exactly seven Owner routes and no unexpected aliases. Invalid configuration emits `owner_portal_routes_disabled` with a generic reason and registers zero Owner routes. A stale route cache retains its former routes until rebuilt; editing `.env` alone does not change a cached application. Private prefixes can appear in local HTML and server access logs and must not be published as a credential or treated as authentication.

On a trusted terminal, after the schema rollout, invoke:

```text
php artisan owner:provision
```

Enter the chosen display name/email and a unique passphrase into the hidden password/confirmation prompts. Passwords have no CLI option and must never go into shell history, a payload file, `.env`, reports or repository fixtures for a real identity. Hidden input has no visible fallback. The command refuses noninteractive execution, missing schema or an existing owner, including disabled owners. There are no default credentials, web setup routes or shipped privileged seeds. All committed credentials in tests are isolated synthetic fixtures and are not a live account.

## Runtime requirements

Local HTTP can use the existing local session settings. Production Owner routes fail closed with 503 unless the request is HTTPS and SESSION_SECURE_COOKIE and SESSION_HTTP_ONLY are true. Keep APP_DEBUG=false in production, a strong private APP_KEY, an appropriate SameSite policy (existing default lax), narrow SESSION_DOMAIN, and a server-side shared session/cache store appropriate to the deployment. If the existing Customer SPA requires SameSite=None, HTTPS, CSRF and trusted origins remain mandatory; Owner behavior does not alter global Customer session settings. Configure only actual trusted reverse proxies; never trust arbitrary forwarded IP/protocol headers. No proxy trust relaxation is introduced here.

Owner idle timeout is 30 minutes without changing the global Customer session lifetime. The global session can expire sooner. Owner authentication has no remember-me, token or personal access token. Rotating APP_KEY invalidates cookies and HMAC limiter keys and requires an operational plan.

## Limits and privacy

Login permits five attempts per minute per normalized identity+source IP and twenty per hour per source IP, including malformed validated bodies. Identity keys and IP keys use HMAC-SHA256 with the app key and contain no email/IP plaintext. Successful login clears its identity failure counter only; the global source-IP quota remains. 429 includes Retry-After and safe Arabic HTML. Throttled audit events are bounded to one per source IP per hour. Failed credential attempts cannot exceed the enforced quota for a source; input-validation failures are rejected without credential event payloads. HTTP CSRF rejection occurs before credential checking.

`owner_security` logs only event, nullable internal owner_id and request_id: login_succeeded, login_failed, login_throttled, logout, password_changed, profile_name_changed and access_denied. Safe exception logging records exception class/request ID only. No submitted name/email, password/hash, cookie/session ID, provider payload or raw SQL/stack trace is logged. Internal IDs and correlation IDs still require access controls, retention limits and rotation; logs are not immutable financial audit records. Pages, redirects and error responses use no-store/private, and responses prevent framing and MIME sniffing. Request logging at a web server, APM or future middleware must also exclude credential bodies and cookies; that external configuration is not claimed tested here.

## Recovery and emergency access

No email verification, email change, forgot-password, password reset, backup code or 2FA service exists. An owner who loses the passphrase cannot use a web recovery flow. A disabled owner cannot create a replacement through provisioning. There is no emergency HTTP or CLI bypass. Any exceptional operator intervention needs a separately approved, identity-verified operational procedure with revocation and an audit trail; direct DBA privileges are outside application authorization. Secure server access and password-manager storage are operator responsibilities. Recovery and 2FA need future infrastructure and review before implementation.

Deployment-specific TLS, domain/proxy/cookie policies, distributed cache/session storage and real account provisioning remain unverified until a server is chosen. Multi-instance production must share limiter storage; a per-node file cache does not provide cluster-wide quotas.
