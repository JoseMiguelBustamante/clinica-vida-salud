<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre_usuario' => ['required', 'string', 'max:100'],
            'correo_acceso' => ['nullable', 'string', 'max:255', 'email'],
            'password' => ['nullable', 'string', 'min:12'],
            'id_rol' => ['required', 'integer'],
            'activo' => ['required', 'in:0,1'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre_usuario.required' => 'Indique el nombre de usuario.',
            'password.min' => 'La contraseña debe tener al menos 12 caracteres.',
            'id_rol.required' => 'Seleccione un rol.',
        ];
    }
}
