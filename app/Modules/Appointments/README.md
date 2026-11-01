# Appointments (CU08–CU13 and CU22)

Owns the public `GET /` landing page and the appointment workflow through
marking a visit as attended.

CU08 consults availability (`citas.consultar`). Showing a slot does not reserve
it. CU09 books (`citas.programar`) as Programada. CU10 reschedules the same
row (`citas.reprogramar`). CU11 cancels (`citas.cancelar`) to Cancelada and
discards pending reminders. CU12 (`citas.asistencia`) records confirmation,
arrival and Inasistencia.

CU13 (`GET /agenda`, `GET|POST /citas/{cita}/atender`) requires `citas.atender`:
- Actor: Médico, on appointments of the physician linked to the signed-in account.
- Programada or Confirmada → Atendida. Already Atendida is not repeated.
- Cancelada and Inasistencia cannot be marked attended.
- Arrival or confirmation does not substitute medical care.
- Writes `registro_atencion` (resumen required by schema; diagnosis optional),
  `fecha_hora_registro_atencion`, event `Atencion`, and discards pending reminders.

HTML forms use CSRF, not Idempotency-Key. English `/appointments` stays 404.
The physician account must be linked on the physician record (`id_usuario`).
