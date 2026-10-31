<?php

declare(strict_types=1);

namespace App\Modules\Schedules;

use App\Modules\MedicalStaff\PhysicianNotFound;
use App\Modules\MedicalStaff\PhysicianRepository;
use DateTimeImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ScheduleService
{
    public const WEEKDAYS = ['Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado', 'Domingo'];

    public const WEEKDAY_LABELS = [
        'Lunes' => 'Lunes',
        'Martes' => 'Martes',
        'Miercoles' => 'Miércoles',
        'Jueves' => 'Jueves',
        'Viernes' => 'Viernes',
        'Sabado' => 'Sábado',
        'Domingo' => 'Domingo',
    ];

    public function __construct(
        private ScheduleRepository $schedules,
        private PhysicianRepository $physicians,
    ) {}

    public function getAllSchedules(?string $fragment): Collection
    {
        return $this->schedules->getAllSchedules($fragment === '' ? null : $fragment);
    }

    public function getScheduleById(int $id): object
    {
        $schedule = $this->schedules->getScheduleById($id);
        if ($schedule === null) {
            throw new ScheduleNotFound;
        }

        return $schedule;
    }

    /**
     * @param  array{id_medico: int, dia_semana: string, hora_inicio: string, hora_fin: string, duracion_cita_minutos: int, activo: bool}  $input
     */
    public function createSchedule(array $input): int
    {
        $this->requirePhysician((int) $input['id_medico']);
        $values = $this->normalizedWindow($input);
        $this->assertNoOverlap((int) $input['id_medico'], $values['dia_semana'], $values['hora_inicio'], $values['hora_fin'], null);

        return DB::transaction(function () use ($input, $values): int {
            $this->schedules->lockPhysicianSchedules((int) $input['id_medico']);
            $this->assertNoOverlap((int) $input['id_medico'], $values['dia_semana'], $values['hora_inicio'], $values['hora_fin'], null);

            return $this->schedules->createSchedule([
                ...$values,
                'activo' => (bool) $input['activo'],
                'id_medico' => (int) $input['id_medico'],
            ]);
        });
    }

    /**
     * @param  array{dia_semana: string, hora_inicio: string, hora_fin: string, duracion_cita_minutos: int}  $input
     */
    public function updateSchedule(int $id, array $input): void
    {
        $current = $this->getScheduleById($id);
        $values = $this->normalizedWindow($input);
        $this->assertAppointmentsRemainCovered($current, $values);
        if ($current->activo) {
            $this->assertNoOverlap((int) $current->id_medico, $values['dia_semana'], $values['hora_inicio'], $values['hora_fin'], $id);
        }

        DB::transaction(function () use ($id, $current, $values): void {
            $this->schedules->lockPhysicianSchedules((int) $current->id_medico);
            $this->assertAppointmentsRemainCovered($current, $values);
            if ($current->activo) {
                $this->assertNoOverlap((int) $current->id_medico, $values['dia_semana'], $values['hora_inicio'], $values['hora_fin'], $id);
            }
            $this->schedules->updateSchedule($id, $values);
        });
    }

    public function deactivateSchedule(int $id): void
    {
        $this->getScheduleById($id);
        $this->schedules->setScheduleActive($id, false);
    }

    public function activateSchedule(int $id): void
    {
        $schedule = $this->getScheduleById($id);
        $start = $this->timeString($schedule->hora_inicio);
        $end = $this->timeString($schedule->hora_fin);
        $this->assertNoOverlap((int) $schedule->id_medico, (string) $schedule->dia_semana, $start, $end, $id);

        DB::transaction(function () use ($id, $schedule, $start, $end): void {
            $this->schedules->lockPhysicianSchedules((int) $schedule->id_medico);
            $this->assertNoOverlap((int) $schedule->id_medico, (string) $schedule->dia_semana, $start, $end, $id);
            $this->schedules->setScheduleActive($id, true);
        });
    }

    /**
     * @param  array{dia_semana: string, hora_inicio: string, hora_fin: string, duracion_cita_minutos: int}  $input
     * @return array{dia_semana: string, hora_inicio: string, hora_fin: string, duracion_cita_minutos: int}
     */
    private function normalizedWindow(array $input): array
    {
        $day = $input['dia_semana'] ?? '';
        if (! in_array($day, self::WEEKDAYS, true)) {
            throw new InvalidScheduleInput;
        }
        $start = $this->normalizedTime($input['hora_inicio'] ?? '');
        $end = $this->normalizedTime($input['hora_fin'] ?? '');
        $duration = (int) $input['duracion_cita_minutos'];
        if ($duration < 1 || $duration > 1440) {
            throw new InvalidScheduleInput;
        }
        $startAt = DateTimeImmutable::createFromFormat('!H:i:s', $start);
        $endAt = DateTimeImmutable::createFromFormat('!H:i:s', $end);
        if ($startAt === false || $endAt === false || $endAt <= $startAt) {
            throw new InvalidScheduleInput;
        }
        $minutes = intdiv($endAt->getTimestamp() - $startAt->getTimestamp(), 60);
        if ($minutes % $duration !== 0) {
            throw new InvalidScheduleInput;
        }

        return [
            'dia_semana' => $day,
            'hora_inicio' => $start,
            'hora_fin' => $end,
            'duracion_cita_minutos' => $duration,
        ];
    }

    private function normalizedTime(string $value): string
    {
        foreach (['H:i:s', 'H:i'] as $format) {
            $parsed = DateTimeImmutable::createFromFormat('!'.$format, $value);
            if ($parsed instanceof DateTimeImmutable && $parsed->format($format) === $value) {
                return $parsed->format('H:i:s');
            }
        }

        throw new InvalidScheduleInput;
    }

    private function timeString(mixed $value): string
    {
        return $this->normalizedTime(substr((string) $value, 0, 8));
    }

    private function requirePhysician(int $id): void
    {
        if ($this->physicians->getPhysicianById($id) === null) {
            throw new PhysicianNotFound;
        }
    }

    private function assertNoOverlap(int $physicianId, string $day, string $start, string $end, ?int $exceptId): void
    {
        if ($this->schedules->hasOverlappingActiveSchedule($physicianId, $day, $start, $end, $exceptId)) {
            throw new OverlappingSchedule;
        }
    }

    /**
     * @param  array{dia_semana: string, hora_inicio: string, hora_fin: string, duracion_cita_minutos: int}  $proposed
     */
    private function assertAppointmentsRemainCovered(object $current, array $proposed): void
    {
        if ($this->schedules->scheduledAppointmentsWouldFallOutside(
            (int) $current->id_medico,
            (string) $current->dia_semana,
            $this->timeString($current->hora_inicio),
            $this->timeString($current->hora_fin),
            $proposed['dia_semana'],
            $proposed['hora_inicio'],
            $proposed['hora_fin'],
        )) {
            throw new ScheduleBlocksExistingAppointments;
        }
    }
}
