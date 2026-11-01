<?php

declare(strict_types=1);

namespace App\Modules\Appointments;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AppointmentRepository
{
    /** @return Collection<int, object> */
    public function getActivePhysiciansForSpecialty(int $specialtyId): Collection
    {
        return DB::table('medico')
            ->join('medico_especialidad', 'medico_especialidad.id_medico', '=', 'medico.id_medico')
            ->select('medico.id_medico', 'medico.nombres', 'medico.apellidos', 'medico.registro_profesional', 'medico.activo')
            ->where('medico.activo', true)
            ->where('medico_especialidad.id_especialidad', $specialtyId)
            ->orderBy('medico.apellidos')
            ->orderBy('medico.nombres')
            ->get();
    }

    public function physicianAttendsSpecialty(int $physicianId, int $specialtyId): bool
    {
        return DB::table('medico_especialidad')
            ->where('id_medico', $physicianId)
            ->where('id_especialidad', $specialtyId)
            ->exists();
    }

    /** @return Collection<int, object> */
    public function getActiveSchedulesForPhysicianOnDay(int $physicianId, string $weekday): Collection
    {
        return DB::table('horario_atencion')
            ->select('id_horario', 'dia_semana', 'hora_inicio', 'hora_fin', 'duracion_cita_minutos', 'activo', 'id_medico')
            ->where('id_medico', $physicianId)
            ->where('dia_semana', $weekday)
            ->where('activo', true)
            ->orderBy('hora_inicio')
            ->get();
    }

    /** @return Collection<int, object> */
    public function getOccupyingAppointments(
        int $physicianId,
        string $rangeStart,
        string $rangeEnd,
        ?int $exceptAppointmentId = null,
    ): Collection {
        $query = DB::table('cita_medica')
            ->select('id_cita', 'inicio', 'fin', 'estado')
            ->where('id_medico', $physicianId)
            ->whereIn('estado', ['Programada', 'Confirmada'])
            ->where('inicio', '<', $rangeEnd)
            ->where('fin', '>', $rangeStart);
        if ($exceptAppointmentId !== null) {
            $query->where('id_cita', '!=', $exceptAppointmentId);
        }

        return $query->orderBy('inicio')->get();
    }

    /** @return Collection<int, object> */
    public function getAllAppointments(?string $fragment, ?string $onDate = null, ?string $status = null): Collection
    {
        $query = DB::table('cita_medica')
            ->join('paciente', 'paciente.id_paciente', '=', 'cita_medica.id_paciente')
            ->join('medico', 'medico.id_medico', '=', 'cita_medica.id_medico')
            ->join('especialidad', 'especialidad.id_especialidad', '=', 'cita_medica.id_especialidad')
            ->select(
                'cita_medica.id_cita',
                'cita_medica.codigo',
                'cita_medica.inicio',
                'cita_medica.fin',
                'cita_medica.estado',
                'cita_medica.modalidad',
                'paciente.nombres as paciente_nombres',
                'paciente.apellidos as paciente_apellidos',
                'paciente.ci as paciente_ci',
                'medico.nombres as medico_nombres',
                'medico.apellidos as medico_apellidos',
                'especialidad.nombre as especialidad_nombre',
            )
            ->orderByDesc('cita_medica.inicio');

        if ($fragment !== null && $fragment !== '') {
            $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $fragment).'%';
            $query->where(function ($inner) use ($like): void {
                $inner->whereRaw('cita_medica.codigo ILIKE ? ESCAPE \'!\'', [$like])
                    ->orWhereRaw('paciente.ci ILIKE ? ESCAPE \'!\'', [$like])
                    ->orWhereRaw('paciente.nombres ILIKE ? ESCAPE \'!\'', [$like])
                    ->orWhereRaw('paciente.apellidos ILIKE ? ESCAPE \'!\'', [$like])
                    ->orWhereRaw('medico.nombres ILIKE ? ESCAPE \'!\'', [$like])
                    ->orWhereRaw('medico.apellidos ILIKE ? ESCAPE \'!\'', [$like]);
            });
        }
        if ($onDate !== null && $onDate !== '') {
            $query->whereDate('cita_medica.inicio', $onDate);
        }
        if ($status !== null && $status !== '') {
            $query->where('cita_medica.estado', $status);
        }

