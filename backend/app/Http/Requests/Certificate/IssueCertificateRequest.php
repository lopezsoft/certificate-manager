<?php

declare(strict_types=1);

namespace App\Http\Requests\Certificate;

use App\Modules\Viafirma\Domain\Enums\IdentityType;
use App\Modules\Viafirma\Domain\Enums\OrganizationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validación del nuevo endpoint unificado:
 *   POST /api/v1/certificate-request/{id}/issue
 *
 * El campo `provider` es OPCIONAL y SÓLO se honra cuando:
 *   - El llamante autenticado es admin (controlado por authorize()).
 *   - config('certificate.issuance.allow_payload_override') === true
 *
 * Si no se envía `provider`, lo resuelve la factory por config + reglas.
 *
 * `email_certificate` es obligatorio si el provider resuelto/forzado es 'viafirma'.
 * Para 'mail' es opcional (compat con /send-mail).
 */
class IssueCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'provider' => [
                'nullable',
                'string',
                Rule::in(array_keys((array) config('certificate.issuance.providers', []))),
            ],
            'email_certificate' => [
                'nullable',
                'string',
                'email:rfc',
                'max:150',
                'required_if:provider,viafirma',
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
            'comments' => ['nullable', 'string', 'max:1000'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'email_certificate.required_if' => 'El email del certificado es obligatorio para emisión Viafirma.',
            'email_certificate.email'       => 'El email del certificado no es válido.',
            'provider.in'                   => 'El proveedor solicitado no está registrado.',
            'organization_type.in'          => 'El tipo de organización no es válido.',
            'identity_type_override.in'     => 'El tipo de identidad no es válido.',
        ];
    }

    /**
     * Indica si el llamante puede usar el override de proveedor por payload.
     */
    public function callerCanOverrideProvider(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }
        // Compatibilidad con los dos esquemas de roles del proyecto.
        if (method_exists($user, 'hasRole') && $user->hasRole('admin')) {
            return true;
        }
        return (bool) ($user->is_admin ?? false);
    }
}

