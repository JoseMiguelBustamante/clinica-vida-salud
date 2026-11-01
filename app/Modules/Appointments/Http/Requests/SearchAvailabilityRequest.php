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

    public function rules(): array
    {
        return [
            'especialidad' => ['nullable', 'integer'],
            'medico' => ['nullable', 'integer'],
            'fecha' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'vista' => ['nullable', 'in:dia,semana,mes'],
            'estado' => ['nullable', 'in:Programada,Confirmada,Atendida,Cancelada,Inasistencia'],
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
