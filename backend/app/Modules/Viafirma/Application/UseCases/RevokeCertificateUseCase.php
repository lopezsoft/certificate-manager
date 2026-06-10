<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Application\UseCases;

use App\Enums\CertificateRequestStatusEnum;
use App\Models\ChangeHistory;
use App\Modules\Viafirma\Application\DTOs\RevokeInputDto;
use App\Modules\Viafirma\Domain\Contracts\ViafirmaClient;
use App\Modules\Viafirma\Domain\Enums\InternalState;
use App\Modules\Viafirma\Domain\Exceptions\ViafirmaException;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaStatusHistory;
use Illuminate\Support\Facades\DB;
use Psr\Log\LoggerInterface;

/**
 * RevokeCertificateUseCase — orquesta la revocación de un certificado Viafirma ya emitido.
 *
 * Flujo:
 *   1) Valida que el trámite esté en estado COMPLETED (único estado revocable).
 *   2) Llama a Viafirma API: POST /request/revoke/code/{revokingCode}
 *   3) Persiste el revocation_request_code devuelto + marca revoked_at.
 *   4) Transiciona internal_state → REVOKED.
 *   5) Sincroniza CertificateRequest.request_status → REJECTED.
 *   6) Registra en change_histories + viafirma_status_history para auditoría.
 */
final class RevokeCertificateUseCase
{
    public function __construct(
        private readonly ViafirmaClient $client,
        private readonly LoggerInterface $logger,
    ) {}

    public function handle(RevokeInputDto $dto): ViafirmaCertificateRequest
    {
        // ── 1) Cargar y validar estado ────────────────────────────────────
        $entity = ViafirmaCertificateRequest::with('certificateRequest')
            ->findOrFail($dto->viafirmaCertificateRequestId);

        if ($entity->internal_state !== InternalState::COMPLETED) {
            throw new ViafirmaException(
                "Solo se pueden revocar certificados en estado COMPLETED. " .
                "Estado actual: {$entity->internal_state->value} (id={$entity->id})."
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
            $previousState = $entity->internal_state;

            // Actualizar entidad Viafirma
            $entity->update([
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
                'attempt_number' => $entity->poll_attempts,
                'occurred_at'    => now(),
            ]);

            // Sincronizar CertificateRequest principal → REJECTED
            $cr = $entity->certificateRequest;
            if ($cr) {
                $cr->update(['request_status' => CertificateRequestStatusEnum::REJECTED->value]);

                // Registrar en change_histories del módulo legacy
                ChangeHistory::create([
                    'certificate_request_id' => $cr->id,
                    'user_id'                => $dto->revokedByUserId,
                    'user_of_change'         => 'Sistema (Revocación Viafirma)',
                    'status'                 => CertificateRequestStatusEnum::REJECTED->value,
                    'comments'               => "Certificado revocado. Motivo: {$dto->revocationReason->label()}. " .
                                                "Código de revocación Viafirma: {$newRevocationCode}.",
                ]);
            }

            $this->logger->info('viafirma.revoke.completed', [
                'viafirma_cr_id'          => $entity->id,
                'revocation_request_code' => $newRevocationCode,
                'reason'                  => $dto->revocationReason->label(),
            ]);

            return $entity->fresh();
        });
    }
}
