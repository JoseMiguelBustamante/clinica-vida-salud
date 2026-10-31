# Appointments (CU08–CU11 and CU22)

Owns the public `GET /` landing page, availability lookup, booking,
rescheduling and cancellation.

CU08 (`GET /disponibilidad`) requires `citas.consultar`. Showing a slot does
not reserve it.

CU09 (`GET /citas/programar`, `POST /citas`) requires `citas.programar`. The
service locks the physician (`pg_advisory_xact_lock(847201, id_medico)`), runs
CU22 and inserts `cita_medica` as Programada with a unique `codigo`. HTML forms
use CSRF, not Idempotency-Key. Booking does not confirm attendance.

CU10 (`GET /citas`, `GET|POST /citas/{cita}/reprogramar`) requires
`citas.reprogramar`. The same row keeps its `codigo`. CU22 excludes that
appointment from overlap. Programada stays Programada; Confirmada returns to
Programada and needs a new confirmation. A failed attempt leaves interval and
state untouched.

CU11 (`GET|POST /citas/{cita}/cancelar`) requires `citas.cancelar`. Programada
and Confirmada become Cancelada, keep the row, free the interval and discard
pending reminders (`Pendiente`/`EnProceso` → `Descartado`). Atendida, Cancelada
and Inasistencia cannot be cancelled. Confirmation and attendance (CU12) are
not implemented.

CU22 is `AvailabilityService::validateRequestedSlot`. English `/appointments`
stays 404.
