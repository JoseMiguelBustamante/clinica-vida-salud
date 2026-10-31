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
    public function getOccupyingAppointments(int $physicianId, string $rangeStart, string $rangeEnd): Collection
    {
        return DB::table('cita_medica')
            ->select('id_cita', 'inicio', 'fin', 'estado')
            ->where('id_medico', $physicianId)
            ->whereIn('estado', ['Programada', 'Confirmada'])
            ->where('inicio', '<', $rangeEnd)
            ->where('fin', '>', $rangeStart)
            ->orderBy('inicio')
            ->get();
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
                'cita_medica.modalidad',
                'cita_medica.estado',
                'cita_medica.fecha_creacion',
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
