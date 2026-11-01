<?php

declare(strict_types=1);

namespace App\Modules\MedicalStaff\Http\Controllers;

use App\Modules\Identity\ActivityLogService;
use App\Modules\MedicalStaff\DuplicateProfessionalLicense;
use App\Modules\MedicalStaff\Http\Requests\SearchPhysiciansRequest;
use App\Modules\MedicalStaff\Http\Requests\StorePhysicianRequest;
use App\Modules\MedicalStaff\Http\Requests\UpdatePhysicianRequest;
use App\Modules\MedicalStaff\InactiveSpecialtyAssociation;
use App\Modules\MedicalStaff\InvalidPhysicianInput;
use App\Modules\MedicalStaff\PhysicianNotFound;
use App\Modules\MedicalStaff\PhysicianService;
use App\Modules\MedicalStaff\SpecialtyNotFound;
use App\Modules\MedicalStaff\SpecialtyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

final class PhysicianController
{
    public function __construct(
        private PhysicianService $physicians,
        private SpecialtyService $specialties,
        private ActivityLogService $activity,
    ) {}

    public function getAllPhysicians(SearchPhysiciansRequest $request): View
    {
        $input = $request->validated();

        return view('medical-staff.physicians.index', [
            'physicians' => $this->physicians->getAllPhysicians($input['q'] ?? null),
            'q' => $input['q'] ?? '',
        ]);
    }

    public function showCreatePhysicianForm(): View
    {
        return view('medical-staff.physicians.create', [
            'specialties' => $this->specialties->getActiveSpecialties(),
            'accounts' => $this->physicians->getLinkableStaffAccounts(null),
        ]);
    }

    public function createPhysician(StorePhysicianRequest $request): RedirectResponse
    {
        $input = $this->physicianInput($request->validated());
        try {
            $physicianId = $this->physicians->createPhysician($input);
            $this->activity->record(
                'Creación',
                'Médicos',
                'Registro de '.$input['nombres'].' '.$input['apellidos'],
                $physicianId,
            );
        } catch (DuplicateProfessionalLicense) {
            return back()->withInput()->withErrors(['registro_profesional' => 'El registro profesional ya está en uso.']);
        } catch (SpecialtyNotFound|InactiveSpecialtyAssociation) {
            return back()->withInput()->withErrors(['especialidades' => 'Una especialidad seleccionada no es válida para asociar.']);
        } catch (InvalidPhysicianInput) {
            return back()->withInput()->withErrors(['nombres' => 'Revise los datos del médico y seleccione al menos una especialidad.']);
        } catch (Throwable $exception) {
            Log::error('[PhysicianController - createPhysician] '.$exception::class);

            return back()->withInput()->withErrors(['nombres' => 'No fue posible guardar el médico.']);
        }

        return redirect()->route('physicians.index')->with('status', 'El médico quedó registrado.');
    }

    public function getPhysicianById(int $medico): View|RedirectResponse
    {
        try {
            return view('medical-staff.physicians.show', [
                'physician' => $this->physicians->getPhysicianById($medico),
            ]);
        } catch (PhysicianNotFound) {
            return redirect()->route('physicians.index')
                ->withErrors(['nombres' => 'No puede realizarse la operación solicitada.']);
        }
    }

    public function showEditPhysicianForm(int $medico): View|RedirectResponse
    {
        try {
            return view('medical-staff.physicians.edit', [
                'physician' => $this->physicians->getPhysicianById($medico),
                'specialties' => $this->specialties->getAllSpecialties(null),
                'accounts' => $this->physicians->getLinkableStaffAccounts($medico),
            ]);
        } catch (PhysicianNotFound) {
            return redirect()->route('physicians.index')
                ->withErrors(['nombres' => 'No puede realizarse la operación solicitada.']);
        }
    }

    public function updatePhysician(UpdatePhysicianRequest $request, int $medico): RedirectResponse
    {
        try {
            $this->physicians->updatePhysician($medico, $this->physicianInput($request->validated()));
        } catch (PhysicianNotFound) {
            return redirect()->route('physicians.index')
                ->withErrors(['nombres' => 'No puede realizarse la operación solicitada.']);
        } catch (DuplicateProfessionalLicense) {
            return back()->withInput()->withErrors(['registro_profesional' => 'El registro profesional ya está en uso.']);
        } catch (SpecialtyNotFound|InactiveSpecialtyAssociation) {
            return back()->withInput()->withErrors(['especialidades' => 'Una especialidad seleccionada no es válida para asociar.']);
        } catch (InvalidPhysicianInput) {
            return back()->withInput()->withErrors(['nombres' => 'Revise los datos del médico y seleccione al menos una especialidad.']);
        } catch (Throwable $exception) {
            Log::error('[PhysicianController - updatePhysician] '.$exception::class);

            return back()->withInput()->withErrors(['nombres' => 'No fue posible guardar los cambios.']);
        }

        return redirect()->route('physicians.show', $medico)->with('status', 'El médico quedó actualizado.');
    }

    public function deactivatePhysician(int $medico): RedirectResponse
    {
        try {
            $this->physicians->deactivatePhysician($medico);
        } catch (PhysicianNotFound) {
            return redirect()->route('physicians.index')
                ->withErrors(['nombres' => 'No puede realizarse la operación solicitada.']);
        } catch (Throwable $exception) {
            Log::error('[PhysicianController - deactivatePhysician] '.$exception::class);

            return back()->withErrors(['nombres' => 'No fue posible cambiar el estado.']);
        }

        return redirect()->route('physicians.show', $medico)
            ->with('status', 'El médico quedó inactivo. No recibirá nuevas citas.');
    }

    public function activatePhysician(int $medico): RedirectResponse
    {
        try {
            $this->physicians->activatePhysician($medico);
        } catch (PhysicianNotFound) {
            return redirect()->route('physicians.index')
                ->withErrors(['nombres' => 'No puede realizarse la operación solicitada.']);
        } catch (Throwable $exception) {
            Log::error('[PhysicianController - activatePhysician] '.$exception::class);

            return back()->withErrors(['nombres' => 'No fue posible cambiar el estado.']);
        }

        return redirect()->route('physicians.show', $medico)->with('status', 'El médico quedó activo.');
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{registro_profesional: string, nombres: string, apellidos: string, telefono: ?string, correo_contacto: ?string, activo: bool, id_usuario: ?int, especialidades: list<int>}
     */
    private function physicianInput(array $input): array
    {
        return [
            'registro_profesional' => $input['registro_profesional'],
            'nombres' => $input['nombres'],
            'apellidos' => $input['apellidos'],
            'telefono' => $input['telefono'] ?? null,
            'correo_contacto' => $input['correo_contacto'] ?? null,
            'activo' => (bool) (int) $input['activo'],
            'id_usuario' => isset($input['id_usuario']) && $input['id_usuario'] !== '' ? (int) $input['id_usuario'] : null,
            'especialidades' => array_map(intval(...), $input['especialidades'] ?? []),
        ];
    }
}
