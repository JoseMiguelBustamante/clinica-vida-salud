# Schedules (CU07)

HTML configuration of `horario_atencion` for each physician: weekday, start,
end and appointment duration. Session required plus `horarios.gestionar`
(Administrador). Days use the schema values without accents (`Miercoles`,
`Sabado`). Duration must be positive and divide the window into complete
slots. Active fringes of the same physician cannot overlap. Deactivation
keeps existing appointments. Shrinking or moving a fringe is refused when
Programada or Confirmada appointments would fall outside.

Do not send Idempotency-Key on these HTML forms. CU08 availability and CU09
booking are not part of this module.
