# Backend session authentication

Identity exposes JSON session authentication against the report's `usuario`,
`rol`, `permiso` and `rol_permiso` tables, HTML screens for staff sign-in and
the signed-in panel, CU03 account administration, CU17 patient portal
registration and CU18 profile preferences. It does not provide password recovery
or clinical modules. Creating a patient account does not approve a link to a
`paciente` row. Updating the profile does not change role, permissions or that link.
Declared medical information (CU19) lives in Patients and requires that approved link.

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
Protected JSON requests query the active account again rather than trusting a
cached user. Deactivation invalidates the existing session.

HTML screens (same session cookie, CSRF on mutations):

| Request | Response |
| --- | --- |
| `GET /login` | `200` login form, or `302` to `/panel` when a session id is already present |
| `POST /login` | `302` to `/panel`, or `302` back with a generic error |
| `POST /logout` | `302` to `/login`; session invalidated |
| `GET /panel` | `200` role shell, or `302` to `/login` for guests |
| `GET /registro` + `POST /registro` | public patient account registration when enabled |
| `GET /perfil` + `POST /perfil` | own profile and reminder preferences (`perfil.actualizar`) |
| `POST /perfil/verificar-correo` | mark the stored access email as verified; does not enable reminders |

HTML account administration (CU03), session required plus `usuarios.administrar`:

| Request | Response |
| --- | --- |
| `GET /usuarios` | list/search |
| `GET /usuarios/registrar` + `POST /usuarios` | register |
| `GET /usuarios/{id}` | consult |
| `GET /usuarios/{id}/editar` + `POST /usuarios/{id}` | update (password optional) |
| `POST /usuarios/{id}/desactivar` / `activar` | change state |

HTML activity log, session required plus `bitacora.consultar`:

| Request | Response |
| --- | --- |
| `GET /bitacora` | read-only list of accesses and changes; dates default to the last 7 days |

Roles and permission codes come from `PermissionCatalog`. A signed-in request
applies any missing `rol` / `permiso` / `rol_permiso` rows automatically.
`php artisan clinic:sync-permissions` remains a manual repair and does not
create accounts or change passwords. The first administrator still comes from
`clinic:bootstrap-admin`.

`/auth/*` stays JSON. Do not send Idempotency-Key on login or logout.

## Rejections and availability

- Identifiers match username **or** access email exactly, case sensitively and
  without trimming. Two matching accounts are ambiguous and rejected.
- Passwords are not normalized. Inputs beyond bcrypt's 72-byte boundary are
  rejected before queries or throttle increments, never truncated.
- Missing/malformed fields produce Laravel validation JSON (`422`) before SQL or
  throttle access. Wrong, unknown, inactive and ambiguous accounts all return
  `401 {message:"Invalid credentials or account unavailable."}`.
  Each well-formed, within-72-byte, non-throttled attempt generates a dummy hash
  with the configured Laravel algorithm/cost, then performs one password check.
  Eligible accounts use their stored hash; unknown, inactive and ambiguous
  accounts use the dummy, whose result can never authorize access. Generation
  runs for eligible accounts too, avoiding account-dependent setup or cache
  misses. This intentionally adds one hash-generation operation per attempt;
  it uses no sleeps or cached dummy profiles. Comparable hash work reduces the
  account-state timing oracle, but is not a constant-time HTTP guarantee.
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
  Writes refresh PHP's cached filesystem metadata before read-back so rewriting
  an expired file is not mistaken for a storage failure. Expired cookies produce
  anonymous `401` protected reads and `419` stale-CSRF mutations; `GET /auth/csrf`
  can bootstrap a fresh token. Genuine storage failures still produce `503`.

## Verification and deployment limits

DB-independent tests cover validation, generic failures and middleware behavior,
including expired-file recovery, real encrypted cookies and CSRF with only the
identity repository mocked. Database-backed queries, constraints and the complete
cookie/session lifecycle still require
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
module never seeds default credentials.
`clinic:bootstrap-admin` creates the first administrator without permission rows.
The next signed-in request defines the role/permission catalog. `clinic:sync-permissions`
is optional after that and does not change passwords.
