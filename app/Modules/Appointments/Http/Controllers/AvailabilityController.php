<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Http\Controllers;

use App\Modules\Appointments\AvailabilityService;
use App\Modules\Appointments\Http\Requests\SearchAvailabilityRequest;
use App\Modules\Appointments\InvalidAvailabilityInput;
use App\Modules\Appointments\PhysicianNotEligible;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

final class AvailabilityController
{
    private const BOARD_START_HOUR = 8;

    private const BOARD_END_HOUR = 18;

    private const HOUR_HEIGHT_REM = 4.75;

    public function __construct(private AvailabilityService $availability) {}

    public function consultAvailability(SearchAvailabilityRequest $request): View
    {
        $input = $request->validated();
        $specialtyId = isset($input['especialidad']) ? (int) $input['especialidad'] : null;
        $physicianId = isset($input['medico']) ? (int) $input['medico'] : null;
        $estado = $input['estado'] ?? null;
        $vista = in_array($input['vista'] ?? '', ['dia', 'semana', 'mes'], true) ? $input['vista'] : 'semana';
        $today = Carbon::now('UTC')->startOfDay();
        $focus = $this->focusDate($input['fecha'] ?? null, $today);
        $physicians = collect();
        $appointments = collect();
        $slots = [];
        $status = null;
        $days = $this->boardDays($vista, $focus, $today);

        try {
            $physicians = $this->availability->getBoardPhysicians($specialtyId);
            $boardPhysicians = $this->boardPhysicians($physicians, $physicianId);
            if ($vista !== 'mes' && $days !== []) {
                $rangeStart = $days[0]->copy()->startOfDay()->toIso8601String();
                $rangeEnd = $days[array_key_last($days)]->copy()->addDay()->startOfDay()->toIso8601String();
                $appointments = $this->availability->getBoardAppointments(
                    $rangeStart,
                    $rangeEnd,
                    $physicianId,
                    $specialtyId,
                    $estado,
                );
            }
            if ($specialtyId !== null && $physicianId !== null && $vista !== 'mes' && $boardPhysicians->isNotEmpty()) {
                $slots = $this->availability->getAvailableSlotsForDates(
                    $specialtyId,
                    $boardPhysicians->pluck('id_medico')->map(fn ($id): int => (int) $id)->all(),
                    $this->slotDates($vista, $focus, $today),
                );
            }
        } catch (InvalidAvailabilityInput) {
            $status = 'Revise la especialidad, el médico y la fecha.';
        } catch (PhysicianNotEligible) {
            $status = 'El médico no está habilitado para esa especialidad.';
        } catch (Throwable $exception) {
            Log::error('[AvailabilityController - consultAvailability] '.$exception::class);
            $status = 'No fue posible obtener la disponibilidad.';
        }

        return view('appointments.availability', [
            'specialties' => $this->availability->getConsultSpecialties(),
            'physicians' => $physicians,
            'slots' => $slots === [] && $specialtyId === null ? null : $slots,
            'events' => $this->boardEvents($appointments, $slots, $physicians),
            'status' => $status,
            'especialidad' => $specialtyId,
            'medico' => $physicianId,
            'estado' => $estado ?? '',
            'fecha' => $focus->toDateString(),
            'vista' => $vista,
            'today' => $today,
            'days' => $days,
            'hours' => $this->boardHourLabels(),
            'boardStartHour' => self::BOARD_START_HOUR,
            'boardEndHour' => self::BOARD_END_HOUR,
            'hourHeight' => self::HOUR_HEIGHT_REM,
            'rangeLabel' => $this->rangeLabel($vista, $days, $focus),
            'prevFecha' => $this->shiftFocus($vista, $focus, -1, $today),
            'nextFecha' => $this->shiftFocus($vista, $focus, 1, $today),
            'canPrev' => $this->canGoPrevious($vista, $focus, $today),
        ]);
    }

    private function focusDate(?string $fecha, Carbon $today): Carbon
    {
        if ($fecha === null || $fecha === '') {
            return $today->copy();
        }
        try {
            $parsed = Carbon::createFromFormat('!Y-m-d', $fecha, 'UTC');
        } catch (Throwable) {
            return $today->copy();
        }
        if ($parsed === false || $parsed->lt($today)) {
            return $today->copy();
        }

        return $parsed->startOfDay();
    }

