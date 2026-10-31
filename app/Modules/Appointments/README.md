# Appointments (CU08 and CU22)

Owns the public `GET /` landing page and the HTML availability lookup.

CU08 (`GET /disponibilidad`) requires a session plus `citas.consultar`. The
receptionist selects an active specialty, an active physician who attends it,
and a date that is not in the past. The page lists complete free intervals
from `horario_atencion` minus Programada/Confirmada rows. Showing a slot does
not reserve it. Booking (CU09) is not implemented.

CU22 is `AvailabilityService::validateRequestedSlot`. It is not a screen and
does not insert `cita_medica`.
