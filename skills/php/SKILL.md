---
name: php
description: "Trigger: PHP, Laravel, controller, service, repository, FormRequest, DTO, Idempotency-Key, handler, módulo, PostgreSQL. Código PHP del monolito modular de Clínica Vida Salud."
license: MIT
metadata:
  author: clinica-vida-salud
  version: "2.3"
---

## Activation Contract

Use this skill before creating or editing PHP: controllers, services, repositories, FormRequests, routes, models or `app/Modules/*`.

The generic file `/home/agent/skill-generic/php` is the **practice source**, not the layout. Do not create `BACKEND/src`, JWT, `Postgres.php`, `Schema.php`, `SendError`, `ErrorCodes` or stored procedures.

## Hard Rules

- Laravel 13 modular monolith + Blade + clinic PostgreSQL schema. Modules: Identity, Patients, MedicalStaff, Schedules, Appointments.
- Controller → Service → Repository. New CUs must include a Service. Do not retrofit Identity unless that is the task.
- No empty module folders. No Laravel `users`/`cache`/`jobs` tables. No `.env` reads while the bootstrap guard is active. No SQLite.

---

## Estructura del proyecto

```
app/
├── Modules/
│   ├── Identity/          # CU01–CU03, CU17 when implemented
│   ├── Patients/          # CU04
│   ├── MedicalStaff/      # CU05–CU06
│   ├── Schedules/         # CU07
│   └── Appointments/      # CU08–CU16, CU21–CU22
│       ├── Http/
│       │   ├── Controllers/
│       │   └── Requests/    # FormRequests = DTOs
│       ├── Services/
│       ├── Repositories/
│       ├── Models/          # only clinic entities this module owns
│       └── routes/web.php
├── Providers/               # ModuleServiceProvider loads each existing web.php
└── Shared/                  # only when two modules share a type; do not dump domain here
database/schema/clinic.sql   # 17 tables — source of column names
resources/views/
public/
tests/
```

Create only the folders a CU needs. Appointments today has `routes/web.php` (`GET /`) only. PSR-4 is `App\` via Composer; `public/index.php` is Laravel’s front controller — do not add another bootstrap.

---

## Patrón arquitectónico

```
HTTP (navegador)
    → middleware web (sesión, CSRF)
    → authenticate / authorize (sesión activa + permiso)
    → FormRequest (DTO)
    → Controller        lee validated(), llama al service, devuelve View o JSON
        → Service       reglas de negocio + DB::transaction
            → Repository    única capa que toca PostgreSQL
                → tablas del schema de la clínica
