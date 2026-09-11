<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Application\UseCases;

use App\Modules\Viafirma\Domain\StateMachine;
use App\Modules\Viafirma\Infrastructure\Logging\SafePemLogger;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use App\Services\QuotaService;

/**
 * Cancela UNA solicitud cuyo plazo de verificación KYC venció, y reintegra
 * el cupo consumido. Es una operación de inventario interno (no una
 * devolución financiera) — no borra la solicitud ni sus archivos, solo
 * transiciona el estado a EXPIRED para conservar el histórico.
 *
 * Asume que `$entity` ya viene con `state` y `certificateRequest.company`
 * cargados y FRESCOS (responsabilidad de quien orquesta — ver
 * ExpireStalledKycAccreditationsJob, que recarga desde BD bajo mutex antes
 * de llamar aquí, para evitar cancelar una solicitud que el usuario acaba
 * de completar en el margen). Este use case NO vuelve a tocar BD para leer,
 * solo para escribir el resultado de la cancelación.
 */
final class CancelExpiredKycRequestUseCase
{
    public function __construct(
        private readonly StateMachine $stateMachine,
        private readonly QuotaService $quotaService,
        private readonly SafePemLogger $logger,
    ) {}

    /**
     * @return string|null Nombre del solicitante si se canceló, o null si se omitió
     *                      (ya terminal, o el usuario completó el KYC en el margen).
     */
    public function handle(ViafirmaCertificateRequest $entity): ?string
    {
        if ($entity->state === null
            || $entity->isTerminal()
            || $entity->state->kyc_flow_completed_at !== null
        ) {
            return null;
        }

        $certificateRequest = $entity->certificateRequest;
        $company             = $certificateRequest?->company;

        if ($certificateRequest === null || $company === null) {
            $this->logger->warning('viafirma.kyc_expire.no_company', ['id' => $entity->id]);
            return null;
        }

        $applicantName = $certificateRequest->applicantDisplayName();

        // markExpired() dispara ViafirmaStatusChanged, que
        // ViafirmaRequestStateChangedListener::syncExpiredStatus() escucha
        // para sincronizar certificate_requests.request_status y registrar
        // el cambio en change_histories (historial visible de la solicitud)
        // — no se duplica esa escritura aquí.
        $this->stateMachine->markExpired($entity);

        $this->quotaService->releaseQuotaForRequest($company->id);

        $this->logger->info('viafirma.kyc_expire.cancelled', [
            'id'          => $entity->id,
            'cod_request' => $entity->cod_request,
            'company_id'  => $company->id,
        ]);

        return $applicantName;
    }
}
