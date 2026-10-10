# Customer authentication API

| Method | Path | Authentication / behavior |
| --- | --- | --- |
| GET | /api/v1/auth/google/redirect | Web session; optional safe relative return_to; 302 to Google |
| GET | /api/v1/auth/google/callback | Same web session; validates OAuth state; 302 to frontend |
| GET | /api/v1/auth/me | Sanctum customer session; 200 safe customer data |
| POST | /api/v1/auth/logout | Sanctum customer session and CSRF; 200; invalidates session |

Framework GET /sanctum/csrf-cookie returns 204 with CSRF/session cookies. Use browser
credentials and a configured Origin/Referer for SPA requests. POST logout requires
X-XSRF-TOKEN or X-CSRF-TOKEN. These endpoints never accept application bearer tokens.
Google endpoints share an IP-based 20/minute limit by default; exceeded limit is 429
with the standard RATE_LIMITED envelope and Retry-After.

me returns exactly:

```json
{"success":true,"message":"بيانات العميل الحالي.","data":{"id":"uuidv7","name":"Customer","email":"customer@example.test","avatar_url":null,"account_status":"active","last_login_at":"ISO-8601 timestamp"}}
```

The localized message is supplied by the controller. logout returns success=true,
a localized message and data={}. No google_sub, credentials or tokens are returned.
Errors follow [foundation](foundation.md), with server-generated request_id matching
X-Request-ID: unauthenticated 401 UNAUTHENTICATED, CSRF 419 SESSION_EXPIRED,
suspended 403 ACCOUNT_SUSPENDED. Suspension also revokes the existing session.

OAuth failures are frontend redirects to /auth/error?code= with one of
AUTHENTICATION_FAILED, ACCOUNT_SUSPENDED, AUTH_PROVIDER_UNAVAILABLE or
INVALID_EXTERNAL_IDENTITY. No raw provider message is included. Success goes to the
validated stored return_to on FRONTEND_URL; invalid return paths become /.
Frontend integration and live Google consent are not yet verified.