```

CU22 is a reusable availability check inside Appointments services. It is not a page and does not reserve a slot.

---

## Reglas del patrón

- **Controller**: no business rules, no SQL, no `Hash`/`DB` except calling the service. Reads `$request->validated()`. Returns `view()` or the module’s existing JSON contract.
- **Service**: no `Request`/`Response`, no Blade, no raw SQL. Owns RN01–RN14 and CU transitions. Uses `DB::transaction` when two writes or a check+write must be atomic (programar/reprogramar).
- **Repository**: no business decisions, no PHP filtering of result sets, no `$data`/`$filters`/`$options`. Explicit parameters. Query Builder or Eloquent **bindings** only. Returns rows/models as stored; mapping to HTTP happens in the service or a dedicated presenter, not in the repository.
- **FormRequest (DTO)**: only HTTP input shape. One class per operation. Never imported by the service or repository.
- Instance + constructor injection. No static `TemplateService::`, no manual singletons. Laravel’s container is the shared-instance mechanism.
- `declare(strict_types=1)`, explicit types, method names include the resource (`createPatient`, not `create`).

---

## Uso de cada capa

| Capa | Hace | No hace |
|---|---|---|
| `routes/web.php` | verb + URI + middleware + FormRequest + controller action | if/else de negocio |
| FormRequest | rules, authorize (permission), `validated()` | SQL, hash, transacciones |
| Controller | `$this->service->…($validated)`; view/JSON/redirect | reglas RN, `DB::`, PDO |
| Service | RN, estados de cita, `DB::transaction`, llama repositories | HTML, `echo`, leer `$request` crudo |
| Repository | `paciente`, `medico`, `cita_medica`, … | `users` de Laravel, arrays en memoria, `clear()` de test |
| Model | clinic table mapping | default Laravel `User` scaffold as source of truth |
| Blade / JS | UI; JS only complements | replace server validation or availability |

Identity JSON (`/auth/*`) and HTML (`/login`, `/panel`) share `IdentitySessionService`. Do not duplicate password/throttle work in a controller.

---

## Logger

Use `Illuminate\Support\Facades\Log` only. No `echo`, `error_log`, `fwrite(STDOUT/STDERR)`, no project `src/Config/Logger.php`.

| Método | Cuándo |
|---|---|
| `Log::info` | flujo normal relevante (no loguear contraseñas ni tokens) |
| `Log::warning` | reintento / degradación |
| `Log::error` | en el `catch` antes de relanzar o responder 5xx |

Do not log SQL, hashes, session IDs or `.env` values. Do not add timers infrastructure unless a CU needs it; if timing is required, `Log::info` with a duration is enough.

---

## Manejo de errores

Do not port `AppError` / `SendError` / `ErrorCodes`. Map domain failures to HTTP **without leaking internals**.

Identity JSON (do not change):

| Situación | HTTP | Cuerpo |
|---|---|---|
| Validación | 422 | JSON de validación de Laravel |
| Credenciales / cuenta no usable | 401 | `{message:"Invalid credentials or account unavailable."}` |
| Sin sesión / inactivo | 401 | `{message:"Unauthenticated."}` |
| CSRF | 419 | `{message:"CSRF token mismatch."}` |
| Throttle login | 429 | `{message:"Too many login attempts. Try again later."}` + `Retry-After` |
| Fallo operativo auth | 503 | `{message:"Unable to complete authentication. Please try again."}` |
| Logout OK | 204 | vacío |

New **HTML** screens: Laravel validation errors + generic flash. Never dump exception text.

New **JSON** (only if a CU is JSON): keep `{message}` consistent with Identity; optional `details` for field lists. Statuses:

| Situación | HTTP |
|---|---|
| Validación | 422 |
| Sin sesión | 401 |
| Sin permiso | 403 |
| No encontrado | 404 |
| Conflicto (horario, duplicado CI) | 409 |
| Regla de negocio | 422 |
| Error interno | 500 `{message}` genérico; el stack solo en `Log::error` |
| DB caída | 503 |

Services throw typed domain exceptions (`PatientDuplicateDocument`, `SlotUnavailable`). Controllers or a module renderer map them. Never put the business key in `Exception::$code`. Never invent codes in the controller.

---

## HTTP — Request, Response y Schema

Do not add `App\Http\Request`, `Response` or `Dto\Schema`. Laravel already provides them.

| Genérico | Aquí |
|---|---|
| `App\Http\Request` | `Illuminate\Http\Request` |
| `$request->validated['body']` | `$request->validated()` after FormRequest |
| `App\Http\Response::json` | `response()->json()` / `view()` / `redirect()` / `response()->noContent()` |
| `Schema::safeParse` | reglas del FormRequest |

Mutations use CSRF (`web` middleware). Protected reads/writes require an active session and a permission check on the server. No Bearer JWT. JS validations never replace the FormRequest.

---

## DTOs — Contratos de entrada

A **DTO is a FormRequest** under `app/Modules/<Mod>/Http/Requests/`.

```
Http/Requests/
├── StorePatientRequest.php
├── UpdatePatientRequest.php
├── SearchPatientsRequest.php
└── ShowPatientRequest.php
```

| Tipo | Fuente | Clase |
|---|---|---|
| Creación | body | `Store<Recurso>Request` |
| Actualización | body | `Update<Recurso>Request` (optional fields; `withValidator` if at least one required) |
| Filtros | query | `Search<Recurso>Request` |
| ID de ruta | route | `Show<Recurso>Request` or route `{paciente}` binding after authorize |

Rules:

- One FormRequest per operation. Do not reuse Store as Update.
- Validate in the FormRequest / `validate()`, never in the service or repository.
- Controller reads only `validated()`. Never `$request->all()` for persistence.
- Do not mix body + query + route into one bag unless Laravel route params are authorized there.
- CI: empty → `null` before save (clinic schema). Do not unique-check empty CI as a duplicate of another empty CI.
- Passwords: never in logs; never truncated; reject >72 bytes for bcrypt like Identity.

---

## Repository

- Explicit parameters: `getPatientByDocument(?string $ci)`, not `getAll($filters)`.
- Reads use prefix `get`. Never in-memory lists or `clear()`/`reset()`.
- No `toXxxResponse` in the repository.
- Table/column names from `database/schema/clinic.sql` (`nombre_usuario`, `contrasena_hash`, `correo_acceso`, …).
- Optional SQL `NULL` filters: pass `null`; do not add `?? null` noise.
- `$row ?? null` is OK when fetching one row.

---

## Service

Class `PatientService`, methods `getPatientById`, `createPatient`, `deactivatePatient`. Checks RN (active doctor, unique CI, no overlap) then calls the repository. Not-found and conflicts throw domain exceptions. Coordinates CU22 before insert/update of `cita_medica`.

---

## Controller

Inject the service. No local error closures. No FormRequest imports of other operations. HTML: return Blade. JSON: only where the CU/contract already is JSON.

### Nombres de handlers — siempre incluir el recurso

The class carries the resource. The method carries the operation. The router points at that method; no aliases (`index`, `store`, `show`, `destroy`).

```php
// MAL — nombres genéricos, ambiguos cuando hay varios controllers
public function getAll(Request $request) {}
public function getById(Request $request) {}
public function create(Request $request) {}
public function update(Request $request) {}
public function remove(Request $request) {}

// BIEN — la clase lleva el recurso; el método, la operación
final class TemplateController
{
    public function getAllTemplates(Request $request) {}
    public function getTemplateById(Request $request) {}
    public function createTemplate(Request $request) {}
    public function updateTemplate(Request $request) {}
    public function deleteTemplate(Request $request) {}
}
```

In this repo the resource is clinical, not `Template`. Same naming, instance injection, no static handlers:

```php
final class PatientController
{
    public function __construct(private PatientService $patients) {}

    public function getAllPatients(SearchPatientsRequest $request) {}
    public function getPatientById(ShowPatientRequest $request) {}
    public function createPatient(StorePatientRequest $request) {}
    public function updatePatient(UpdatePatientRequest $request) {}
    public function deactivatePatient(ShowPatientRequest $request) {}
}

Route::get('/pacientes', [PatientController::class, 'getAllPatients'])->name('patients.index');
Route::post('/pacientes', [PatientController::class, 'createPatient'])->name('patients.store');
```

Same rule on services and repositories: `getAllPatients`, `createPatient`. Never `findAll` / `store`.

---

## Rutas

`app/Modules/<Mod>/routes/web.php`, loaded by `ModuleServiceProvider` if the file exists.

Order: `web` → session/active-user → permission → FormRequest → controller.

```php
Route::middleware(['web', RequireActiveSession::class])->group(function (): void {
    Route::post('/pacientes', [PatientController::class, 'createPatient'])
        ->middleware('can:pacientes.registrar') // or an explicit permission middleware
        ->name('patients.store');
});
```

Do not put business `if` in the route file. Do not register `/` again (Appointments already owns `home`).

---

## Convención de respuestas HTTP

| Operación | HTML | JSON (si el CU es JSON) |
|---|---|---|
| Listar / ver | 200 view | 200 payload del contrato |
| Crear | 302 + flash | 201 |
| Actualizar | 302 + flash | 200 |
| Desactivar | 302 + flash | 200 |
| Logout Identity | 302 `/login` | 204 vacío |

Soft-delete in this domain is **desactivar**, not DELETE of historical rows (RN11).

---

## Idempotency-Key

For write endpoints that require idempotency (programar cita, reprogramar, registrar paciente cuando el cliente reintenta), the key goes in the **header**, never in the body.

```php
// MAL — SendError / ErrorCodes no existen aquí; la key no va en el body
use App\Utils\SendError;
$idempotencyKey = $request->input('idempotency_key');
return SendError::code($response, 'VALIDATION_ERROR', [
    'field' => 'Idempotency-Key',
]);

// BIEN — header only; Laravel 422 del contrato del módulo
$idempotencyKey = $request->header('Idempotency-Key');
if ($idempotencyKey === null || $idempotencyKey === '') {
    return response()->json([
        'message' => 'The Idempotency-Key header is required.',
        'details' => ['field' => 'Idempotency-Key'],
    ], 422);
}
```

Rules:

- Header name: `Idempotency-Key`. `$request->header('idempotency-key')` is the same header (Laravel is case-insensitive). Do not read `$request->input('idempotency_key')` or a form field as the key.
- Do not port `SendError`. Use the module’s `{message}` JSON or a Blade 422.
- The service receives the key as an explicit argument. Same actor + same key + same operation → replay the first result; do not create a second row.
- Distinct keys are distinct attempts. A failed attempt may be retried with a **new** key; do not invent automatic retry.
- HTML forms still use CSRF. If the CU is submitted with `fetch`/XHR, send `Idempotency-Key`. Classic form POST without JS relies on CSRF plus business uniqueness (`ci`, `codigo` de cita, overlap). Do not hide the key in the body to “make forms work”.
- Do not add a global idempotency table until the first write CU that needs replay. Until then, enforce uniqueness inside `DB::transaction`.
- Never log the key next to credentials. Identity login/logout do **not** use Idempotency-Key (throttle + session regenerate already apply).

---

## Constants — nombres de tablas y columnas

Do not catalog SQL function or stored-procedure names. There are no `get_*` functions or `sp_*` routines in `clinic.sql`.

```php
// MAL — el genérico asume funciones/SPs que este schema no tiene
final class Constants
{
    public const GET_ALL_TEMPLATES_FUNC = 'SELECT * FROM get_all_templates(?, ?)';
    public const GET_TEMPLATE_BY_ID_FUNC = 'SELECT * FROM get_template_by_id(?)';
    public const SP_CREATE_TEMPLATE = 'sp_create_template';
    public const SP_UPDATE_TEMPLATE = 'sp_update_template';
    public const SP_DELETE_TEMPLATE = 'sp_delete_template';
}

// BIEN — nombres de tabla/columna de clinic.sql
final class PatientSchema
{
    public const TABLE = 'paciente';
    public const ID = 'id_paciente';
    public const DOCUMENT = 'ci';
    public const FIRST_NAME = 'nombres';
    public const LAST_NAME = 'apellidos';
}
```

The repository is the only layer that uses these constants in queries. Never concatenate a request value into SQL. Prefer Query Builder column names over a second SQL-string catalog.

---

## Singleton

Do not write `instance()`. Bind in a service provider if a shared object is required.

---

## Entorno y datos

- Config via `config/*`. Never `getenv` in modules. Never print env values.
- PostgreSQL only. PDO stays inside Laravel; do not import `PDO` in modules.
- Sessions/cache stay file-based as already configured. Do not add framework migration tables.

---

## Decision Gates

| Need | Action |
|---|---|
| New CU | Folders only for that CU under the approved module |
| HTML | Blade + FormRequest + service |
| Persist | Repository + `clinic.sql` names |
| Availability | Service transaction + CU22 check; showing a slot does not reserve it |
| Generic skill vs this file | This file wins |

## Execution Steps

1. Read `AGENTS.md`, matching Laravel Boost rules, and this skill.
2. Confirm CU + module. Stop if the folder would be empty.
3. FormRequest → Controller → Service → Repository → `routes/web.php`.
4. `vendor/bin/phpunit --do-not-cache-result <path>`, then the DB-independent suite.

## Output Contract

Files touched, module, CU, tests run, and any generic-skill item rejected (JWT, SPs, `ErrorCodes`, …).

## References

- `skills/php/references/architecture.md`
- `AGENTS.md`
- `app/Modules/Identity/README.md`
- `database/schema/clinic.sql`
