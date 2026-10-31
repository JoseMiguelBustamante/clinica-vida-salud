<?php

declare(strict_types=1);

namespace App\Modules\Patients\Http\Controllers;

use App\Modules\Patients\DuplicatePatientDocument;
use App\Modules\Patients\Http\Requests\SearchPatientsRequest;
use App\Modules\Patients\Http\Requests\StorePatientRequest;
use App\Modules\Patients\Http\Requests\UpdatePatientRequest;
use App\Modules\Patients\InvalidPatientInput;
use App\Modules\Patients\PatientNotFound;
use App\Modules\Patients\PatientService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

final class PatientController
{
    public function __construct(private PatientService $patients) {}

    public function getAllPatients(SearchPatientsRequest $request): View
    {
        $input = $request->validated();

        return view('patients.index', [
            'patients' => $this->patients->getAllPatients($input['q'] ?? null),
            'q' => $input['q'] ?? '',
        ]);
    }

    public function showCreatePatientForm(): View
    {
        return view('patients.create');
    }

    public function createPatient(StorePatientRequest $request): RedirectResponse
    {
        try {
            $this->patients->createPatient($request->validated());
        } catch (DuplicatePatientDocument) {
            return back()->withInput()->withErrors(['ci' => 'Ya existe un paciente con ese documento de identidad.']);
        } catch (InvalidPatientInput) {
            return back()->withInput()->withErrors(['nombres' => 'Revise los datos del paciente, incluida la fecha de nacimiento.']);
        } catch (Throwable $exception) {
            Log::error('[PatientController - createPatient] '.$exception::class);

            return back()->withInput()->withErrors(['nombres' => 'No fue posible guardar el paciente.']);
        }

        return redirect()->route('patients.index')->with('status', 'El paciente quedó registrado.');
    }

    public function getPatientById(int $paciente): View|RedirectResponse
    {
        try {
            return view('patients.show', [
                'patient' => $this->patients->getPatientById($paciente),
            ]);
        } catch (PatientNotFound) {
            return redirect()->route('patients.index')
                ->withErrors(['nombres' => 'No puede realizarse la operación solicitada.']);
        }
    }

    public function showEditPatientForm(int $paciente): View|RedirectResponse
    {
        try {
            return view('patients.edit', [
                'patient' => $this->patients->getPatientById($paciente),
            ]);
        } catch (PatientNotFound) {
            return redirect()->route('patients.index')
                ->withErrors(['nombres' => 'No puede realizarse la operación solicitada.']);
        }
    }

    public function updatePatient(UpdatePatientRequest $request, int $paciente): RedirectResponse
    {
        try {
            $this->patients->updatePatient($paciente, $request->validated());
        } catch (PatientNotFound) {
            return redirect()->route('patients.index')
                ->withErrors(['nombres' => 'No puede realizarse la operación solicitada.']);
        } catch (DuplicatePatientDocument) {
            return back()->withInput()->withErrors(['ci' => 'Ya existe un paciente con ese documento de identidad.']);
        } catch (InvalidPatientInput) {
            return back()->withInput()->withErrors(['nombres' => 'Revise los datos del paciente, incluida la fecha de nacimiento.']);
        } catch (Throwable $exception) {
            Log::error('[PatientController - updatePatient] '.$exception::class);

            return back()->withInput()->withErrors(['nombres' => 'No fue posible guardar los cambios.']);
        }

        return redirect()->route('patients.show', $paciente)->with('status', 'El paciente quedó actualizado.');
    }

    public function deactivatePatient(int $paciente): RedirectResponse
    {
        try {
            $this->patients->deactivatePatient($paciente);
        } catch (PatientNotFound) {
            return redirect()->route('patients.index')
                ->withErrors(['nombres' => 'No puede realizarse la operación solicitada.']);
        } catch (Throwable $exception) {
            Log::error('[PatientController - deactivatePatient] '.$exception::class);

            return back()->withErrors(['nombres' => 'No fue posible cambiar el estado.']);
        }

        return redirect()->route('patients.show', $paciente)->with('status', 'El paciente quedó inactivo. Sus citas se conservan.');
    }

    public function activatePatient(int $paciente): RedirectResponse
    {
        try {
            $this->patients->activatePatient($paciente);
        } catch (PatientNotFound) {
            return redirect()->route('patients.index')
                ->withErrors(['nombres' => 'No puede realizarse la operación solicitada.']);
        } catch (Throwable $exception) {
            Log::error('[PatientController - activatePatient] '.$exception::class);

            return back()->withErrors(['nombres' => 'No fue posible cambiar el estado.']);
        }

        return redirect()->route('patients.show', $paciente)->with('status', 'El paciente quedó activo.');
    }
}
