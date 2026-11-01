<?php

declare(strict_types=1);

namespace App\Modules\Patients\Http\Controllers;

use App\Modules\Patients\DeclaredInformationUnavailable;
use App\Modules\Patients\Http\Requests\UpdateDeclaredMedicalInformationRequest;
use App\Modules\Patients\InvalidPatientInput;
use App\Modules\Patients\MedicalInformationService;
use App\Modules\Patients\PatientLinkRequired;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

final class MedicalInformationController
{
    public function __construct(private MedicalInformationService $information) {}

    public function showDeclaredMedicalInformation(Request $request): View
    {
        return $this->declaredView($request, null);
    }

    public function updateDeclaredMedicalInformation(UpdateDeclaredMedicalInformationRequest $request): RedirectResponse
    {
        $input = $request->validated();
        try {
            $this->information->updateDeclaredMedicalInformation(
                (int) $request->attributes->get('identity')->getKey(),
                [
                    'grupo_sanguineo' => $input['grupo_sanguineo'] ?? null,
                    'situacion_alergias' => $input['situacion_alergias'] ?? null,
                    'detalle_alergias' => $input['detalle_alergias'] ?? null,
                    'situacion_enfermedades' => $input['situacion_enfermedades'] ?? null,
                    'detalle_enfermedades' => $input['detalle_enfermedades'] ?? null,
                    'situacion_medicamentos' => $input['situacion_medicamentos'] ?? null,
                    'detalle_medicamentos' => $input['detalle_medicamentos'] ?? null,
                    'antecedentes' => $input['antecedentes'] ?? null,
                ],
            );
        } catch (DeclaredInformationUnavailable) {
            return back()->withInput()->withErrors(['grupo_sanguineo' => 'El portal no está habilitado.']);
        } catch (PatientLinkRequired) {
            return back()->withInput()
                ->withErrors(['grupo_sanguineo' => 'El acceso requiere un vínculo aprobado con el registro de paciente.']);
        } catch (InvalidPatientInput) {
            return back()->withInput()
                ->withErrors(['grupo_sanguineo' => 'Revise los datos declarados. Esta validación no acredita veracidad clínica.']);
        } catch (Throwable $exception) {
            Log::error('[MedicalInformationController - updateDeclaredMedicalInformation] '.$exception::class);

            return back()->withInput()
                ->withErrors(['grupo_sanguineo' => 'No fue posible guardar los cambios.']);
        }

        return redirect()->route('patients.declared')->with('status', 'La información declarada quedó actualizada.');
    }

    private function declaredView(Request $request, ?string $status): View
    {
        $enabled = $this->information->portalIsEnabled();
        $linked = false;
        $queryFailed = false;
        $patient = null;
        $information = null;
        if (! $enabled) {
            return view('patients.declared', [
                'enabled' => false,
                'linked' => false,
                'queryFailed' => false,
                'patient' => null,
                'information' => null,
                'bloodGroups' => MedicalInformationService::BLOOD_GROUPS,
                'status' => $status,
            ]);
        }

        try {
            $payload = $this->information->getDeclaredMedicalInformation(
                (int) $request->attributes->get('identity')->getKey(),
            );
            $linked = true;
            $patient = $payload['patient'];
            $information = $payload['information'];
        } catch (PatientLinkRequired) {
            $linked = false;
        } catch (Throwable $exception) {
            Log::error('[MedicalInformationController - showDeclaredMedicalInformation] '.$exception::class);
            $queryFailed = true;
            $status = 'No fue posible completar la consulta.';
        }

        return view('patients.declared', [
            'enabled' => true,
            'linked' => $linked,
            'queryFailed' => $queryFailed,
            'patient' => $patient,
            'information' => $information,
            'bloodGroups' => MedicalInformationService::BLOOD_GROUPS,
            'status' => $status,
        ]);
    }
}
