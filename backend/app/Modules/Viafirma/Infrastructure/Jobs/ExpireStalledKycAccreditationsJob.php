<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Infrastructure\Jobs;

use App\Modules\Viafirma\Application\Notifications\ViafirmaKycExpiredNotification;
use App\Modules\Viafirma\Application\Notifications\ViafirmaKycLastCallNotification;
use App\Modules\Viafirma\Application\UseCases\CancelExpiredKycRequestUseCase;
use App\Modules\Viafirma\Domain\Contracts\KycWebhookNotifierContract;
use App\Modules\Viafirma\Domain\Enums\InternalState;
use App\Modules\Viafirma\Infrastructure\Logging\SafePemLogger;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * Cron horario: avisa (24h antes) y cancela (al vencer) solicitudes cuyo
 * usuario final nunca completó la verificación KYC.
 *
 * Desde que se eliminó la auto-expiración del polling, `expires_at` quedó
 * como dato puramente informativo (solo lo consumía el countdown del
 * frontend). Este job es quien cierra el ciclo: cancela, reintegra el cupo
 * consumido, y avisa a la Casa de Software por correo y WhatsApp (n8n).
 *
 * NO borra nada — ni la solicitud ni sus archivos adjuntos. Solo transiciona
 * el estado a EXPIRED (igual que cualquier otro caso FAILED/EXPIRED), para
 * conservar el histórico.
 */
final class ExpireStalledKycAccreditationsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $timeout = 300;
    public int $tries = 1;

    public function __construct()
    {
        $this->onQueue('notifications');
    }

    public function handle(
        CancelExpiredKycRequestUseCase $cancelUseCase,
        KycWebhookNotifierContract $webhookNotifier,
        SafePemLogger $logger,
    ): void {
        $this->sendLastCallWarnings($webhookNotifier, $logger);
        $this->expireOverdueRequests($cancelUseCase, $webhookNotifier, $logger);
    }

    /**
     * Paso A — aviso de último llamado, 24h antes de expires_at, una sola vez.
     */
    private function sendLastCallWarnings(KycWebhookNotifierContract $webhookNotifier, SafePemLogger $logger): void
    {
        $candidates = ViafirmaCertificateRequest::query()
            ->whereHas('state', function ($query) {
                $query->where('internal_state', InternalState::POLLING->value)
                    ->whereNotNull('kyc_accreditation_link')
                    ->whereNull('kyc_flow_completed_at')
                    ->whereNull('kyc_last_call_sent_at')
                    ->whereNotNull('expires_at')
                    ->whereBetween('expires_at', [now(), now()->addHours(24)]);
            })
            ->with(['state', 'certificateRequest.company'])
            ->get()
            ->filter(fn (ViafirmaCertificateRequest $e) => $e->certificateRequest?->company !== null
                && !empty($e->certificateRequest->company->email));

        if ($candidates->isEmpty()) {
            $logger->info('viafirma.kyc_last_call.nothing_pending');
            return;
        }

        $byCompany = $candidates->groupBy(fn (ViafirmaCertificateRequest $e) => $e->certificateRequest->company->id);

        foreach ($byCompany as $companyId => $requests) {
            $company     = $requests->first()->certificateRequest->company;
            $solicitudes = [];

            foreach ($requests as $entity) {
                $link = $entity->state?->kyc_accreditation_link;
                if (empty($link) || empty($entity->cod_request)) {
                    continue;
                }

                $applicantName = $entity->certificateRequest?->applicantDisplayName() ?? 'Solicitante';

                try {
                    Notification::route('mail', $company->email)->notify(
                        new ViafirmaKycLastCallNotification(
                            viafirmaRequestId: $entity->id,
                            companyName:       $company->company_name ?? 'Empresa',
                            applicantName:     $applicantName,
                            codRequest:        $entity->cod_request,
                            kycUrl:            $link,
                        )
                    );
                } catch (\Throwable $e) {
                    $logger->warning('viafirma.kyc_last_call.email_failed', [
                        'id'    => $entity->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                // Aislado: si marcar el aviso falla, no debe abortar el lote
                // completo. Se omite esta solicitud (se reintentará la
                // próxima hora) y se sigue con las demás.
                try {
                    $entity->state->kyc_last_call_sent_at = now();
                    $entity->state->save();
                } catch (\Throwable $e) {
                    $logger->error('viafirma.kyc_last_call.mark_failed', [
                        'id'    => $entity->id,
                        'error' => $e->getMessage(),
                    ]);
                    continue;
                }

                $solicitudes[] = ['codigo' => $entity->cod_request, 'enlace' => $link, 'nombre' => $applicantName];
            }

            if ($solicitudes === []) {
                continue;
            }

            $webhookNotifier->notify(
                casaSoftware: $company->company_name ?? 'Empresa',
                whatsapp: $company->phone,
                solicitudes: $solicitudes,
                tipo: 'ultimo_llamado',
            );

            $logger->info('viafirma.kyc_last_call.company_notified', [
                'company_id' => $companyId,
                'count'      => count($solicitudes),
            ]);
        }
    }

    /**
     * Paso B — cancelación efectiva de solicitudes ya vencidas.
     *
     * `expires_at <= now()` (no igualdad exacta): a propósito, para recoger en
     * cada pasada todas las vencidas acumuladas, sin importar hace cuánto
     * cruzaron el umbral.
     */
    private function expireOverdueRequests(
        CancelExpiredKycRequestUseCase $cancelUseCase,
        KycWebhookNotifierContract $webhookNotifier,
        SafePemLogger $logger,
    ): void {
        $overdue = ViafirmaCertificateRequest::query()
            ->whereHas('state', function ($query) {
                $query->where('internal_state', InternalState::POLLING->value)
                    ->whereNotNull('kyc_accreditation_link')
                    ->whereNull('kyc_flow_completed_at')
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<=', now());
            })
            ->with(['state', 'certificateRequest.company'])
            ->get()
            ->filter(fn (ViafirmaCertificateRequest $e) => $e->certificateRequest?->company !== null
                && !empty($e->certificateRequest->company->email));

        if ($overdue->isEmpty()) {
            $logger->info('viafirma.kyc_expire.nothing_overdue');
            return;
        }

        $byCompany = $overdue->groupBy(fn (ViafirmaCertificateRequest $e) => $e->certificateRequest->company->id);

        foreach ($byCompany as $companyId => $requests) {
            $company     = $requests->first()->certificateRequest->company;
            $solicitudes = [];

            foreach ($requests as $entity) {
                // Aislado por solicitud: un fallo en una no debe abortar el
                // resto del lote ni las demás empresas (antes, una excepción
                // aquí mataba el job completo — tries=1 — dejando solicitudes
                // sin procesar hasta la siguiente hora).
                try {
                    $applicantName = $this->cancelSingleRequest($entity, $cancelUseCase, $logger);
                } catch (\Throwable $e) {
                    $logger->error('viafirma.kyc_expire.request_failed', [
                        'id'    => $entity->id,
                        'error' => $e->getMessage(),
                    ]);
                    continue;
                }

                if ($applicantName === null) {
                    continue;
                }

                try {
                    Notification::route('mail', $company->email)->notify(
                        new ViafirmaKycExpiredNotification(
                            viafirmaRequestId: $entity->id,
                            companyName:       $company->company_name ?? 'Empresa',
                            applicantName:     $applicantName,
                            codRequest:        (string) $entity->cod_request,
                        )
                    );
                } catch (\Throwable $e) {
                    $logger->warning('viafirma.kyc_expire.email_failed', [
                        'id'    => $entity->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                $solicitudes[] = [
                    'codigo' => (string) $entity->cod_request,
                    'enlace' => (string) $entity->state?->kyc_accreditation_link,
                    'nombre' => $applicantName,
                ];
            }

            if ($solicitudes === []) {
                continue;
            }

            $webhookNotifier->notify(
                casaSoftware: $company->company_name ?? 'Empresa',
                whatsapp: $company->phone,
                solicitudes: $solicitudes,
                tipo: 'cancelacion',
            );

            $logger->info('viafirma.kyc_expire.company_notified', [
                'company_id' => $companyId,
                'count'      => count($solicitudes),
            ]);
        }
    }

    /**
     * Recarga fresca + mutex (protege contra la carrera de que el usuario
     * complete el KYC justo cuando este job procesa la misma solicitud —
     * misma clave de mutex que usa PollViafirmaStatusJob) y delega la
     * decisión de negocio a CancelExpiredKycRequestUseCase.
     *
     * @return string|null Nombre del solicitante si se canceló, null si se omitió.
     */
    private function cancelSingleRequest(
        ViafirmaCertificateRequest $entity,
        CancelExpiredKycRequestUseCase $cancelUseCase,
        SafePemLogger $logger,
    ): ?string {
        $mutexKey = "viafirma:poll:mutex:{$entity->id}";
        $lock = Cache::lock($mutexKey, 30);

        if (!$lock->get()) {
            $logger->info('viafirma.kyc_expire.mutex_busy', ['id' => $entity->id]);
            return null;
        }

        try {
            // Recarga explícita (no refresh()) — evita depender del comportamiento
            // implícito de refresh() con relaciones anidadas (certificateRequest.company).
            $entity->load(['state', 'certificateRequest.company']);

            return $cancelUseCase->handle($entity);
        } finally {
            $lock->release();
        }
    }
}
