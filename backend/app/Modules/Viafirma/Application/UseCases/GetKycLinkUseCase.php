<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Application\UseCases;

use App\Modules\Viafirma\Domain\Contracts\ViafirmaClient;
use App\Modules\Viafirma\Domain\Enums\RemoteStatus;
use App\Modules\Viafirma\Domain\Exceptions\ViafirmaException;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use App\Modules\Viafirma\Infrastructure\Logging\SafePemLogger;

/**
 * GetKycLinkUseCase — obtiene el enlace del portal KYC (onboarding/acreditación).
 *
 * Pre-condición: la solicitud debe estar en estado remoto `accreditation`.
 * El link está disponible únicamente mientras Viafirma mantiene ese estado.
 */
final class GetKycLinkUseCase
{
    public function __construct(
        private readonly ViafirmaClient $client,
        private readonly SafePemLogger $logger,
    ) {}

    public function handle(int $viafirmaCertificateRequestId): string
    {
        $entity = ViafirmaCertificateRequest::with('state')->findOrFail($viafirmaCertificateRequestId);

        // Si el link ya está cacheado (capturado automáticamente al entrar a 'accreditation'),
        // retornarlo directamente sin hacer llamada HTTP — permite funcionamiento incluso si
        // Viafirma avanzó el remote_status más allá de 'accreditation'.
        if (!empty($entity->state?->kyc_accreditation_link)) {
            return $entity->state->kyc_accreditation_link;
        }

        // Validar que la solicitud esté en estado remoto 'accreditation'
        $remoteStatus = RemoteStatus::tryFrom((string) $entity->state?->remote_status);

        if ($remoteStatus !== RemoteStatus::ACCREDITATION) {
            $currentStatus = $entity->state?->remote_status ?? 'null';
            throw new ViafirmaException(
                "El link KYC solo está disponible cuando la solicitud está en estado 'accreditation'. " .
                "Estado remoto actual: {$currentStatus} (id={$entity->id})."
            );
        }

        if (!$entity->cod_request) {
            throw new ViafirmaException(
                "La solicitud Viafirma (id={$entity->id}) no tiene cod_request asignado."
            );
        }

        $this->logger->info('viafirma.kyc_link.requested', [
            'viafirma_cr_id' => $entity->id,
            'cod_request'    => $entity->cod_request,
            'remote_status'  => $entity->state?->remote_status,
        ]);

        $link = $this->client->getAccreditationLink($entity->cod_request, (string) $entity->public_id);

        // Persistir también en el camino on-demand — cubre registros creados antes de
        // que existiera el listener automático, o si el job aún no corrió.
        $entity->state->kyc_accreditation_link = $link;
        $entity->state->save();

        return $link;
    }
}
