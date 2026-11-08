<?php

declare(strict_types=1);

namespace App\Modules\Patients\Http\Controllers;

use App\Modules\Identity\ActivityLogService;
use App\Modules\Patients\DuplicatePatientDocument;
use App\Modules\Patients\Http\Requests\SearchPatientsRequest;
use App\Modules\Patients\Http\Requests\StorePatientRequest;
use App\Modules\Patients\Http\Requests\UpdatePatientRequest;
use App\Modules\Patients\InvalidPatientInput;
use App\Modules\Patients\PatientNotFound;
use App\Modules\Patients\PatientService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

final class PatientController
{
    public function __construct(
        private PatientService $patients,
        private ActivityLogService $activity,
    ) {}

    public function getAllPatients(SearchPatientsRequest $request): View
    {
        $input = $request->validated();

        return view('patients.index', [
            'patients' => $this->patients->getAllPatients($input['q'] ?? null),
            'q' => $input['q'] ?? '',
        ]);
    }

    public function showCreatePatientForm(Request $request): View
    {
        $accounts = $this->patients->getLinkablePatientAccounts(null);
        $accountId = $request->query('cuenta');
        $suggestedAccount = null;
        if (is_numeric($accountId)) {
            $suggestedAccount = $this->patients->getLinkablePatientAccountById((int) $accountId);
        }

        return view('patients.create', [
            'accounts' => $accounts,
            'suggestedAccount' => $suggestedAccount,
        ]);
    }

    public function createPatient(StorePatientRequest $request): RedirectResponse
    {
        $input = $request->validated();
        try {
            $patientId = $this->patients->createPatient($input);
            $this->activity->record(
                'Creación',
                'Pacientes',
                'Registro de '.$input['nombres'].' '.$input['apellidos'],
                $patientId,
            );
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
            $patient = $this->patients->getPatientById($paciente);

            return view('patients.edit', [
                'patient' => $patient,
                'accounts' => $this->patients->getLinkablePatientAccounts((int) $patient->id_paciente),
            ]);
        } catch (PatientNotFound) {
            return redirect()->route('patients.index')
                ->withErrors(['nombres' => 'No puede realizarse la operación solicitada.']);
        }
    }

    public function updatePatient(UpdatePatientRequest $request, int $paciente): RedirectResponse
    {
        try {
            $before = $this->patients->getPatientById($paciente);
            $this->patients->updatePatient($paciente, $request->validated());
            $after = $this->patients->getPatientById($paciente);
            $changes = $this->activity->fieldChanges((array) $before, (array) $after, [
                'ci' => 'CI',
                'nombres' => 'Nombres',
                'apellidos' => 'Apellidos',
                'fecha_nacimiento' => 'Fecha de nacimiento',
                'sexo' => 'Sexo',
                'telefono' => 'Teléfono',
                'correo_contacto' => 'Correo',
                'direccion' => 'Dirección',
            ]);
            $detail = $changes === []
                ? 'Paciente actualizado'
                : ($changes[0]['campo'].(count($changes) === 1 ? ' actualizado' : ' y otros campos actualizados'));
            $this->activity->record('Actualización', 'Pacientes', $detail, $paciente, $changes);
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
            $this->activity->record('Desactivación', 'Pacientes', 'Paciente desactivado', $paciente);
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
            $this->activity->record('Activación', 'Pacientes', 'Paciente activado', $paciente);
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
