<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Presentation\Http\Requests;

use App\Modules\Viafirma\Domain\Enums\RevocationReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validación del endpoint POST /api/v1/certificate-request/{id}/revoke.
 */
class RevokeCertificateFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'revoking_code' => [
                'required',
                'string',
                'max:255',
            ],
            'revocation_reason' => [
                'required',
                'integer',
                Rule::in(RevocationReason::values()),
            ],
        ];
    }

    public function messages(): array
    {
        $validReasons = implode(', ', RevocationReason::values());

        return [
            'revoking_code.required'       => 'El código de revocación es obligatorio.',
            'revoking_code.string'         => 'El código de revocación debe ser una cadena de texto.',
            'revocation_reason.required'   => 'El motivo de revocación es obligatorio.',
            'revocation_reason.integer'    => 'El motivo de revocación debe ser un número entero.',
            'revocation_reason.in'         => "El motivo de revocación no es válido. Valores permitidos: {$validReasons}.",
        ];
    }
}
