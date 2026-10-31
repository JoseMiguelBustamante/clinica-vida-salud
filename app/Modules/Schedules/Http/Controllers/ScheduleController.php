<?php

declare(strict_types=1);

namespace App\Modules\Schedules\Http\Controllers;

use App\Modules\MedicalStaff\PhysicianNotFound;
use App\Modules\MedicalStaff\PhysicianRepository;
use App\Modules\Schedules\Http\Requests\SearchSchedulesRequest;
use App\Modules\Schedules\Http\Requests\StoreScheduleRequest;
use App\Modules\Schedules\Http\Requests\UpdateScheduleRequest;
use App\Modules\Schedules\InvalidScheduleInput;
use App\Modules\Schedules\OverlappingSchedule;
use App\Modules\Schedules\ScheduleBlocksExistingAppointments;
use App\Modules\Schedules\ScheduleNotFound;
use App\Modules\Schedules\ScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

final class ScheduleController
{
    public function __construct(
        private ScheduleService $schedules,
        private PhysicianRepository $physicians,
    ) {}

    public function getAllSchedules(SearchSchedulesRequest $request): View
    {
        $input = $request->validated();

        return view('schedules.index', [
            'schedules' => $this->schedules->getAllSchedules($input['q'] ?? null),
            'q' => $input['q'] ?? '',
        ]);
    }

    public function showCreateScheduleForm(): View
    {
        return view('schedules.create', [
            'physicians' => $this->physicians->getAllPhysicians(null),
            'weekdays' => ScheduleService::WEEKDAY_LABELS,
        ]);
    }

    public function createSchedule(StoreScheduleRequest $request): RedirectResponse
    {
        $input = $request->validated();
        try {
            $this->schedules->createSchedule([
                'id_medico' => (int) $input['id_medico'],
                'dia_semana' => $input['dia_semana'],
                'hora_inicio' => $input['hora_inicio'],
                'hora_fin' => $input['hora_fin'],
                'duracion_cita_minutos' => (int) $input['duracion_cita_minutos'],
                'activo' => (bool) (int) $input['activo'],
            ]);
        } catch (PhysicianNotFound) {
            return back()->withInput()->withErrors(['id_medico' => 'El médico seleccionado no existe.']);
        } catch (OverlappingSchedule) {
            return back()->withInput()->withErrors(['hora_inicio' => 'Esa franja se superpone con otro horario activo del mismo médico.']);
        } catch (InvalidScheduleInput) {
            return back()->withInput()->withErrors(['duracion_cita_minutos' => 'La duración debe ser positiva y caber en intervalos completos dentro de la franja.']);
        } catch (Throwable $exception) {
            Log::error('[ScheduleController - createSchedule] '.$exception::class);

            return back()->withInput()->withErrors(['id_medico' => 'No fue posible guardar el horario.']);
        }

        return redirect()->route('schedules.index')->with('status', 'El horario quedó registrado.');
    }

    public function getScheduleById(int $horario): View|RedirectResponse
    {
        try {
            return view('schedules.show', [
                'schedule' => $this->schedules->getScheduleById($horario),
            ]);
        } catch (ScheduleNotFound) {
            return redirect()->route('schedules.index')
                ->withErrors(['id_medico' => 'No puede realizarse la operación solicitada.']);
        }
    }

    public function showEditScheduleForm(int $horario): View|RedirectResponse
    {
        try {
            return view('schedules.edit', [
                'schedule' => $this->schedules->getScheduleById($horario),
                'weekdays' => ScheduleService::WEEKDAY_LABELS,
            ]);
        } catch (ScheduleNotFound) {
            return redirect()->route('schedules.index')
                ->withErrors(['id_medico' => 'No puede realizarse la operación solicitada.']);
        }
    }

    public function updateSchedule(UpdateScheduleRequest $request, int $horario): RedirectResponse
    {
        $input = $request->validated();
        try {
            $this->schedules->updateSchedule($horario, [
                'dia_semana' => $input['dia_semana'],
                'hora_inicio' => $input['hora_inicio'],
                'hora_fin' => $input['hora_fin'],
                'duracion_cita_minutos' => (int) $input['duracion_cita_minutos'],
            ]);
        } catch (ScheduleNotFound) {
            return redirect()->route('schedules.index')
                ->withErrors(['id_medico' => 'No puede realizarse la operación solicitada.']);
        } catch (OverlappingSchedule) {
            return back()->withInput()->withErrors(['hora_inicio' => 'Esa franja se superpone con otro horario activo del mismo médico.']);
        } catch (ScheduleBlocksExistingAppointments) {
            return back()->withInput()->withErrors(['hora_inicio' => 'Hay citas programadas o confirmadas que quedarían fuera de esta franja. No se aplicó el cambio.']);
        } catch (InvalidScheduleInput) {
            return back()->withInput()->withErrors(['duracion_cita_minutos' => 'La duración debe ser positiva y caber en intervalos completos dentro de la franja.']);
        } catch (Throwable $exception) {
            Log::error('[ScheduleController - updateSchedule] '.$exception::class);

            return back()->withInput()->withErrors(['id_medico' => 'No fue posible guardar los cambios.']);
        }

        return redirect()->route('schedules.show', $horario)->with('status', 'El horario quedó actualizado.');
    }

    public function deactivateSchedule(int $horario): RedirectResponse
    {
        try {
            $this->schedules->deactivateSchedule($horario);
        } catch (ScheduleNotFound) {
            return redirect()->route('schedules.index')
                ->withErrors(['id_medico' => 'No puede realizarse la operación solicitada.']);
        } catch (Throwable $exception) {
            Log::error('[ScheduleController - deactivateSchedule] '.$exception::class);

            return back()->withErrors(['id_medico' => 'No fue posible cambiar el estado.']);
        }

        return redirect()->route('schedules.show', $horario)
            ->with('status', 'El horario quedó inactivo. Las citas existentes se conservan.');
    }

    public function activateSchedule(int $horario): RedirectResponse
    {
        try {
            $this->schedules->activateSchedule($horario);
        } catch (ScheduleNotFound) {
            return redirect()->route('schedules.index')
                ->withErrors(['id_medico' => 'No puede realizarse la operación solicitada.']);
        } catch (OverlappingSchedule) {
            return back()->withErrors(['hora_inicio' => 'Esa franja se superpone con otro horario activo del mismo médico.']);
        } catch (Throwable $exception) {
            Log::error('[ScheduleController - activateSchedule] '.$exception::class);

            return back()->withErrors(['id_medico' => 'No fue posible cambiar el estado.']);
        }

        return redirect()->route('schedules.show', $horario)->with('status', 'El horario quedó activo.');
    }
}
