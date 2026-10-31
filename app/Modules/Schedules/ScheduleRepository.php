<?php

declare(strict_types=1);

namespace App\Modules\Schedules;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ScheduleRepository
{
    /** @return Collection<int, object> */
    public function getAllSchedules(?string $fragment): Collection
    {
        $query = DB::table('horario_atencion')
            ->join('medico', 'medico.id_medico', '=', 'horario_atencion.id_medico')
            ->select(
                'horario_atencion.id_horario',
                'horario_atencion.dia_semana',
                'horario_atencion.hora_inicio',
                'horario_atencion.hora_fin',
                'horario_atencion.duracion_cita_minutos',
                'horario_atencion.activo',
                'horario_atencion.id_medico',
                'medico.nombres',
                'medico.apellidos',
                'medico.registro_profesional',
            )
            ->orderBy('medico.apellidos')
            ->orderBy('medico.nombres')
            ->orderByRaw("array_position(ARRAY['Lunes','Martes','Miercoles','Jueves','Viernes','Sabado','Domingo']::varchar[], horario_atencion.dia_semana)")
            ->orderBy('horario_atencion.hora_inicio');

        if ($fragment !== null && $fragment !== '') {
            $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $fragment).'%';
            $query->where(function ($inner) use ($like): void {
                $inner->whereRaw('medico.nombres ILIKE ? ESCAPE \'!\'', [$like])
                    ->orWhereRaw('medico.apellidos ILIKE ? ESCAPE \'!\'', [$like])
                    ->orWhereRaw('medico.registro_profesional ILIKE ? ESCAPE \'!\'', [$like])
                    ->orWhereRaw('horario_atencion.dia_semana ILIKE ? ESCAPE \'!\'', [$like]);
            });
        }

        return $query->get();
    }

    public function getScheduleById(int $id): ?object
    {
        return DB::table('horario_atencion')
            ->join('medico', 'medico.id_medico', '=', 'horario_atencion.id_medico')
            ->select(
                'horario_atencion.id_horario',
                'horario_atencion.dia_semana',
                'horario_atencion.hora_inicio',
                'horario_atencion.hora_fin',
                'horario_atencion.duracion_cita_minutos',
                'horario_atencion.activo',
                'horario_atencion.id_medico',
                'medico.nombres',
                'medico.apellidos',
                'medico.registro_profesional',
            )
            ->where('horario_atencion.id_horario', $id)
            ->first();
    }

    public function lockPhysicianSchedules(int $physicianId): void
    {
        DB::select('SELECT pg_advisory_xact_lock(847201, ?)', [$physicianId]);
    }

    public function hasOverlappingActiveSchedule(
        int $physicianId,
        string $day,
        string $start,
        string $end,
        ?int $exceptId = null,
    ): bool {
        $query = DB::table('horario_atencion')
            ->where('id_medico', $physicianId)
            ->where('dia_semana', $day)
            ->where('activo', true)
            ->where('hora_inicio', '<', $end)
            ->where('hora_fin', '>', $start);

        if ($exceptId !== null) {
            $query->where('id_horario', '!=', $exceptId);
        }

        return $query->exists();
    }

    public function scheduledAppointmentsWouldFallOutside(
        int $physicianId,
        string $currentDay,
        string $currentStart,
        string $currentEnd,
        string $proposedDay,
        string $proposedStart,
        string $proposedEnd,
    ): bool {
        $currentDow = $this->isoDow($currentDay);
        $proposedDow = $this->isoDow($proposedDay);

        return DB::table('cita_medica')
            ->where('id_medico', $physicianId)
            ->whereIn('estado', ['Programada', 'Confirmada'])
            ->whereRaw('EXTRACT(ISODOW FROM (inicio AT TIME ZONE \'UTC\')) = ?', [$currentDow])
            ->whereRaw('(inicio AT TIME ZONE \'UTC\')::time >= ?', [$currentStart])
            ->whereRaw('(inicio AT TIME ZONE \'UTC\')::time < ?', [$currentEnd])
            ->where(function ($query) use ($proposedDow, $proposedStart, $proposedEnd): void {
                $query->whereRaw('EXTRACT(ISODOW FROM (inicio AT TIME ZONE \'UTC\')) <> ?', [$proposedDow])
                    ->orWhereRaw('(inicio AT TIME ZONE \'UTC\')::time < ?', [$proposedStart])
                    ->orWhereRaw('(fin AT TIME ZONE \'UTC\')::time > ?', [$proposedEnd]);
            })
            ->exists();
    }

    /**
     * @param  array{dia_semana: string, hora_inicio: string, hora_fin: string, duracion_cita_minutos: int, activo: bool, id_medico: int}  $values
     */
    public function createSchedule(array $values): int
    {
        return (int) DB::table('horario_atencion')->insertGetId($values, 'id_horario');
    }

    /**
     * @param  array{dia_semana: string, hora_inicio: string, hora_fin: string, duracion_cita_minutos: int}  $values
     */
    public function updateSchedule(int $id, array $values): void
    {
        DB::table('horario_atencion')->where('id_horario', $id)->update($values);
    }

    public function setScheduleActive(int $id, bool $active): void
    {
        DB::table('horario_atencion')->where('id_horario', $id)->update(['activo' => $active]);
    }

    private function isoDow(string $day): int
    {
        return match ($day) {
            'Lunes' => 1,
            'Martes' => 2,
            'Miercoles' => 3,
            'Jueves' => 4,
            'Viernes' => 5,
            'Sabado' => 6,
            'Domingo' => 7,
            default => 0,
        };
    }
}
