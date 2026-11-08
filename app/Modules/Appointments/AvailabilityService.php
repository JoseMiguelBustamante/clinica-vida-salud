<?php

declare(strict_types=1);

namespace App\Modules\Appointments;

use App\Modules\MedicalStaff\PhysicianRepository;
use App\Modules\MedicalStaff\SpecialtyRepository;
use App\Modules\Schedules\ScheduleService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class AvailabilityService
{
    public function __construct(
        private AppointmentRepository $appointments,
        private PhysicianRepository $physicians,
        private SpecialtyRepository $specialties,
    ) {}

    public function getConsultSpecialties(): Collection
    {
        return $this->specialties->getActiveSpecialties();
    }

    public function getBookableSpecialties(): Collection
    {
        return $this->specialties->getActiveSpecialtiesWithPhysicians();
    }

    public function getConsultPhysicians(int $specialtyId): Collection
    {
        $this->requireActiveSpecialty($specialtyId);

        return $this->appointments->getActivePhysiciansForSpecialty($specialtyId);
    }

    public function getBoardPhysicians(?int $specialtyId): Collection
    {
        if ($specialtyId === null) {
            return $this->appointments->getActivePhysicians();
        }

        return $this->getConsultPhysicians($specialtyId);
    }

    /**
     * @return Collection<int, object>
     */
    public function getBoardAppointments(
        string $rangeStart,
        string $rangeEnd,
        ?int $physicianId,
        ?int $specialtyId,
        ?string $status,
    ): Collection {
        if ($status !== null && $status !== ''
            && ! in_array($status, ['Programada', 'Confirmada', 'Atendida', 'Cancelada', 'Inasistencia'], true)) {
            throw new InvalidAvailabilityInput;
        }

        $rows = $this->appointments->getAppointmentsInRange(
            $rangeStart,
            $rangeEnd,
            $physicianId,
            $specialtyId,
            $status === '' ? null : $status,
        );
        if ($status !== null && $status !== '') {
            return $rows;
        }

        return $rows->filter(
            fn (object $appointment): bool => in_array((string) $appointment->estado, ['Programada', 'Confirmada', 'Atendida'], true),
        )->values();
    }

    /**
     * @return list<array{hora_inicio: string, hora_fin: string}>
     */
    public function getAvailableSlots(int $specialtyId, int $physicianId, string $date, ?int $exceptAppointmentId = null): array
    {
        $this->requireEligiblePhysician($physicianId, $specialtyId);
        $day = $this->normalizedDate($date);
        $weekday = $this->weekdayName($day);
        $schedules = $this->appointments->getActiveSchedulesForPhysicianOnDay($physicianId, $weekday);
        if ($schedules->isEmpty()) {
            return [];
        }

        $rangeStart = $day->copy()->startOfDay();
        $rangeEnd = $day->copy()->addDay()->startOfDay();
        $occupying = $this->appointments->getOccupyingAppointments(
            $physicianId,
            $rangeStart->toIso8601String(),
            $rangeEnd->toIso8601String(),
            $exceptAppointmentId,
        );
        $now = Carbon::now('UTC');
        $slots = [];
        foreach ($schedules as $schedule) {
            foreach ($this->slotsFromSchedule($schedule, $day, $now, $occupying) as $slot) {
                $slots[] = $slot;
            }
        }

        return $slots;
    }

    /**
     * @param  list<int>  $physicianIds
     * @param  list<string>  $dates
     * @return list<array{fecha: string, hora_inicio: string, hora_fin: string, id_medico: int}>
     */
    public function getAvailableSlotsForDates(int $specialtyId, array $physicianIds, array $dates): array
    {
        $slots = [];
        foreach ($physicianIds as $physicianId) {
            foreach ($dates as $date) {
                try {
                    foreach ($this->getAvailableSlots($specialtyId, $physicianId, $date) as $slot) {
                        $slots[] = [
                            'fecha' => $date,
                            'hora_inicio' => $slot['hora_inicio'],
                            'hora_fin' => $slot['hora_fin'],
                            'id_medico' => $physicianId,
                        ];
                    }
                } catch (InvalidAvailabilityInput|PhysicianNotEligible) {
                    continue;
                }
            }
        }

        return $slots;
    }

    /**
     * CU22: validates a requested interval without reserving it.
     *
     * @return array{available: true}|never
     */
    public function validateRequestedSlot(
        int $specialtyId,
        int $physicianId,
        string $start,
        string $end,
        ?int $exceptAppointmentId = null,
    ): array {
        $this->requireEligiblePhysician($physicianId, $specialtyId);
        try {
            $from = Carbon::parse($start, 'UTC');
            $until = Carbon::parse($end, 'UTC');
        } catch (\Throwable) {
            throw new InvalidAvailabilityInput;
        }
        if ($until->lte($from) || $from->lt(Carbon::now('UTC'))) {
            throw new InvalidAvailabilityInput;
        }

        $weekday = $this->weekdayName($from->copy()->startOfDay());
        $schedules = $this->appointments->getActiveSchedulesForPhysicianOnDay($physicianId, $weekday);
        if (! $this->matchesConfiguredSlot($from, $until, $schedules)) {
            throw new SlotUnavailable;
        }

        $occupying = $this->appointments->getOccupyingAppointments(
            $physicianId,
            $from->toIso8601String(),
            $until->toIso8601String(),
            $exceptAppointmentId,
        );
        if ($this->overlaps($from, $until, $occupying)) {
            throw new SlotUnavailable;
        }

        return ['available' => true];
    }

    private function requireActiveSpecialty(int $specialtyId): object
    {
        $specialty = $this->specialties->getSpecialtyById($specialtyId);
        if ($specialty === null || ! $specialty->activo) {
            throw new InvalidAvailabilityInput;
        }

        return $specialty;
    }

    private function requireEligiblePhysician(int $physicianId, int $specialtyId): void
    {
        $this->requireActiveSpecialty($specialtyId);
        $physician = $this->physicians->getPhysicianById($physicianId);
        if ($physician === null || ! $physician->activo) {
            throw new PhysicianNotEligible;
        }
        if (! $this->appointments->physicianAttendsSpecialty($physicianId, $specialtyId)) {
            throw new PhysicianNotEligible;
        }
    }

    private function normalizedDate(string $date): Carbon
    {
        try {
            $day = Carbon::createFromFormat('!Y-m-d', $date, 'UTC');
        } catch (\Throwable) {
            throw new InvalidAvailabilityInput;
        }
        if ($day === false || $day->format('Y-m-d') !== $date) {
            throw new InvalidAvailabilityInput;
        }
        if ($day->copy()->startOfDay()->lt(Carbon::now('UTC')->startOfDay())) {
            throw new InvalidAvailabilityInput;
        }

        return $day;
    }

    private function weekdayName(Carbon $day): string
    {
        $names = ScheduleService::WEEKDAYS;
        $index = $day->isoWeekday() - 1;

        return $names[$index];
    }

    /**
     * @param  Collection<int, object>  $occupying
     * @return list<array{hora_inicio: string, hora_fin: string}>
     */
    private function slotsFromSchedule(object $schedule, Carbon $day, Carbon $now, Collection $occupying): array
    {
        $start = $this->clockOnDate($day, (string) $schedule->hora_inicio);
        $end = $this->clockOnDate($day, (string) $schedule->hora_fin);
        $duration = (int) $schedule->duracion_cita_minutos;
        if ($duration < 1 || $end->lte($start)) {
            return [];
        }

        $slots = [];
        $cursor = $start->copy();
        while ($cursor->copy()->addMinutes($duration)->lte($end)) {
            $slotEnd = $cursor->copy()->addMinutes($duration);
            if ($slotEnd->gt($now) && ! $this->overlaps($cursor, $slotEnd, $occupying)) {
                $slots[] = [
                    'hora_inicio' => $cursor->format('H:i'),
                    'hora_fin' => $slotEnd->format('H:i'),
                ];
            }
            $cursor = $slotEnd;
        }

        return $slots;
    }

    private function clockOnDate(Carbon $day, string $time): Carbon
    {
        $clock = substr($time, 0, 8);
        $parsed = Carbon::createFromFormat('Y-m-d H:i:s', $day->format('Y-m-d').' '.$clock, 'UTC');
        if ($parsed === false) {
            throw new InvalidAvailabilityInput;
        }

        return $parsed;
    }

    /** @param  Collection<int, object>  $occupying */
    private function overlaps(Carbon $from, Carbon $until, Collection $occupying): bool
    {
        foreach ($occupying as $appointment) {
            $start = Carbon::parse((string) $appointment->inicio);
            $end = Carbon::parse((string) $appointment->fin);
            if ($start->lt($until) && $end->gt($from)) {
                return true;
            }
        }

        return false;
    }

    /** @param  Collection<int, object>  $schedules */
    private function matchesConfiguredSlot(Carbon $from, Carbon $until, Collection $schedules): bool
    {
        foreach ($schedules as $schedule) {
            foreach ($this->slotsFromSchedule($schedule, $from->copy()->startOfDay(), $from->copy()->subSecond(), collect()) as $slot) {
                if ($slot['hora_inicio'] === $from->format('H:i') && $slot['hora_fin'] === $until->format('H:i')) {
                    return true;
                }
            }
        }

        return false;
    }
}
