<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Application\Listeners;

use App\Modules\Viafirma\Domain\Enums\RemoteStatus;
use App\Modules\Viafirma\Domain\Events\ViafirmaStatusChanged;
use App\Modules\Viafirma\Infrastructure\Logging\SafePemLogger;

/**
 * Listener que envía notificación al cliente cuando el estado remoto
 * pasa a `accreditation` (KYC pendiente) (V-308).
 *
 * El cliente recibe un email con el link público de Viafirma para
 * completar la acreditación (foto de documento, selfie, etc.).
 *
 * URL pública: {config.download_url}/public/{public_id}
 */
final class NotifyClientOnAccreditationListener
{
    public function __construct(
        private readonly SafePemLogger $logger,
    ) {}

    public function handle(ViafirmaStatusChanged $event): void
    {
        // Solo actuar cuando el estado remoto es ACCREDITATION
        if ($event->remoteStatus !== RemoteStatus::ACCREDITATION) {
            return;
        }

        $entity   = $event->entity;
        $publicId = $entity->public_id;

        if ($publicId === null || $publicId === '') {
            $this->logger->warning('viafirma.listener.accreditation_no_public_id', [
                'id' => $entity->id,
            ]);
            return;
        }

        $kycUrl = rtrim((string) config('viafirma.download_url'), '/') . '/public/' . $publicId;

        // Obtener email del solicitante via relación
        $email = $entity->certificateRequest?->company?->email ?? null;

        if ($email === null || $email === '') {
            $this->logger->warning('viafirma.listener.accreditation_no_email', [
                'id' => $entity->id,
            ]);
            return;
        }

        // TODO Sprint 4-5: Reemplazar con Notification class + Mailable
        // Por ahora se registra el evento para que el sistema de notificaciones
        // lo procese cuando se implemente la plantilla de email.
        $this->logger->info('viafirma.listener.accreditation_notify', [
            'id'       => $entity->id,
            'email'    => $email,
            'kyc_url'  => $kycUrl,
        ]);

        // Crear notificación en base de datos (canal database de Laravel)
        // para que aparezca en el bell del frontend
        if ($entity->certificateRequest?->company) {
            $company = $entity->certificateRequest->company;
            $users   = $company->users ?? collect();

            foreach ($users as $user) {
                $user->notify(new \App\Modules\Viafirma\Application\Notifications\ViafirmaAccreditationPendingNotification(
                    viafirmaRequestId: $entity->id,
                    companyName:       $company->company_name ?? 'Empresa',
                    kycUrl:            $kycUrl,
                ));
            }
        }
    }
}
