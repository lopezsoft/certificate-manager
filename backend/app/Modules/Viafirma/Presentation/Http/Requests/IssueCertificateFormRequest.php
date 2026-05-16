<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Presentation\Http\Requests;

use App\Modules\Viafirma\Domain\Enums\IdentityType;
use App\Modules\Viafirma\Domain\Enums\OrganizationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validación del endpoint POST /api/v2/certificates/viafirma/issue (V-203).
 *
 * Discrimina reglas por contexto:
 *  - Si la empresa es PJ → `organization_type` es obligatorio.
 *  - Si la empresa es PN → `organization_type` NO debe enviarse.
 *
 * La resolución del perfil (FE_PJ/FE_PN) se delega al UseCase que consulta
 * los catálogos productivos. Este FormRequest sólo valida los datos que el
 * caller envía explícitamente.
 */
class IssueCertificateFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'certificate_request_id' => [
                'required',
                'integer',
                Rule::exists('certificate_requests', 'id'),
            ],
            'email_certificate' => [
                'required',
                'string',
                'email:rfc,dns',
                'max:150',
            ],
            'organization_type' => [
                'nullable',
                'string',
                Rule::in(array_column(OrganizationType::cases(), 'value')),
            ],
            'identity_type_override' => [
                'nullable',
                'string',
                Rule::in(array_column(IdentityType::cases(), 'value')),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'certificate_request_id.required' => 'La solicitud de certificado es obligatoria.',
            'certificate_request_id.exists'   => 'La solicitud de certificado no existe.',
            'email_certificate.required'      => 'El email para el certificado es obligatorio.',
            'email_certificate.email'         => 'El email para el certificado no es válido.',
            'organization_type.in'            => 'El tipo de organización Viafirma no es válido. Valores permitidos: '
                . implode(', ', array_column(OrganizationType::cases(), 'value')),
            'identity_type_override.in'       => 'El tipo de identidad no es válido. Valores permitidos: '
                . implode(', ', array_column(IdentityType::cases(), 'value')),
        ];
    }
}
