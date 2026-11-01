<?php

declare(strict_types=1);

namespace App\Modules\Patients\Http\Requests;

use App\Modules\Patients\MedicalInformationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateDeclaredMedicalInformationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        if (! app(MedicalInformationService::class)->portalIsEnabled()) {
            return [];
        }

        return [
            'grupo_sanguineo' => ['nullable', 'string', 'max:5'],
            'situacion_alergias' => ['nullable', 'string', 'max:50'],
            'detalle_alergias' => ['nullable', 'string', 'max:2000'],
            'situacion_enfermedades' => ['nullable', 'string', 'max:50'],
            'detalle_enfermedades' => ['nullable', 'string', 'max:2000'],
            'situacion_medicamentos' => ['nullable', 'string', 'max:50'],
            'detalle_medicamentos' => ['nullable', 'string', 'max:2000'],
            'antecedentes' => ['nullable', 'string', 'max:4000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! app(MedicalInformationService::class)->portalIsEnabled()) {
                $validator->errors()->add('grupo_sanguineo', 'El portal no está habilitado.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'grupo_sanguineo.max' => 'El grupo sanguíneo no es válido.',
        ];
    }
}
