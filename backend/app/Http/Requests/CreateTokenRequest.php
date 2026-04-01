<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxDays = config('tokens.max_expiration_days', 365);

        return [
            'name'            => ['required', 'string', 'min:3', 'max:255'],
            'expires_in_days' => ['sometimes', 'integer', 'min:1', "max:{$maxDays}"],
            'abilities'       => ['sometimes', 'array'],
            'abilities.*'     => ['string'],
        ];
    }

    public function messages(): array
    {
        $maxDays = config('tokens.max_expiration_days', 365);

        return [
            'name.required'           => 'El nombre del token es requerido.',
            'name.min'                => 'El nombre debe tener al menos 3 caracteres.',
            'name.max'                => 'El nombre no puede exceder los 255 caracteres.',
            'expires_in_days.integer' => 'La duración debe ser un número entero de días.',
            'expires_in_days.min'     => 'La duración mínima es 1 día.',
            'expires_in_days.max'     => "La duración máxima es {$maxDays} días.",
        ];
    }
}
