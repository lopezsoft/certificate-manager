<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Application\UseCases;

use App\Modules\Viafirma\Domain\Contracts\ViafirmaClient;
use App\Modules\Viafirma\Domain\Enums\RemoteStatus;
use App\Modules\Viafirma\Domain\Exceptions\ViafirmaException;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use Psr\Log\LoggerInterface;

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
        private readonly LoggerInterface $logger,
    ) {}

    public function handle(int $viafirmaCertificateRequestId): string
    {
        $entity = ViafirmaCertificateRequest::findOrFail($viafirmaCertificateRequestId);

        // Validar que la solicitud esté en estado remoto 'accreditation'
        $remoteStatus = RemoteStatus::tryFrom((string) $entity->remote_status);

        if ($remoteStatus !== RemoteStatus::ACCREDITATION) {
            $currentStatus = $entity->remote_status ?? 'null';
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
            'remote_status'  => $entity->remote_status,
        ]);

        return $this->client->getAccreditationLink($entity->cod_request);
    }
}
