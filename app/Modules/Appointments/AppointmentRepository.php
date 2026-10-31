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
}
