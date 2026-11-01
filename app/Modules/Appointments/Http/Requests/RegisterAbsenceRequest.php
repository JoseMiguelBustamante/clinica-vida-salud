<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RegisterAbsenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'verificado' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'verificado.accepted' => 'Confirme que verificó que el paciente no se presentó.',
        ];
    }
}
