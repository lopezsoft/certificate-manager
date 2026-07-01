<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Application\UseCases;

use App\Enums\DocumentStatusEnum;
use App\Models\ChangeHistory;
use App\Modules\Viafirma\Application\DTOs\RevokeInputDto;
use App\Modules\Viafirma\Domain\Contracts\ViafirmaClient;
use App\Modules\Viafirma\Domain\Enums\InternalState;
use App\Modules\Viafirma\Domain\Exceptions\ViafirmaException;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaStatusHistory;
use Illuminate\Support\Facades\DB;
use App\Modules\Viafirma\Infrastructure\Logging\SafePemLogger;

/**
 * RevokeCertificateUseCase — orquesta la revocación de un certificado Viafirma ya emitido.
 *
 * Flujo:
 *   1) Valida que el trámite esté en estado PROCESSED (único estado revocable).
 *   2) Llama a Viafirma API: POST /request/revoke/code/{revokingCode}
 *   3) Persiste el revocation_request_code devuelto + marca revoked_at.
 *   4) Transiciona internal_state → REVOKED.
 *   5) Sincroniza CertificateRequest.request_status → REVOKED (vía InternalState::toRequestStatus()).
 *   6) Registra en change_histories + viafirma_status_history para auditoría.
 */
final class RevokeCertificateUseCase
{
    public function __construct(
        private readonly ViafirmaClient $client,
        private readonly SafePemLogger $logger,
    ) {}

    public function handle(RevokeInputDto $dto): ViafirmaCertificateRequest
    {
        // ── 1) Cargar y validar estado ────────────────────────────────────
        $entity = ViafirmaCertificateRequest::with('certificateRequest')
            ->findOrFail($dto->viafirmaCertificateRequestId);
        
        $certificateRequest = $entity->certificateRequest;

        if (!$certificateRequest) {
            throw new ViafirmaException(
                "No se encontró la solicitud de certificado asociada (certificate_requests.id={$entity->certificate_request_id})."
            );
        }

        if ($certificateRequest->request_status !== DocumentStatusEnum::PROCESSED->value) {
            throw new ViafirmaException(
                "No se puede revocar un certificado que no esté en estado PROCESSED. " .
                "Estado actual: {$certificateRequest->request_status} (id={$entity->id})."
            );
        }

        $this->logger->info('viafirma.revoke.start', [
            'viafirma_cr_id'   => $entity->id,
            'revocation_reason' => $dto->revocationReason->value,
        ]);

        // ── 2) Llamada HTTP a Viafirma (fuera de transacción para no bloquear BD) ──
        $newRevocationCode = $this->client->revokeCertificate(
            $dto->revokingCode,
            $dto->revocationReason->value,
        );

        // ── 3-6) Persistencia en transacción ─────────────────────────────
        return DB::transaction(function () use ($entity, $dto, $newRevocationCode) {
            $state = $entity->state;
            $previousState = $state->internal_state;

            // Actualizar entidad de estado Viafirma
            $state->update([
                'internal_state'           => InternalState::REVOKED,
                'revocation_request_code'  => $newRevocationCode,
                'revoked_at'               => now(),
            ]);

            // Registrar en viafirma_status_history
            ViafirmaStatusHistory::create([
                'viafirma_certificate_request_id' => $entity->id,
                'previous_state'                  => $previousState->value,
                'new_state'                       => InternalState::REVOKED->value,
                'remote_status'                   => null,
                'raw_response'                    => [
                    'action'                  => 'revocation',
                    'revocation_request_code' => $newRevocationCode,
                    'revocation_reason'       => $dto->revocationReason->value,
                    'revocation_reason_label' => $dto->revocationReason->label(),
                ],
                'attempt_number' => $state->poll_attempts,
                'occurred_at'    => now(),
            ]);

            // Sincronizar CertificateRequest principal → REVOKED (estado unificado vía mapper).
            $cr = $entity->certificateRequest;
            $revokedStatus = DocumentStatusEnum::getRevoked();
            $cr->update(['request_status' => $revokedStatus]);

            // Registrar en change_histories del módulo legacy
            ChangeHistory::create([
                'certificate_request_id' => $cr->id,
                'user_id'                => $dto->revokedByUserId,
                'user_of_change'         => 'USER',
                'status'                 => $revokedStatus,
                'comments'               => "Certificado revocado. Motivo: {$dto->revocationReason->label()}. " .
                                            "Código de revocación Viafirma: {$newRevocationCode}.",
            ]);

            $this->logger->info('viafirma.revoke.completed', [
                'viafirma_cr_id'          => $entity->id,
                'revocation_request_code' => $newRevocationCode,
                'reason'                  => $dto->revocationReason->label(),
            ]);

            return $entity->fresh();
        });
    }
}