    /**
     * @return Collection<int, object>
     */
    private function boardPhysicians(Collection $physicians, ?int $physicianId): Collection
    {
        if ($physicianId === null) {
            return $physicians;
        }

        return $physicians->filter(
            fn (object $physician): bool => (int) $physician->id_medico === $physicianId,
        )->values();
    }

    /**
     * @return list<Carbon>
     */
    private function boardDays(string $vista, Carbon $focus, Carbon $today): array
    {
        if ($vista === 'dia') {
            return [$focus->copy()->startOfDay()];
        }
        if ($vista === 'mes') {
            return [];
        }

        $monday = $this->weekMonday($focus, $today);
        $days = [];
        for ($offset = 0; $offset < 5; $offset++) {
            $days[] = $monday->copy()->addDays($offset);
        }

        return $days;
    }

    /**
     * @return list<string>
     */
    private function slotDates(string $vista, Carbon $focus, Carbon $today): array
    {
        $dates = [];
        foreach ($this->boardDays($vista, $focus, $today) as $day) {
            if ($day->lt($today)) {
                continue;
            }
            $dates[] = $day->toDateString();
        }

        return $dates;
    }

    private function weekMonday(Carbon $focus, Carbon $today): Carbon
    {
        $cursor = $focus->copy()->startOfDay();
        if ($cursor->lt($today)) {
            $cursor = $today->copy();
        }
        if ($cursor->isoWeekday() >= 6) {
            $cursor = $cursor->addDays(8 - $cursor->isoWeekday())->startOfDay();
        }

        return $cursor->copy()->startOfWeek(Carbon::MONDAY);
    }

    /**
     * @return list<string>
     */
    private function boardHourLabels(): array
    {
        $hours = [];
        for ($hour = self::BOARD_START_HOUR; $hour < self::BOARD_END_HOUR; $hour++) {
            $hours[] = str_pad((string) $hour, 2, '0', STR_PAD_LEFT).':00';
        }

        return $hours;
    }

    /**
     * @param  Collection<int, object>  $appointments
     * @param  list<array{fecha: string, hora_inicio: string, hora_fin: string, id_medico: int}>  $slots
     * @return list<array{kind: string, fecha: string, hora_inicio: string, hora_fin: string, id_medico: int, medico: string, paciente: string, estado: string, id_cita: ?int, tone: int, top: float, height: float}>
     */
    private function boardEvents(Collection $appointments, array $slots, Collection $physicians): array
    {
        $tones = [];
        $names = [];
        $physicianIds = $physicians->pluck('id_medico')->map(fn ($id): int => (int) $id)->unique()->sort()->values();
        foreach ($physicianIds as $index => $id) {
            $tones[$id] = $index % 3;
        }
        foreach ($physicians as $physician) {
            $id = (int) $physician->id_medico;
            $names[$id] = trim((string) $physician->nombres.' '.(string) $physician->apellidos);
        }

        $events = [];
        $occupied = [];
        foreach ($appointments as $appointment) {
            $startAt = Carbon::parse((string) $appointment->inicio, 'UTC');
            $endAt = Carbon::parse((string) $appointment->fin, 'UTC');
            $fecha = $startAt->toDateString();
            $horaInicio = $startAt->format('H:i');
            $horaFin = $endAt->format('H:i');
            $physicianId = (int) $appointment->id_medico;
            if (! isset($names[$physicianId])) {
                $names[$physicianId] = trim((string) $appointment->medico_nombres.' '.(string) $appointment->medico_apellidos);
                $tones[$physicianId] = count($tones) % 3;
            }
            $layout = $this->eventLayout($horaInicio, $horaFin);
            if ($layout === null) {
                continue;
            }
            $occupied[$fecha.'|'.$physicianId.'|'.$horaInicio] = true;
            $events[] = [
                'kind' => 'cita',
                'fecha' => $fecha,
                'hora_inicio' => $horaInicio,
                'hora_fin' => $horaFin,
                'id_medico' => $physicianId,
                'medico' => $names[$physicianId],
                'paciente' => trim((string) $appointment->paciente_nombres.' '.(string) $appointment->paciente_apellidos),
                'estado' => (string) $appointment->estado,
                'id_cita' => (int) $appointment->id_cita,
                'tone' => $tones[$physicianId] ?? 0,
                'top' => $layout['top'],
                'height' => $layout['height'],
            ];
        }
        foreach ($slots as $slot) {
            $physicianId = (int) $slot['id_medico'];
            if (isset($occupied[$slot['fecha'].'|'.$physicianId.'|'.$slot['hora_inicio']])) {
                continue;
            }
            $layout = $this->eventLayout($slot['hora_inicio'], $slot['hora_fin']);
            if ($layout === null) {
                continue;
            }
            $events[] = [
                'kind' => 'libre',
                'fecha' => $slot['fecha'],
                'hora_inicio' => $slot['hora_inicio'],
                'hora_fin' => $slot['hora_fin'],
                'id_medico' => $physicianId,
                'medico' => $names[$physicianId] ?? 'Médico',
                'paciente' => '',
                'estado' => 'Libre',
                'id_cita' => null,
                'tone' => $tones[$physicianId] ?? 0,
                'top' => $layout['top'],
                'height' => $layout['height'],
            ];
        }

        return $events;
    }

