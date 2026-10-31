# PHP skill — mapeo a este repo

Origen: `/home/agent/skill-generic/php`. Las secciones viven en `skills/php/SKILL.md` (estructura, patrón, capas, logger, errores, HTTP, DTOs/FormRequest, repository, service, controller, rutas, respuestas).
Ese archivo genérico describe otro stack (`BACKEND/src`, JWT, `Postgres.php`, stored procedures, `ErrorCodes`).
**No copiar esa estructura.** Aquí rige el informe ciclo 2 y `AGENTS.md`.

## Qué se conserva

- Controller sin reglas de negocio.
- Service con las reglas; no pinta HTML ni arma SQL.
- Repository único acceso a PostgreSQL; parámetros explícitos; sin arrays `$data`/`$filters`.
- Consultas parametrizadas (Query Builder / Eloquent bindings). Nunca concatenar input en SQL.
- Errores de negocio con tipo/excepción; no volcar stack, SQL ni secretos al cliente.
- `declare(strict_types=1)`, tipos explícitos, nombres de handler con el recurso (`createPatient`, no `create` / `store`).
- Validación de entrada antes del service (FormRequest o `validate()`).
- Idempotencia en header `Idempotency-Key`, nunca en el body; no portar `SendError`.
- Constants = nombres de `clinic.sql`, no `GET_*_FUNC` ni `SP_*`.

## Qué cambia

| Genérico | Clínica Vida Salud |
|---|---|
| `BACKEND/src/{Controllers,Services,Repositories}` | `app/Modules/{Identity,Patients,MedicalStaff,Schedules,Appointments}` |
| Router + DTO custom + JWT | Rutas `web.php` del módulo, middleware `web`, sesión + CSRF |
| `src/Db/Postgres.php` + SPs | Eloquent / Query Builder sobre el schema de 17 tablas |
| `ErrorCodes` + `SendError` | Contrato del módulo (Identity ya usa `{message}`); Blade para pantallas |
| `Logger` propio | `Log` de Laravel; no `echo`/`error_log` |
| `Env.php` | `config/*`; no leer `.env` mientras el guardia de bootstrap esté activo |
| API JSON única | Blade + HTML/CSS/JS; JSON solo donde el CU/contrato ya lo define |
| Módulos vacíos / `artisan make` de scaffold | No crear carpetas ni tablas Laravel (`users`, `cache`) no aprobadas |

Módulos aprobados: Identity, Patients, MedicalStaff, Schedules, Appointments.
No crear un módulo hasta implementar su CU. Appointments hoy solo sirve `GET /`.

Ver `README.md`, `AGENTS.md`, `app/Modules/Identity/README.md` y el informe en `Document/`.
