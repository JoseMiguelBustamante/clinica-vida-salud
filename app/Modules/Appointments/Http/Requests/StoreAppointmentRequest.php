<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreAppointmentRequest extends FormRequest
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
            'id_paciente' => ['required', 'integer'],
            'id_especialidad' => ['required', 'integer'],
            'id_medico' => ['required', 'integer'],
            'fecha' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fin' => ['required', 'date_format:H:i', 'after:hora_inicio'],
            'modalidad' => ['required', 'in:Presencial,Virtual'],
            'motivo_consulta' => ['nullable', 'string', 'max:2000'],
            'origen' => ['nullable', 'in:disponibilidad'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_paciente.required' => 'Seleccione un paciente.',
            'id_especialidad.required' => 'Seleccione una especialidad.',
            'id_medico.required' => 'Seleccione un médico.',
            'fecha.required' => 'Indique la fecha de la cita.',
            'fecha.after_or_equal' => 'No se programan fechas pasadas.',
            'hora_inicio.required' => 'Indique la hora de inicio.',
            'hora_fin.required' => 'Indique la hora de fin.',
            'hora_fin.after' => 'La hora de fin debe ser posterior al inicio.',
            'modalidad.required' => 'Seleccione la modalidad.',
            'modalidad.in' => 'La modalidad debe ser Presencial o Virtual.',
        ];
    }
}
