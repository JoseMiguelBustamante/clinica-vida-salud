# Appointments (CU08–CU14 and CU22)

Owns the public `GET /` landing page and the appointment workflow through
physician agenda consultation.

CU08 consults availability (`citas.consultar`). Showing a slot does not reserve
it. CU09 books (`citas.programar`) as Programada. CU10 reschedules the same
row (`citas.reprogramar`). CU11 cancels (`citas.cancelar`) to Cancelada and
discards pending reminders. CU12 (`citas.asistencia`) records confirmation,
arrival and Inasistencia.

CU13 (`GET|POST /citas/{cita}/atender`) requires `citas.atender`. The physician
marks Programada or Confirmada as Atendida on their linked agenda.

CU14 (`GET /agenda`, `citas.agenda`) is a read-only agenda by physician, date and
optional state. The Médico sees only their linked agenda. Recepcionista and
Administrador select a registered physician. Results are ordered by start time.
The consult reason is shown only when the actor may view it. Empty results are
not an error. Patient history (CU15) is not implemented.

HTML forms use CSRF, not Idempotency-Key. English `/appointments` stays 404.
The physician account must be linked on the physician record (`id_usuario`).
