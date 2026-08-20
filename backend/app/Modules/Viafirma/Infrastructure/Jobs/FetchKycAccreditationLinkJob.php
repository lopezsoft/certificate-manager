<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Infrastructure\Jobs;

use App\Modules\Viafirma\Application\Notifications\ViafirmaAccreditationPendingNotification;
use App\Modules\Viafirma\Domain\Contracts\ViafirmaClient;
use App\Modules\Viafirma\Domain\Exceptions\TransientHttpException;
use App\Modules\Viafirma\Domain\Exceptions\ViafirmaClientException;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use App\Modules\Viafirma\Infrastructure\Logging\SafePemLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Notification;

/**
 * Obtiene y persiste el link KYC cuando la solicitud entra en estado `accreditation`.
 *
 * Este job es una optimización de cacheo: captura el link automáticamente para
 * que permanezca disponible incluso si Viafirma avanza el remote_status más
 * allá de 'accreditation'. Si el link no puede obtenerse (error 400 transitorio
 * o no transitorio), el usuario aún puede obtenerlo on-demand vía GetKycLinkUseCase.
 */
final class FetchKycAccreditationLinkJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;
    public int $timeout = 30;

    public function __construct(
        public readonly int $requestId,
    ) {}

    public function uniqueId(): string
    {
        return "viafirma-kyc-link-{$this->requestId}";
    }

    public function uniqueFor(): int
    {
        return 300;
    }

    public function tags(): array
    {
        return ["viafirma:kyc-link:{$this->requestId}"];
    }

    public function handle(
        ViafirmaClient $client,
        SafePemLogger $logger,
    ): void {
        $entity = ViafirmaCertificateRequest::with(['state', 'certificateRequest.company'])->find($this->requestId);

        if (!$entity) {
            $logger->warning('viafirma.kyc_link_job.entity_not_found', [
                'request_id' => $this->requestId,
            ]);
            return;
        }

        // Idempotencia: si ya existe el link persistido, no hacer nada
        if (!empty($entity->state?->kyc_accreditation_link)) {
            $logger->info('viafirma.kyc_link_job.already_cached', [
                'id'  => $entity->id,
                'cod' => $entity->cod_request,
            ]);
            return;
        }

        if (!$entity->cod_request) {
            $logger->warning('viafirma.kyc_link_job.no_cod_request', [
                'id' => $entity->id,
            ]);
            return;
        }

        try {
            $logger->info('viafirma.kyc_link_job.fetching', [
                'id'  => $entity->id,
                'cod' => $entity->cod_request,
            ]);

            $link = $client->getAccreditationLink($entity->cod_request);

            $entity->state->kyc_accreditation_link = $link;
            $entity->state->save();

            $logger->info('viafirma.kyc_link_job.success', [
                'id'   => $entity->id,
                'link' => $link,
            ]);

            $this->notifyMasterCompany($entity, $link, $logger);
        } catch (TransientHttpException $e) {
            // Reintentable — dejar que Laravel lo reintente
            $logger->warning('viafirma.kyc_link_job.transient_error', [
                'id'      => $entity->id,
                'attempt' => $this->attempts(),
                'error'   => $e->getMessage(),
            ]);
            throw $e;
        } catch (ViafirmaClientException $e) {
            // No transitoria (ej. 400) — loguear como warning pero NO relanzar
            // El link aún estará disponible on-demand mientras remote_status = accreditation
            $logger->warning('viafirma.kyc_link_job.client_error', [
                'id'    => $entity->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Avisa por correo a la empresa dueña de la solicitud (no al suscriptor
     * final, que ya recibe el link directamente de Viafirma) para que pueda
     * compartirlo con su cliente. Errores de envío no deben marcar el job
     * como fallido — el link ya quedó persistido correctamente.
     */
    private function notifyMasterCompany(ViafirmaCertificateRequest $entity, string $link, SafePemLogger $logger): void
    {
        $company = $entity->certificateRequest?->company;

        if ($company === null || empty($company->email)) {
            $logger->warning('viafirma.kyc_link_job.no_company_email', [
                'id' => $entity->id,
            ]);
            return;
        }

        try {
            Notification::route('mail', $company->email)->notify(
                new ViafirmaAccreditationPendingNotification(
                    viafirmaRequestId: $entity->id,
                    companyName:       $company->company_name ?? 'Empresa',
                    kycUrl:            $link,
                )
            );

            $logger->info('viafirma.kyc_link_job.company_notified', [
                'id'    => $entity->id,
                'email' => $company->email,
            ]);
        } catch (\Throwable $e) {
            $logger->warning('viafirma.kyc_link_job.notify_failed', [
                'id'    => $entity->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
