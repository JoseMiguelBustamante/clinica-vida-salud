<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\PatientPortalService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class RegisterPatientAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        if (! app(PatientPortalService::class)->registrationIsEnabled()) {
            return [];
        }

        return [
            'nombre_usuario' => ['required', 'string', 'max:100'],
            'correo_acceso' => ['nullable', 'string', 'max:255', 'email'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! app(PatientPortalService::class)->registrationIsEnabled()) {
                $validator->errors()->add('nombre_usuario', 'El registro de cuentas no está habilitado.');

                return;
            }
            $password = $this->input('password');
            if (is_string($password) && strlen($password) > 72) {
                $validator->errors()->add('password', 'La contraseña no cumple las condiciones establecidas.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'nombre_usuario.required' => 'Indique el nombre de usuario.',
            'password.required' => 'Indique la contraseña.',
            'password.min' => 'La contraseña debe tener al menos 12 caracteres.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
        ];
    }
}
