<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RescheduleAppointmentRequest extends FormRequest
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
            'fecha' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fin' => ['required', 'date_format:H:i', 'after:hora_inicio'],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha.required' => 'Indique la nueva fecha de la cita.',
            'fecha.after_or_equal' => 'No se programan fechas pasadas.',
            'hora_inicio.required' => 'Indique la hora de inicio.',
            'hora_fin.required' => 'Indique la hora de fin.',
            'hora_fin.after' => 'La hora de fin debe ser posterior al inicio.',
        ];
    }
}
