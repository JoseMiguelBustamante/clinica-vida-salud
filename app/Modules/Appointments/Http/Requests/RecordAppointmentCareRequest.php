<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RecordAppointmentCareRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'verificado' => ['accepted'],
            'resumen_atencion' => ['nullable', 'string', 'max:2000'],
            'diagnostico' => ['nullable', 'string', 'max:2000'],
            'indicaciones' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'verificado.accepted' => 'Confirme que brindó la atención médica. La llegada no la sustituye.',
        ];
    }
}
