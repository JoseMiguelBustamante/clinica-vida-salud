<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\ActivityLogService;
use Illuminate\Foundation\Http\FormRequest;

final class SearchActivityLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'id_usuario' => ['nullable', 'integer', 'min:1'],
            'accion' => ['nullable', 'in:'.implode(',', ActivityLogService::ACTIONS)],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'pagina' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'accion.in' => 'La acción indicada no es válida.',
            'desde.date_format' => 'Indique una fecha inicial válida.',
            'hasta.date_format' => 'Indique una fecha final válida.',
            'hasta.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
        ];
    }
}
