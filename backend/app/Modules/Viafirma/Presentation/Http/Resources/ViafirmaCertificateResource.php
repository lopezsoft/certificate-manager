<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource para serializar {@see \App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest}.
 *
 * Oculta campos sensibles (key_vault_ref, csr_pem, p12_password_ref, payloads crudos)
 * y formatea timestamps de forma consistente con el resto de la API.
 */
class ViafirmaCertificateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'certificate_request_id' => $this->certificate_request_id,
            'company_id'             => $this->company_id,
            'requested_by_user_id'   => $this->requested_by_user_id,

            // Identificadores Viafirma
            'cod_request'            => $this->cod_request,
            'public_id'              => $this->public_id,
            'ra_code'                => $this->ra_code,

            // Perfil y tipo
            'profile_type'           => $this->profile_type?->value,
            'profile_type_label'     => $this->profile_type?->label(),
            'identity_type'          => $this->identity_type?->value,
            'country_code'           => $this->country_code,
            'organization_type'      => $this->organization_type?->value,
            'validity_days'          => $this->validity_days,

            // Estado
            'internal_state'         => $this->internal_state?->value,
            'remote_status'          => $this->remote_status,
            'is_terminal'            => $this->isTerminal(),
            'is_failed'              => $this->isFailed(),
            'has_expired'            => $this->hasExpired(),

            // Auditoría criptográfica (solo fingerprint, no material)
            'csr_fingerprint'        => $this->csr_fingerprint,

            // Polling
            'poll_attempts'          => $this->poll_attempts,
            'next_poll_at'           => $this->next_poll_at?->toIso8601String(),
            'last_polled_at'         => $this->last_polled_at?->toIso8601String(),

            // Timestamps del ciclo de vida
            'submitted_at'           => $this->submitted_at?->toIso8601String(),
            'downloaded_at'          => $this->downloaded_at?->toIso8601String(),
            'assembled_at'           => $this->assembled_at?->toIso8601String(),
            'expires_at'             => $this->expires_at?->toIso8601String(),

            // Errores (si aplica)
            'last_error_code'        => $this->last_error_code,
            'last_error_message'     => $this->last_error_message,

            // Relaciones cargadas (condicional)
            'certificate_request'    => $this->whenLoaded('certificateRequest'),
            'company'                => $this->whenLoaded('company'),
            'status_history'         => $this->whenLoaded('statusHistory'),

            'created_at'             => $this->created_at?->toIso8601String(),
            'updated_at'             => $this->updated_at?->toIso8601String(),
        ];
    }
}
