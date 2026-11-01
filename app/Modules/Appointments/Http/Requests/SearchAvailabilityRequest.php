<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SearchAvailabilityRequest extends FormRequest
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
            'especialidad' => ['nullable', 'integer'],
            'medico' => ['nullable', 'integer'],
            'fecha' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'vista' => ['nullable', 'in:dia,semana,mes'],
            'estado' => ['nullable', 'in:Programada,Confirmada,Atendida,Cancelada,Inasistencia'],
            'q' => ['nullable', 'string', 'max:100'],
            'id_paciente' => ['nullable', 'integer'],
            'hora_inicio' => ['nullable', 'date_format:H:i'],
            'hora_fin' => ['nullable', 'date_format:H:i'],
            'nueva' => ['nullable', 'in:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha.date_format' => 'Indique una fecha válida.',
            'fecha.after_or_equal' => 'No se consultan fechas pasadas.',
            'estado.in' => 'El estado de la cita no es válido.',
        ];
    }
}
