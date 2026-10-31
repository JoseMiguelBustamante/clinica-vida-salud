<?php

declare(strict_types=1);

namespace App\Modules\MedicalStaff\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SearchPhysiciansRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
        ];
    }
}
