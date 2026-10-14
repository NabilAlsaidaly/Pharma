# Owner web interface

This is a custom Laravel Blade Arabic RTL web interface, not a Customer JSON API. Let `{prefix}` mean the valid configured OWNER_PORTAL_PREFIX; it is not a literal deployment value. Routes use the existing web session/CSRF middleware plus private response headers and bounded mutation bodies.

| Method | Path | Name | Input / behavior |
| --- | --- | --- | --- |
| GET | /{prefix}/login | owner.login | Login form; active existing Owner redirects home after current authorization checks. |
| POST | /{prefix}/login | owner.login.submit | email, password, CSRF. Quotas, validation and credentials; redirects home on success. |
| GET | /{prefix} | owner.home | Protected minimal workspace, account link and logout. No business statistics or controls. |
| GET | /{prefix}/profile | owner.profile | Protected display name, read-only email and password-change forms. |
| PATCH | /{prefix}/profile | owner.profile.update | display_name only, CSRF; redirect profile with success feedback. |
| PUT | /{prefix}/password | owner.password.update | current_password, password, password_confirmation, CSRF; redirect profile after atomic revocation and current-session rotation. |
| POST | /{prefix}/logout | owner.logout | CSRF; clear Owner keys and rotate session/CSRF; redirect Owner login. |

Blade forms use POST with Laravel `_method` for PATCH/PUT. Mutation fields are strictly allowlisted, including the reserved form `_token`/`_method`; query parameters and additional business/security fields are rejected. Display names normalize surrounding whitespace, require meaningful letters/numbers, reject control/format characters and angle brackets, and have maximum length 150. Emails normalize surrounding whitespace/lowercase and validate RFC syntax with maximum length 254. Password fields have native byte bounds; new passwords require 12 characters, confirmation and a difference from the current credential. Password values remain empty after validation and are never in old input.

Anonymous/Customer access redirects to the configured Owner login. A stale, disabled, idle-expired or non-owner management identity receives safe 403 and loses Owner authentication. Validation redirects back with Arabic field messages and safe old values. Failed credentials use one generic message for wrong, absent, disabled or restricted identities. CSRF failures are 419, excessive attempts 429 with Retry-After, oversized mutations 413 and unsafe production transport/session configuration 503. Unexpected failures use generic 500 HTML even with debug enabled, with no SQL/trace or Customer error envelope. Authentication redirects are fixed internal routes; no return URL or open redirect field is accepted.

All Owner responses prohibit caching. Forms have associated labels, required inputs, autocomplete intent, Arabic language/RTL layout, keyboard-visible focus, inline aria-invalid/described-by and status/error feedback. Email and passwords use LTR fields within RTL layout. There is no registration, recovery, email-edit, 2FA, remember-me or business CRUD endpoint.

See [session semantics and races](../architecture/owner-authentication.md) and [operator setup](../security/owner-access.md).