    /**
     * @return array{top: float, height: float}|null
     */
    private function eventLayout(string $horaInicio, string $horaFin): ?array
    {
        $start = $this->clockMinutes($horaInicio);
        $end = $this->clockMinutes($horaFin);
        if ($end <= $start) {
            return null;
        }

        return [
            'top' => round((($start - (self::BOARD_START_HOUR * 60)) / 60) * self::HOUR_HEIGHT_REM, 2),
            'height' => round(max(2.15, (($end - $start) / 60) * self::HOUR_HEIGHT_REM), 2),
        ];
    }

    private function clockMinutes(string $clock): int
    {
        $parts = explode(':', substr($clock, 0, 5));
        $hour = isset($parts[0]) ? (int) $parts[0] : 0;
        $minute = isset($parts[1]) ? (int) $parts[1] : 0;

        return ($hour * 60) + $minute;
    }

    /**
     * @param  list<Carbon>  $days
     */
    private function rangeLabel(string $vista, array $days, Carbon $focus): string
    {
        $months = [1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto', 9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'];
        if ($vista === 'dia' || $days === []) {
            $day = $days[0] ?? $focus;

            return $day->day.' de '.$months[(int) $day->month].' de '.$day->year;
        }
        if ($vista === 'mes') {
            $names = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];

            return $names[(int) $focus->month].' '.$focus->year;
        }
        $first = $days[0];
        $last = $days[array_key_last($days)];
        if ($first->month === $last->month) {
            return $first->day.' – '.$last->day.' de '.$months[(int) $first->month].' de '.$first->year;
        }

        return $first->day.' de '.$months[(int) $first->month].' – '.$last->day.' de '.$months[(int) $last->month].' de '.$last->year;
    }

    private function shiftFocus(string $vista, Carbon $focus, int $direction, Carbon $today): string
    {
        $moved = $focus->copy();
        if ($vista === 'dia') {
            $moved->addDays($direction);
        } elseif ($vista === 'mes') {
            $moved->addMonths($direction)->startOfMonth();
        } else {
            $moved = $this->weekMonday($focus, $today)->addWeeks($direction);
        }
        if ($moved->lt($today)) {
            $moved = $today->copy();
        }

        return $moved->toDateString();
    }

    private function canGoPrevious(string $vista, Carbon $focus, Carbon $today): bool
    {
        if ($vista === 'semana') {
            $monday = $this->weekMonday($focus, $today);

            return $monday->copy()->subWeek()->addDays(4)->gte($today);
        }
        if ($vista === 'dia') {
            return $focus->copy()->subDay()->gte($today);
        }

        return $focus->copy()->startOfMonth()->subMonth()->endOfMonth()->gte($today);
    }
}
