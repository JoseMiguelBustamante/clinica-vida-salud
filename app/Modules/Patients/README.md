# Patient records (CU04) and declared medical information (CU19)

HTML administration of `paciente` identification and contact data. Session
required plus `pacientes.gestionar`. Empty CI is stored as SQL `NULL` and is
not treated as a duplicate of another empty CI. Names are not unique.
Deactivation keeps related appointment rows. An optional portal account
(`paciente.id_usuario`, role Paciente) is the approved link used by CU19.

| Request | Response |
| --- | --- |
| `GET /pacientes` | list/search by CI, given name or family name |
| `GET /pacientes/registrar` + `POST /pacientes` | register (always active) |
| `GET /pacientes/{id}` | consult |
| `GET /pacientes/{id}/editar` + `POST /pacientes/{id}` | update |
| `POST /pacientes/{id}/desactivar` / `activar` | change state without deleting history |
| `GET /informacion-medica` + `POST /informacion-medica` | own declared medical data (`informacion.declarar`) |

CU19 is the patient's declaration on `informacion_medica`. Format checks do not
prove clinical truth and do not change `registro_atencion` or diagnoses.
English `/medical-information` stays 404. A missing approved link is E2, not an
empty declaration. A retrieval failure is not shown as "no data".

Do not send Idempotency-Key on these HTML forms. Staff appointment history (CU15)
and portal clinical history (CU20) live in Appointments. Patient portal
registration (CU17) and profile preferences (CU18) live in Identity and do not
approve a link to a patient record.
