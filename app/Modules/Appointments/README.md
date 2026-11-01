# Appointments (CU08–CU12 and CU22)

Owns the public `GET /` landing page and the appointment workflow through
confirmation, arrival and no-show.

CU08 consults availability (`citas.consultar`). Showing a slot does not reserve
it. CU09 books (`citas.programar`) as Programada. CU10 reschedules the same
row (`citas.reprogramar`). CU11 cancels (`citas.cancelar`) to Cancelada and
discards pending reminders.

CU12 (`GET /citas/{cita}/asistencia`) requires `citas.asistencia`:
- Confirmación: Programada → Confirmada. Already Confirmada is not repeated.
- Llegada: stores `fecha_hora_llegada` without changing the appointment to
  Atendida.
- Inasistencia: only after the scheduled end, with verified no-show and no
  recorded arrival. Schema value `Inasistencia`. Time does not change the
  state by itself.

HTML forms use CSRF, not Idempotency-Key. Marking attended (CU13) is not
implemented. English `/appointments` stays 404.
