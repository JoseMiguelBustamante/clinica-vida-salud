# Appointments (CU08–CU16 and CU22)

Owns the public `GET /` landing page and the appointment workflow through
administrative reports.

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
not an error.

CU15 (`GET /historial`, `citas.historial`) is a read-only patient history for
Recepcionista and Administrador. Search by CI or name, then select the patient.
An optional estado filter narrows the list; an empty filter returns the complete
history, including finished visits. Empty results are not an error. Clinical notes
and diagnoses are omitted.

CU20 (`GET /historial-medico`, `historial.consultar`) is a read-only portal
consult for Paciente. It requires an approved `paciente.id_usuario` link. The
link does not grant diagnoses or professional notes. Empty authorized results
are not an error and do not mean the patient has no clinical records. A
retrieval failure is not shown as that empty state. English `/medical-history`
stays 404.

CU16 (`GET /reportes`, `reportes.generar`) is read-only for Administrador. The
available reports are appointments on a date, by physician, specialty or status,
registered patients, period totals of Atendida/Cancelada/Inasistencia, and each
physician's daily agenda. Empty results are not an error. A processing failure
is not shown as an empty list or a total of zero. CSV/PDF export is not
implemented. Clinical notes and diagnoses are omitted.

Disabling reminder preferences (CU18) discards `Pendiente` and `EnProceso`
rows for appointments linked through `paciente.id_usuario`. Rows already
accepted by the provider are left unchanged.

HTML forms use CSRF, not Idempotency-Key. English `/appointments` stays 404.
The physician account must be linked on the physician record (`id_usuario`).
