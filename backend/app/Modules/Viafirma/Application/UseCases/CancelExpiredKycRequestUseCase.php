<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Application\UseCases;

use App\Modules\Viafirma\Domain\Enums\InternalState;
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
        //
        // Los side-effects (historial, listeners que escriben en BD y envían
        // correos) van aislados: si uno falla NO debe abortar la cancelación,
        // o la solicitud quedaría a medias (estado sin persistir, cupo sin
        // liberar, sin avisos) y el cron la reprocesaría cada hora.
        try {
            $this->stateMachine->markExpired($entity);
        } catch (\Throwable $e) {
            $this->logger->error('viafirma.kyc_expire.mark_expired_side_effect_failed', [
                'id'    => $entity->id,
                'error' => $e->getMessage(),
            ]);
        }

        // markExpired() asigna internal_state ANTES de cualquier side-effect,
        // así que a este punto ya debe estar en EXPIRED aunque algo haya
        // fallado. Si no lo está, la transición no ocurrió: no persistimos ni
        // notificamos una cancelación que en realidad no se hizo.
        if ($entity->state->internal_state !== InternalState::EXPIRED) {
            $this->logger->error('viafirma.kyc_expire.transition_did_not_apply', [
                'id'    => $entity->id,
                'state' => $entity->state->internal_state?->value,
            ]);
            return null;
        }

        // StateMachine NUNCA persiste el state — por convención lo hace el
        // llamador (igual que PollViafirmaStatusJob tras cada transition()).
        // Sin este save(), internal_state seguía en POLLING en BD y el cron
        // volvía a "cancelar" la misma solicitud cada hora, reenviando correo
        // y webhook indefinidamente (bug real en producción, solicitud 1223).
        // El estado también debe dejar de estar programado para polling.
        $entity->state->next_poll_at = null;
        $entity->state->save();

        // releaseQuotaForCancelledRequest() desvincula el item de ESTA
        // solicitud antes de liberarlo — bug real detectado en producción
        // (solicitud 1223): sin ese desvincule, releaseQuotaForRequest()
        // (que solo encuentra items con certificate_request_id NULL) nunca
        // lo encontraba y el cupo jamás se reintegraba.
        //
        // Aislado también: un fallo liberando el cupo no debe impedir que se
        // avise a la empresa de que su solicitud fue cancelada (bug real:
        // un "undefined method" aquí abortaba correo + webhook del flujo).
        try {
            $released = $this->quotaService->releaseQuotaForCancelledRequest($certificateRequest->id, $company->id);
        } catch (\Throwable $e) {
            $released = false;
            $this->logger->error('viafirma.kyc_expire.quota_release_failed', [
                'id'                     => $entity->id,
                'certificate_request_id' => $certificateRequest->id,
                'company_id'             => $company->id,
                'error'                  => $e->getMessage(),
            ]);
        }

        if (!$released) {
            $this->logger->warning('viafirma.kyc_expire.quota_not_released', [
                'id'                     => $entity->id,
                'certificate_request_id' => $certificateRequest->id,
                'company_id'             => $company->id,
            ]);
        }

        $this->logger->info('viafirma.kyc_expire.cancelled', [
            'id'          => $entity->id,
            'cod_request' => $entity->cod_request,
            'company_id'  => $company->id,
            'quota_released' => $released,
        ]);

        return $applicantName;
    }
}
