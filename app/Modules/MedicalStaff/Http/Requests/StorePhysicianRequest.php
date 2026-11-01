<?php

declare(strict_types=1);

namespace App\Modules\MedicalStaff\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StorePhysicianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'registro_profesional' => ['required', 'string', 'max:50'],
            'nombres' => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'correo_contacto' => ['nullable', 'string', 'max:255', 'email'],
            'especialidades' => ['required', 'array', 'min:1'],
            'especialidades.*' => ['integer'],
            'activo' => ['required', 'in:0,1'],
            'id_usuario' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'registro_profesional.required' => 'Indique el registro profesional.',
            'nombres.required' => 'Indique los nombres del médico.',
            'apellidos.required' => 'Indique los apellidos del médico.',
            'especialidades.required' => 'Seleccione al menos una especialidad.',
            'especialidades.min' => 'Seleccione al menos una especialidad.',
        ];
    }
}