        return $query->get();
    }

    public function updateAppointmentSchedule(int $id, string $start, string $end, string $status): void
    {
        DB::table('cita_medica')->where('id_cita', $id)->update([
            'inicio' => $start,
            'fin' => $end,
            'estado' => $status,
        ]);
    }

    public function cancelAppointment(int $id, ?string $reason): void
    {
        DB::table('cita_medica')->where('id_cita', $id)->update([
            'estado' => 'Cancelada',
            'motivo_cancelacion' => $reason,
        ]);
    }

    public function discardPendingReminders(int $appointmentId): void
    {
        DB::table('recordatorio_cita')
            ->where('id_cita', $appointmentId)
            ->whereIn('estado', ['Pendiente', 'EnProceso'])
            ->update(['estado' => 'Descartado']);
    }

    public function discardUndeliveredRemindersForAccount(int $userId): void
    {
        DB::table('recordatorio_cita')
            ->whereIn('estado', ['Pendiente', 'EnProceso'])
            ->whereIn('id_cita', function ($query) use ($userId): void {
                $query->select('cita_medica.id_cita')
                    ->from('cita_medica')
                    ->join('paciente', 'paciente.id_paciente', '=', 'cita_medica.id_paciente')
                    ->where('paciente.id_usuario', $userId);
            })
            ->update(['estado' => 'Descartado']);
    }

    public function createReminder(string $scheduledAt, int $appointmentId): int
    {
        return (int) DB::table('recordatorio_cita')->insertGetId([
            'fecha_programada' => $scheduledAt,
            'fecha_intento' => null,
            'estado' => 'Pendiente',
            'referencia_envio' => null,
            'id_cita' => $appointmentId,
        ], 'id_recordatorio');
    }

    public function hasUndeliveredReminder(int $appointmentId): bool
    {
        return DB::table('recordatorio_cita')
            ->where('id_cita', $appointmentId)
            ->whereIn('estado', ['Pendiente', 'EnProceso'])
            ->exists();
    }

    public function appointmentWasOriginallyEligibleForReminders(int $appointmentId): bool
    {
        return DB::table('recordatorio_cita')->where('id_cita', $appointmentId)->exists()
            || DB::table('evento_cita')
                ->where('id_cita', $appointmentId)
                ->where('tipo', AppointmentReminderService::PLANNING_INCIDENT_TYPE)
                ->exists();
    }

    public function getNotificationPreferencesForPatient(int $patientId): ?object
    {
        return DB::table('paciente')
            ->join('preferencias_notificacion', 'preferencias_notificacion.id_usuario', '=', 'paciente.id_usuario')
            ->select(
                'preferencias_notificacion.id_usuario',
                'preferencias_notificacion.recordatorios_habilitados',
                'preferencias_notificacion.canal',
                'preferencias_notificacion.destino',
                'preferencias_notificacion.destino_verificado',
            )
            ->where('paciente.id_paciente', $patientId)
            ->first();
    }

    /** @return Collection<int, object> */
    public function getDuePendingReminders(string $now): Collection
    {
        return DB::table('recordatorio_cita')
            ->select('id_recordatorio', 'fecha_programada', 'fecha_intento', 'estado', 'referencia_envio', 'id_cita')
            ->where('estado', 'Pendiente')
            ->where('fecha_programada', '<=', $now)
            ->orderBy('fecha_programada')
            ->orderBy('id_recordatorio')
            ->get();
    }

    public function claimReminder(int $id, string $attemptedAt): bool
    {
        return DB::table('recordatorio_cita')
            ->where('id_recordatorio', $id)
            ->where('estado', 'Pendiente')
            ->update([
                'estado' => 'EnProceso',
                'fecha_intento' => $attemptedAt,
            ]) > 0;
    }

    public function discardReminder(int $id): void
    {
        DB::table('recordatorio_cita')
            ->where('id_recordatorio', $id)
            ->whereIn('estado', ['Pendiente', 'EnProceso'])
            ->update(['estado' => 'Descartado']);
    }

    public function recordReminderResult(int $id, string $status, ?string $reference, string $attemptedAt): void
    {
        DB::table('recordatorio_cita')
            ->where('id_recordatorio', $id)
            ->where('estado', 'EnProceso')
            ->update([
                'estado' => $status,
                'referencia_envio' => $reference,
                'fecha_intento' => $attemptedAt,
            ]);
    }

    public function getReminderDispatchContext(int $reminderId): ?object
    {
        return DB::table('recordatorio_cita')
            ->join('cita_medica', 'cita_medica.id_cita', '=', 'recordatorio_cita.id_cita')
            ->join('paciente', 'paciente.id_paciente', '=', 'cita_medica.id_paciente')
            ->join('medico', 'medico.id_medico', '=', 'cita_medica.id_medico')
            ->join('especialidad', 'especialidad.id_especialidad', '=', 'cita_medica.id_especialidad')
            ->leftJoin('preferencias_notificacion', 'preferencias_notificacion.id_usuario', '=', 'paciente.id_usuario')
            ->select(
                'recordatorio_cita.id_recordatorio',
                'recordatorio_cita.fecha_programada',
                'recordatorio_cita.id_cita',
                'cita_medica.codigo',
                'cita_medica.inicio',
                'cita_medica.fin',
                'cita_medica.estado',
                'cita_medica.modalidad',
                'cita_medica.id_paciente',
                'paciente.nombres as paciente_nombres',
                'paciente.apellidos as paciente_apellidos',
                'medico.nombres as medico_nombres',
                'medico.apellidos as medico_apellidos',
                'especialidad.nombre as especialidad_nombre',
                'preferencias_notificacion.recordatorios_habilitados',
                'preferencias_notificacion.canal',
                'preferencias_notificacion.destino',
                'preferencias_notificacion.destino_verificado',
            )
            ->where('recordatorio_cita.id_recordatorio', $reminderId)
            ->first();
    }

    public function confirmAppointment(int $id): void
    {
        DB::table('cita_medica')->where('id_cita', $id)->update(['estado' => 'Confirmada']);
    }

    public function registerAppointmentArrival(int $id, string $arrivedAt): void
    {
        DB::table('cita_medica')->where('id_cita', $id)->update(['fecha_hora_llegada' => $arrivedAt]);
    }

    public function registerAppointmentAbsence(int $id): void
    {
        DB::table('cita_medica')->where('id_cita', $id)->update(['estado' => 'Inasistencia']);
    }

    public function getPhysicianByUserId(int $userId): ?object
    {
        return DB::table('medico')
            ->select('id_medico', 'registro_profesional', 'nombres', 'apellidos', 'activo', 'id_usuario')
            ->where('id_usuario', $userId)
            ->first();
    }

    public function getRegisteredPhysicianById(int $id): ?object
    {
        return DB::table('medico')
            ->select('id_medico', 'registro_profesional', 'nombres', 'apellidos', 'activo', 'id_usuario')
            ->where('id_medico', $id)
            ->first();
    }

    /** @return Collection<int, object> */
    public function getRegisteredPhysicians(): Collection
    {
        return DB::table('medico')
            ->select('id_medico', 'registro_profesional', 'nombres', 'apellidos', 'activo')
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->get();
    }

    /** @return Collection<int, object> */
    public function getActivePhysicians(): Collection
    {
        return DB::table('medico')
            ->select('id_medico', 'registro_profesional', 'nombres', 'apellidos', 'activo')
            ->where('activo', true)
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->get();
    }

    /** @return Collection<int, object> */
    public function getAppointmentsForPhysicianOnDate(
        int $physicianId,
        string $rangeStart,
        string $rangeEnd,
        ?string $status,
    ): Collection {
        $query = DB::table('cita_medica')
            ->join('paciente', 'paciente.id_paciente', '=', 'cita_medica.id_paciente')
            ->join('medico', 'medico.id_medico', '=', 'cita_medica.id_medico')
            ->join('especialidad', 'especialidad.id_especialidad', '=', 'cita_medica.id_especialidad')
            ->select(
                'cita_medica.id_cita',
                'cita_medica.codigo',
                'cita_medica.inicio',
                'cita_medica.fin',
                'cita_medica.estado',
                'cita_medica.modalidad',
                'cita_medica.motivo_consulta',
                'paciente.nombres as paciente_nombres',
                'paciente.apellidos as paciente_apellidos',
                'paciente.ci as paciente_ci',
                'medico.nombres as medico_nombres',
                'medico.apellidos as medico_apellidos',
                'especialidad.nombre as especialidad_nombre',
            )
            ->where('cita_medica.id_medico', $physicianId)
            ->where('cita_medica.inicio', '>=', $rangeStart)
            ->where('cita_medica.inicio', '<', $rangeEnd)
            ->orderBy('cita_medica.inicio');

        if ($status !== null && $status !== '') {
            $query->where('cita_medica.estado', $status);
        }

        return $query->get();
    }

    /** @return Collection<int, object> */
    public function getAppointmentsForPatient(int $patientId, ?string $status): Collection
    {
        $query = DB::table('cita_medica')
            ->join('paciente', 'paciente.id_paciente', '=', 'cita_medica.id_paciente')
            ->join('medico', 'medico.id_medico', '=', 'cita_medica.id_medico')
            ->join('especialidad', 'especialidad.id_especialidad', '=', 'cita_medica.id_especialidad')
            ->select(
                'cita_medica.id_cita',
                'cita_medica.codigo',
                'cita_medica.inicio',
                'cita_medica.fin',
                'cita_medica.estado',
                'cita_medica.modalidad',
                'paciente.nombres as paciente_nombres',
                'paciente.apellidos as paciente_apellidos',
                'paciente.ci as paciente_ci',
                'medico.nombres as medico_nombres',
                'medico.apellidos as medico_apellidos',
                'especialidad.nombre as especialidad_nombre',
            )
            ->where('cita_medica.id_paciente', $patientId)
            ->orderByDesc('cita_medica.inicio');

        if ($status !== null && $status !== '') {
            $query->where('cita_medica.estado', $status);
        }

        return $query->get();
    }

    /** @return Collection<int, object> */
    public function getAppointmentsInRange(
        string $rangeStart,
        string $rangeEnd,
        ?int $physicianId,
        ?int $specialtyId,
        ?string $status,
    ): Collection {
        $query = DB::table('cita_medica')
            ->join('paciente', 'paciente.id_paciente', '=', 'cita_medica.id_paciente')
            ->join('medico', 'medico.id_medico', '=', 'cita_medica.id_medico')
            ->join('especialidad', 'especialidad.id_especialidad', '=', 'cita_medica.id_especialidad')
            ->select(
                'cita_medica.id_cita',
                'cita_medica.codigo',
                'cita_medica.inicio',
                'cita_medica.fin',
                'cita_medica.estado',
                'cita_medica.modalidad',
                'cita_medica.id_medico',
                'paciente.nombres as paciente_nombres',
                'paciente.apellidos as paciente_apellidos',
                'paciente.ci as paciente_ci',
                'medico.nombres as medico_nombres',
                'medico.apellidos as medico_apellidos',
                'especialidad.nombre as especialidad_nombre',
            )
            ->where('cita_medica.inicio', '>=', $rangeStart)
            ->where('cita_medica.inicio', '<', $rangeEnd)
            ->orderBy('medico.apellidos')
            ->orderBy('medico.nombres')
            ->orderBy('cita_medica.inicio');

        if ($physicianId !== null) {
            $query->where('cita_medica.id_medico', $physicianId);
        }
        if ($specialtyId !== null) {
            $query->where('cita_medica.id_especialidad', $specialtyId);
        }
        if ($status !== null && $status !== '') {
            $query->where('cita_medica.estado', $status);
        }

        return $query->get();
    }

    /** @return Collection<int, object> */
    public function getAppointmentStatusCountsInRange(string $rangeStart, string $rangeEnd): Collection
    {
        return DB::table('cita_medica')
            ->select('cita_medica.estado', DB::raw('count(*) as total'))
            ->where('cita_medica.inicio', '>=', $rangeStart)
            ->where('cita_medica.inicio', '<', $rangeEnd)
            ->whereIn('cita_medica.estado', ['Atendida', 'Cancelada', 'Inasistencia'])
            ->groupBy('cita_medica.estado')
            ->get();
    }

    public function markAppointmentAttended(int $id, string $recordedAt): void
    {
        DB::table('cita_medica')->where('id_cita', $id)->update([
            'estado' => 'Atendida',
            'fecha_hora_registro_atencion' => $recordedAt,
        ]);
    }

    /**
     * @param  array{fecha_registro: string, resumen_atencion: string, diagnostico: ?string, indicaciones: ?string, id_cita: int, id_medico: int}  $values
     */
    public function createCareRecord(array $values): void
    {
        DB::table('registro_atencion')->insert($values);
    }

    public function lockPhysicianAppointments(int $physicianId): void
    {
        DB::select('SELECT pg_advisory_xact_lock(847201, ?)', [$physicianId]);
    }

    public function appointmentCodeTaken(string $code): bool
    {
        return DB::table('cita_medica')->where('codigo', $code)->exists();
    }

    /**
     * @param  array{codigo: string, inicio: string, fin: string, motivo_consulta: ?string, modalidad: string, estado: string, fecha_creacion: string, id_paciente: int, id_medico: int, id_especialidad: int}  $values
     */
    public function createAppointment(array $values): int
    {
        return (int) DB::table('cita_medica')->insertGetId($values, 'id_cita');
    }

    /**
     * @param  array{tipo: string, fecha_hora: string, motivo: ?string, id_cita: int, id_usuario: int}  $values
     */
    public function createAppointmentEvent(array $values): void
    {
        DB::table('evento_cita')->insert($values);
    }

    public function getAppointmentById(int $id): ?object
    {
        return DB::table('cita_medica')
            ->join('paciente', 'paciente.id_paciente', '=', 'cita_medica.id_paciente')
            ->join('medico', 'medico.id_medico', '=', 'cita_medica.id_medico')
            ->join('especialidad', 'especialidad.id_especialidad', '=', 'cita_medica.id_especialidad')
            ->select(
                'cita_medica.id_cita',
                'cita_medica.codigo',
                'cita_medica.inicio',
                'cita_medica.fin',
                'cita_medica.motivo_consulta',
                'cita_medica.motivo_cancelacion',
                'cita_medica.modalidad',
                'cita_medica.estado',
                'cita_medica.fecha_creacion',
                'cita_medica.fecha_hora_llegada',
                'cita_medica.fecha_hora_registro_atencion',
                'cita_medica.id_paciente',
                'cita_medica.id_medico',
                'cita_medica.id_especialidad',
                'paciente.nombres as paciente_nombres',
                'paciente.apellidos as paciente_apellidos',
                'paciente.ci as paciente_ci',
                'medico.nombres as medico_nombres',
                'medico.apellidos as medico_apellidos',
                'medico.registro_profesional',
                'especialidad.nombre as especialidad_nombre',
            )
            ->where('cita_medica.id_cita', $id)
            ->first();
    }
}
