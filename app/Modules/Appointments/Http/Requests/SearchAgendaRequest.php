<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SearchAgendaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha' => ['nullable', 'date_format:Y-m-d'],
            'id_medico' => ['nullable', 'integer'],
            'estado' => ['nullable', 'in:Programada,Confirmada,Atendida,Cancelada,Inasistencia'],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha.date_format' => 'Indique una fecha válida.',
            'estado.in' => 'El estado de la cita no es válido.',
        ];
    }
}
