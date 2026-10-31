<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Http\Controllers;

use App\Modules\Appointments\AppointmentNotFound;
use App\Modules\Appointments\AppointmentService;
use App\Modules\Appointments\AvailabilityService;
use App\Modules\Appointments\Http\Requests\PrepareAppointmentRequest;
use App\Modules\Appointments\Http\Requests\StoreAppointmentRequest;
use App\Modules\Appointments\InvalidAvailabilityInput;
use App\Modules\Appointments\PatientNotEligible;
use App\Modules\Appointments\PhysicianNotEligible;
use App\Modules\Appointments\SlotUnavailable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

final class AppointmentController
{
    public function __construct(
        private AppointmentService $appointments,
        private AvailabilityService $availability,
    ) {}

    public function showCreateAppointmentForm(PrepareAppointmentRequest $request): View
    {
        $input = $request->validated();
        $specialtyId = isset($input['especialidad']) ? (int) $input['especialidad'] : null;
        $physicians = collect();
        $status = null;
        try {
            if ($specialtyId !== null) {
                $physicians = $this->availability->getConsultPhysicians($specialtyId);
            }
        } catch (InvalidAvailabilityInput) {
            $status = 'Revise la especialidad seleccionada.';
        } catch (Throwable $exception) {
            Log::error('[AppointmentController - showCreateAppointmentForm] '.$exception::class);
            $status = 'No fue posible cargar los médicos de esa especialidad.';
        }

        return view('appointments.create', [
            'patients' => $this->appointments->getBookablePatients($input['q'] ?? null),
            'specialties' => $this->availability->getConsultSpecialties(),
            'physicians' => $physicians,
            'q' => $input['q'] ?? '',
            'id_paciente' => isset($input['id_paciente']) ? (int) $input['id_paciente'] : null,
            'especialidad' => $specialtyId,
            'medico' => isset($input['medico']) ? (int) $input['medico'] : null,
            'fecha' => $input['fecha'] ?? '',
            'hora_inicio' => $input['hora_inicio'] ?? '',
            'hora_fin' => $input['hora_fin'] ?? '',
            'status' => $status,
        ]);
    }

    public function createAppointment(StoreAppointmentRequest $request): RedirectResponse
    {
        $input = $request->validated();
        $actor = $request->attributes->get('identity');
        try {
            $appointmentId = $this->appointments->createAppointment([
                'id_paciente' => (int) $input['id_paciente'],
                'id_especialidad' => (int) $input['id_especialidad'],
                'id_medico' => (int) $input['id_medico'],
                'fecha' => $input['fecha'],
                'hora_inicio' => $input['hora_inicio'],
                'hora_fin' => $input['hora_fin'],
                'modalidad' => $input['modalidad'],
                'motivo_consulta' => $input['motivo_consulta'] ?? null,
            ], (int) $actor->id_usuario);
        } catch (PatientNotEligible) {
            return back()->withInput()->withErrors(['id_paciente' => 'El paciente no está habilitado para una cita.']);
        } catch (PhysicianNotEligible) {
            return back()->withInput()->withErrors(['id_medico' => 'El médico no está habilitado para esa especialidad.']);
        } catch (SlotUnavailable) {
            return back()->withInput()->withErrors(['hora_inicio' => 'Ese intervalo ya no está disponible. Consulte otra fecha u hora.']);
        } catch (InvalidAvailabilityInput) {
            return back()->withInput()->withErrors(['fecha' => 'Revise la fecha, el intervalo y la modalidad.']);
        } catch (Throwable $exception) {
            Log::error('[AppointmentController - createAppointment] '.$exception::class);

            return back()->withInput()->withErrors(['id_paciente' => 'No fue posible registrar la cita.']);
        }

        return redirect()->route('appointments.show', $appointmentId)
            ->with('status', 'La cita quedó programada.');
    }

    public function getAppointmentById(int $cita): View|RedirectResponse
    {
        try {
            return view('appointments.show', [
                'appointment' => $this->appointments->getAppointmentById($cita),
            ]);
        } catch (AppointmentNotFound) {
            return redirect()->route('appointments.create')
                ->withErrors(['id_paciente' => 'No puede realizarse la operación solicitada.']);
        }
    }
}
