<?php

declare(strict_types=1);

namespace App\Modules\MedicalStaff\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateSpecialtyRequest extends FormRequest
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
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'Indique el nombre de la especialidad.',
        ];
    }
}
