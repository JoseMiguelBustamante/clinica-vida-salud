# Patient records (CU04)

HTML administration of `paciente` identification and contact data. Session
required plus `pacientes.gestionar`. Empty CI is stored as SQL `NULL` and is
not treated as a duplicate of another empty CI. Names are not unique.
Deactivation keeps related appointment rows.

| Request | Response |
| --- | --- |
| `GET /pacientes` | list/search by CI, given name or family name |
| `GET /pacientes/registrar` + `POST /pacientes` | register (always active) |
| `GET /pacientes/{id}` | consult |
| `GET /pacientes/{id}/editar` + `POST /pacientes/{id}` | update |
| `POST /pacientes/{id}/desactivar` / `activar` | change state without deleting history |

Do not send Idempotency-Key on these HTML forms. CU15 history and CU17 account
linking are not part of this module.
