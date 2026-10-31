<?php

declare(strict_types=1);

namespace App\Modules\MedicalStaff\Http\Controllers;

use App\Modules\MedicalStaff\DuplicateSpecialtyName;
use App\Modules\MedicalStaff\Http\Requests\SearchSpecialtiesRequest;
use App\Modules\MedicalStaff\Http\Requests\StoreSpecialtyRequest;
use App\Modules\MedicalStaff\Http\Requests\UpdateSpecialtyRequest;
use App\Modules\MedicalStaff\InvalidSpecialtyInput;
use App\Modules\MedicalStaff\SpecialtyNotFound;
use App\Modules\MedicalStaff\SpecialtyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

final class SpecialtyController
{
    public function __construct(private SpecialtyService $specialties) {}

    public function getAllSpecialties(SearchSpecialtiesRequest $request): View
    {
        $input = $request->validated();

        return view('medical-staff.specialties.index', [
            'specialties' => $this->specialties->getAllSpecialties($input['q'] ?? null),
            'q' => $input['q'] ?? '',
        ]);
    }

    public function showCreateSpecialtyForm(): View
    {
        return view('medical-staff.specialties.create');
    }

    public function createSpecialty(StoreSpecialtyRequest $request): RedirectResponse
    {
        $input = $request->validated();
        try {
            $this->specialties->createSpecialty($input['nombre'], $input['descripcion'] ?? null, (bool) (int) $input['activo']);
        } catch (DuplicateSpecialtyName) {
            return back()->withInput()->withErrors(['nombre' => 'Ya existe una especialidad con ese nombre.']);
        } catch (InvalidSpecialtyInput) {
            return back()->withInput()->withErrors(['nombre' => 'Revise el nombre de la especialidad.']);
        } catch (Throwable $exception) {
            Log::error('[SpecialtyController - createSpecialty] '.$exception::class);

            return back()->withInput()->withErrors(['nombre' => 'No fue posible guardar la especialidad.']);
        }

        return redirect()->route('specialties.index')->with('status', 'La especialidad quedó registrada.');
    }

    public function getSpecialtyById(int $especialidad): View|RedirectResponse
    {
        try {
            return view('medical-staff.specialties.show', [
                'specialty' => $this->specialties->getSpecialtyById($especialidad),
            ]);
        } catch (SpecialtyNotFound) {
            return redirect()->route('specialties.index')
                ->withErrors(['nombre' => 'No puede realizarse la operación solicitada.']);
        }
    }

    public function showEditSpecialtyForm(int $especialidad): View|RedirectResponse
    {
        try {
            return view('medical-staff.specialties.edit', [
                'specialty' => $this->specialties->getSpecialtyById($especialidad),
            ]);
        } catch (SpecialtyNotFound) {
            return redirect()->route('specialties.index')
                ->withErrors(['nombre' => 'No puede realizarse la operación solicitada.']);
        }
    }

    public function updateSpecialty(UpdateSpecialtyRequest $request, int $especialidad): RedirectResponse
    {
        $input = $request->validated();
        try {
            $this->specialties->updateSpecialty($especialidad, $input['nombre'], $input['descripcion'] ?? null);
        } catch (SpecialtyNotFound) {
            return redirect()->route('specialties.index')
                ->withErrors(['nombre' => 'No puede realizarse la operación solicitada.']);
        } catch (DuplicateSpecialtyName) {
            return back()->withInput()->withErrors(['nombre' => 'Ya existe una especialidad con ese nombre.']);
        } catch (InvalidSpecialtyInput) {
            return back()->withInput()->withErrors(['nombre' => 'Revise el nombre de la especialidad.']);
        } catch (Throwable $exception) {
            Log::error('[SpecialtyController - updateSpecialty] '.$exception::class);

            return back()->withInput()->withErrors(['nombre' => 'No fue posible guardar los cambios.']);
        }

        return redirect()->route('specialties.show', $especialidad)->with('status', 'La especialidad quedó actualizada.');
    }

    public function deactivateSpecialty(int $especialidad): RedirectResponse
    {
        try {
            $this->specialties->deactivateSpecialty($especialidad);
        } catch (SpecialtyNotFound) {
            return redirect()->route('specialties.index')
                ->withErrors(['nombre' => 'No puede realizarse la operación solicitada.']);
        } catch (Throwable $exception) {
            Log::error('[SpecialtyController - deactivateSpecialty] '.$exception::class);

            return back()->withErrors(['nombre' => 'No fue posible cambiar el estado.']);
        }

        return redirect()->route('specialties.show', $especialidad)
            ->with('status', 'La especialidad quedó inactiva. Las asociaciones históricas se conservan.');
    }

    public function activateSpecialty(int $especialidad): RedirectResponse
    {
        try {
            $this->specialties->activateSpecialty($especialidad);
        } catch (SpecialtyNotFound) {
            return redirect()->route('specialties.index')
                ->withErrors(['nombre' => 'No puede realizarse la operación solicitada.']);
        } catch (Throwable $exception) {
            Log::error('[SpecialtyController - activateSpecialty] '.$exception::class);

            return back()->withErrors(['nombre' => 'No fue posible cambiar el estado.']);
        }

        return redirect()->route('specialties.show', $especialidad)->with('status', 'La especialidad quedó activa.');
    }
}
