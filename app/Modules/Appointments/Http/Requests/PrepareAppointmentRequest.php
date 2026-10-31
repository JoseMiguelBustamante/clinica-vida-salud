<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PrepareAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['hora_inicio', 'hora_fin'] as $field) {
            $value = $this->input($field);
            if (is_string($value) && preg_match('/^\d{2}:\d{2}/', $value) === 1) {
                $this->merge([$field => substr($value, 0, 5)]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'id_paciente' => ['nullable', 'integer'],
            'especialidad' => ['nullable', 'integer'],
            'medico' => ['nullable', 'integer'],
            'fecha' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'hora_inicio' => ['nullable', 'date_format:H:i'],
            'hora_fin' => ['nullable', 'date_format:H:i'],
            'q' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha.after_or_equal' => 'No se programan fechas pasadas.',
        ];
    }
}
