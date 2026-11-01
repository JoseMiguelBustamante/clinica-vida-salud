# Medical staff (CU05 and CU06)

HTML administration of `especialidad`, `medico` and `medico_especialidad`.
Session required. `especialidades.gestionar` covers the specialty catalog;
`medicos.gestionar` covers physicians. A physician must keep at least one
specialty. Inactive specialties are not offered for new associations.
Deactivation does not delete historical appointment rows.

Specialty names are trimmed and collapsed to a single space; uniqueness is
case-insensitive. Professional license uniqueness is exact.

Do not send Idempotency-Key on these HTML forms. Optional account linking
(`id_usuario`) associates a Médico login with the physician row so CU13 can
open that physician's agenda. Schedules (CU07) live in the Schedules module.
