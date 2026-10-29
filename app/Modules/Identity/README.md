# Backend session authentication

Identity exposes JSON session authentication against the report's `usuario`,
`rol`, `permiso` and `rol_permiso` tables. It does not provide screens, registration,
password recovery, user management or clinical authorization endpoints.

## HTTP contract

Use the same browser cookie jar for every request. First obtain a CSRF token,
then send it as `X-CSRF-TOKEN` (or `_token`) on mutations. Successful login
replaces both the session identifier and CSRF token; use the returned token.
Laravel's web request-forgery middleware remains enabled.

| Request | Response |
| --- | --- |
| `GET /auth/csrf` | `200 {csrf_token}` |
| `POST /auth/login` with `{identifier,password}` | `200 {user,csrf_token}` |
| `GET /auth/session` | Same payload, or `401 {message:"Unauthenticated."}` |
| `POST /auth/logout` | `204`, empty body; original session invalidated |

`user` contains only `id`, `username`, `email`, `role:{id,name}` and
`permissions:[codes]`. Permissions come only from existing role-permission
links, sorted by code. An administrator role does not bypass those links.
Protected requests query the active account again rather than trusting a cached
user. Deactivation invalidates the existing session. `/login` remains absent;
there are no HTML views or authentication redirects.

## Rejections and availability

- Identifiers match username **or** access email exactly, case sensitively and
  without trimming. Two matching accounts are ambiguous and rejected.
- Passwords are not normalized. Inputs beyond bcrypt's 72-byte boundary are
  rejected before queries or throttle increments, never truncated.
- Missing/malformed fields produce Laravel validation JSON (`422`) before SQL or
  throttle access. Wrong, unknown, inactive and ambiguous accounts all return
  `401 {message:"Invalid credentials or account unavailable."}`.
- Five failed credential attempts within 60 seconds block subsequent attempts
  with `429 {message:"Too many login attempts. Try again later."}` and
  `Retry-After`. Cache keys contain SHA-256(identifier + IP), not plaintext
  identifiers. Successful authentication clears that key.
- CSRF rejection returns `419 {message:"CSRF token mismatch."}`.
- Database, cache and session failures return
  `503 {message:"Unable to complete authentication. Please try again."}`;
  logout failures instead use `Unable to complete sign out. Please try again.`
  Debug exception details are suppressed. An uncertain logout is never confirmed.
- Responses disable caching. Session storage writes and destruction are checked,
  including read-back, rather than trusting Laravel's ignored handler booleans.

## Verification and deployment limits

DB-independent tests cover validation, generic failures and middleware behavior.
Actual queries, constraints, cookie fixation, expiration and invalidation require
`tests/Integration/run-postgres.sh --identity` on its disposable PostgreSQL service.
The integration tests use fresh file-session stores outside the repository and
real encrypted cookies with the testing CSRF bypass disabled. They do not claim
production HTTP or PostgreSQL proof until the operator runs them.

The supported deployment uses Laravel's existing local file sessions and cache.
Use HTTPS and enable secure session cookies in production; existing HttpOnly and
SameSite defaults remain unchanged. Storage read-back is an availability check,
not a distributed-session concurrency guarantee. Login throttling uses Laravel's
cache limiter and is not an atomic cross-process admission lock. Account and
permission provisioning is a separate, explicit operator responsibility; this
module never seeds accounts or grants.
