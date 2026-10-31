<?php

declare(strict_types=1);

namespace App\Modules\Schedules\Http\Requests;

use App\Modules\Schedules\ScheduleService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dia_semana' => ['required', 'string', Rule::in(ScheduleService::WEEKDAYS)],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fin' => ['required', 'date_format:H:i', 'after:hora_inicio'],
            'duracion_cita_minutos' => ['required', 'integer', 'min:1', 'max:1440'],
        ];
    }

    public function messages(): array
    {
        return [
            'dia_semana.required' => 'Seleccione el día de atención.',
            'dia_semana.in' => 'El día de atención no es válido.',
            'hora_inicio.required' => 'Indique la hora de inicio.',
            'hora_fin.required' => 'Indique la hora de finalización.',
            'hora_fin.after' => 'La hora final debe ser posterior a la inicial.',
            'duracion_cita_minutos.required' => 'Indique la duración de las citas.',
            'duracion_cita_minutos.min' => 'La duración de las citas debe ser positiva.',
        ];
    }
}
