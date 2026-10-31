<?php

declare(strict_types=1);

namespace App\Modules\MedicalStaff\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreSpecialtyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string'],
            'activo' => ['required', 'in:0,1'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'Indique el nombre de la especialidad.',
        ];
    }
}
