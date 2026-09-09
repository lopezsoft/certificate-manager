<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Infrastructure\Jobs;

use App\Modules\Viafirma\Application\Notifications\ViafirmaAccreditationPendingNotification;
use App\Modules\Viafirma\Domain\Contracts\KycWebhookNotifierContract;
use App\Modules\Viafirma\Domain\Enums\InternalState;
use App\Modules\Viafirma\Infrastructure\Logging\SafePemLogger;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Notification;

/**
 * Recordatorio diario (8-9 AM) de verificación KYC pendiente.
 *
 * Reenvía el correo `ViafirmaAccreditationPendingNotification` a la empresa
 * dueña de cada solicitud que sigue esperando que el usuario final complete
 * el flujo de MetaMap, y dispara un aviso de WhatsApp (n8n) agrupado por
 * empresa con `tipo: recordatorio`.
 *
 * "Pendiente" = el link ya fue generado, el usuario AÚN no ha completado el
 * flujo en el navegador (kyc_flow_completed_at es null), la solicitud sigue
 * en POLLING, y no han pasado más de `viafirma.kyc.reminder_max_days` desde
 * que se envió a Viafirma — pasado ese plazo se deja de insistir.
 */
final class ResendPendingKycAccreditationNotificationsJob implements ShouldQueue
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

    public function handle(KycWebhookNotifierContract $webhookNotifier, SafePemLogger $logger): void
    {
        $maxDays = (int) config('viafirma.kyc.reminder_max_days', 14);
        $cutoff  = now()->subDays($maxDays);

        $pending = ViafirmaCertificateRequest::query()
            ->whereHas('state', function ($query) use ($cutoff) {
                $query->where('internal_state', InternalState::POLLING->value)
                    ->whereNotNull('kyc_accreditation_link')
                    ->whereNull('kyc_flow_completed_at')
                    ->where('submitted_at', '>=', $cutoff);
            })
            ->with(['state', 'certificateRequest.company'])
            ->get()
            ->filter(fn (ViafirmaCertificateRequest $e) => $e->certificateRequest?->company !== null
                && !empty($e->certificateRequest->company->email));

        if ($pending->isEmpty()) {
            $logger->info('viafirma.kyc_reminder.nothing_pending');
            return;
        }

        $byCompany = $pending->groupBy(fn (ViafirmaCertificateRequest $e) => $e->certificateRequest->company->id);

        foreach ($byCompany as $companyId => $requests) {
            $company     = $requests->first()->certificateRequest->company;
            $solicitudes = [];

            foreach ($requests as $entity) {
                $link = $entity->state?->kyc_accreditation_link;
                if (empty($link) || empty($entity->cod_request)) {
                    continue;
                }

                try {
                    Notification::route('mail', $company->email)->notify(
                        new ViafirmaAccreditationPendingNotification(
                            viafirmaRequestId: $entity->id,
                            companyName:       $company->company_name ?? 'Empresa',
                            kycUrl:            $link,
                        )
                    );

                    $solicitudes[] = ['codigo' => $entity->cod_request, 'enlace' => $link];
                } catch (\Throwable $e) {
                    $logger->warning('viafirma.kyc_reminder.email_failed', [
                        'id'    => $entity->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if ($solicitudes === []) {
                continue;
            }

            $webhookNotifier->notify(
                casaSoftware: $company->company_name ?? 'Empresa',
                whatsapp: $company->phone,
                solicitudes: $solicitudes,
                tipo: 'recordatorio',
            );

            $logger->info('viafirma.kyc_reminder.company_notified', [
                'company_id' => $companyId,
                'count'      => count($solicitudes),
            ]);
        }
    }
}
