# API foundation

`routes/api.php` is registered with Laravel's API middleware group and prefix `api/v1`.
It contains no endpoints. `/up` remains Laravel's existing health endpoint.

Future success responses follow `{"success":true,"message":"...","data":{}}`.
No global response wrapper or business endpoint is introduced now.

Errors on `/api` and `/api/*` use:

```json
{"success":false,"error":{"code":"RESOURCE_NOT_FOUND","message":"المورد المطلوب غير موجود.","fields":{}},"request_id":"<UUIDv7>"}
```

The contract is selected by API path even without an Accept header. Web requests retain
Laravel's normal exception behavior. Validation field messages are returned only for
ValidationException. Exception messages and traces are never copied to API responses,
including when APP_DEBUG=true. Protocol headers such as Retry-After and Allow are retained.

| Exception/status | HTTP | Code |
| --- | --- | --- |
| ValidationException | 422 | VALIDATION_FAILED |
| AuthenticationException | 401 | UNAUTHENTICATED |
| AuthorizationException (converted by Laravel) | 403 | FORBIDDEN |
| ModelNotFoundException / NotFoundHttpException | 404 | RESOURCE_NOT_FOUND |
| HttpException 409 | 409 | RESOURCE_CONFLICT |
| ThrottleRequestsException | 429 | RATE_LIMITED |
| HttpException 503 | 503 | SERVICE_UNAVAILABLE |
| HTTP 400 / 405 / 419 | same | BAD_REQUEST / METHOD_NOT_ALLOWED / SESSION_EXPIRED |
| Unexpected Throwable | 500 | INTERNAL_ERROR |

Each HTTP request receives a server-generated `Str::uuid7()` request attribute
`request_id` and response header `X-Request-ID`; caller-supplied IDs are ignored.
Log context includes request_id, method, path without query parameters, and environment.
Context is cleared after each response to prevent leakage across repeated requests.
No full request logging, bodies, query values, cookies, authorization headers or credentials
are added to context. Future log statements must also exclude secrets and redact sensitive
exception/integration data before logging. No Sentry or tracing platform is installed.
