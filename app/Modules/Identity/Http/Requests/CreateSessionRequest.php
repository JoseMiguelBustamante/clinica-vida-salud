<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreateSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'identifier.required' => 'Indique el usuario o el correo de acceso.',
            'identifier.string' => 'Indique el usuario o el correo de acceso.',
            'identifier.max' => 'Indique el usuario o el correo de acceso.',
            'password.required' => 'Indique la contraseña.',
            'password.string' => 'Indique la contraseña.',
        ];
    }
}
