# Appointments (CU08, CU09 and CU22)

Owns the public `GET /` landing page, the HTML availability lookup and booking.

CU08 (`GET /disponibilidad`) requires a session plus `citas.consultar`. The
receptionist selects an active specialty, an active physician who attends it,
and a date that is not in the past. The page lists complete free intervals
from `horario_atencion` minus Programada/Confirmada rows. Showing a slot does
not reserve it.

CU09 (`GET /citas/programar`, `POST /citas`) requires `citas.programar`. The
service locks the physician (`pg_advisory_xact_lock(847201, id_medico)`), runs
CU22 and inserts `cita_medica` as Programada with a unique `codigo`. HTML forms
use CSRF, not Idempotency-Key. Booking does not confirm attendance.

CU22 is `AvailabilityService::validateRequestedSlot`. It is not a screen and
does not insert `cita_medica`. English `/appointments` stays 404.
