<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Http\Controllers;

use App\Modules\Appointments\AvailabilityService;
use App\Modules\Appointments\Http\Requests\SearchAvailabilityRequest;
use App\Modules\Appointments\InvalidAvailabilityInput;
use App\Modules\Appointments\PhysicianNotEligible;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

final class AvailabilityController
{
    public function __construct(private AvailabilityService $availability) {}

    public function consultAvailability(SearchAvailabilityRequest $request): View
    {
        $input = $request->validated();
        $specialtyId = isset($input['especialidad']) ? (int) $input['especialidad'] : null;
        $physicianId = isset($input['medico']) ? (int) $input['medico'] : null;
        $date = $input['fecha'] ?? null;
        $physicians = collect();
        $slots = null;
        $status = null;

        try {
            if ($specialtyId !== null) {
                $physicians = $this->availability->getConsultPhysicians($specialtyId);
            }
            if ($specialtyId !== null && $physicianId !== null && $date !== null && $date !== '') {
                $slots = $this->availability->getAvailableSlots($specialtyId, $physicianId, $date);
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
            'slots' => $slots,
            'status' => $status,
            'especialidad' => $specialtyId,
            'medico' => $physicianId,
            'fecha' => $date ?? '',
        ]);
    }
}
