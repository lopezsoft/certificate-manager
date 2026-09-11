<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Application\Listeners;

use App\Modules\Viafirma\Domain\Events\ViafirmaRequestFailed;
use App\Modules\Viafirma\Infrastructure\Logging\SafePemLogger;

/**
 * Listener que maneja fallos detectados en solicitudes Viafirma.
 *
 * Responsabilidades:
 * - Registro detallado del fallo con contexto (request_id, tipo de fallo, empresa)
 * - Notificación por email al operador RA (vía MAIL_SUPPORT_ADDRESS en .env)
 * - Auditoría de alertas (registradas en logs)
 *
 * Reduce latencia de detección: sin este listener, los fallos son "silenciosos"
 * y solo se descubren por revisión manual (30-38 min típico).
 * Con este listener, se notifica por email en <2 segundos.
 *
 * V-311: Listener para ViafirmaRequestFailed (detectar fallos recuperables e irrecuperables).
 */
final class ViafirmaRequestFailedListener
{
    public function __construct(
        private readonly SafePemLogger $logger,
    ) {}

    public function handle(ViafirmaRequestFailed $event): void
    {
        $entity = $event->entity;
        $state  = $entity->state;

        $context = [
            'viafirma_request_id'      => $entity->id,
            'certificate_request_id'   => $entity->certificate_request_id,
            'company_name'             => $entity->certificateRequest?->company_name ?? 'Unknown',
            'company_nit'              => $entity->certificateRequest?->dni ?? 'Unknown',
            'internal_state'           => $state->internal_state->value,
            'remote_status'            => $state->remote_status ?? 'unknown',
            'error_code'               => $event->errorCode,
            'error_message'            => $event->errorMessage,
            'poll_attempts'            => $state->poll_attempts,
            'submitted_at'             => $state->submitted_at?->toIso8601String(),
            'timestamp'                => now()->toIso8601String(),
        ];

        // Log crítico con contexto completo
        $this->logger->error('viafirma.request.failed', $context);

        // Notificación por email al operador RA (vía MAIL_SUPPORT_ADDRESS en .env)
        if ($this->isEmailNotificationEnabled()) {
            $this->notifyByEmail($entity, $context);
        }
    }

    private function notifyByEmail(mixed $entity, array $context): void
    {
        try {
            $supportEmail = config('mail.support_address');

            if (!$supportEmail) {
                $this->logger->warning('viafirma.email_notification_skipped_no_email', [
                    'request_id' => $entity->id,
                ]);
                return;
            }

            $isExpiration = $context['error_code'] === 'POLL_EXPIRED';

            \Illuminate\Support\Facades\Mail::raw(
                $isExpiration
                    ? $this->buildExpirationEmailMessage($context)
                    : $this->buildEmailMessage($entity, $context),
                function ($message) use ($supportEmail, $context, $isExpiration) {
                    $subject = $isExpiration
                        ? 'ℹ️ Viafirma: Solicitud cancelada por vencimiento de KYC - ' . $context['company_name']
                        : '🚨 Viafirma: Fallo en solicitud - ' . $context['company_name'];

                    $message
                        ->to($supportEmail)
                        ->subject($subject)
                        ->from(config('mail.from.address'), config('mail.from.name'));
                }
            );

            $this->logger->info('viafirma.email_notified', [
                'request_id' => $entity->id,
                'to_email'   => $supportEmail,
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('viafirma.email_notification_failed', [
                'request_id' => $entity->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    private function buildEmailMessage(mixed $entity, array $context): string
    {
        $company = $context['company_name'];
        $nit     = $context['company_nit'];
        $error   = $context['error_message'];
        $state   = $context['internal_state'];
        $remote  = $context['remote_status'];

        return <<<TEXT
SOLICITUD VIAFIRMA FALLIDA - ACCIÓN REQUERIDA

Empresa: {$company} (NIT: {$nit})
ID Viafirma: {$context['viafirma_request_id']}
ID Solicitud: {$context['certificate_request_id']}

ESTADO INTERNO: {$state}
ESTADO REMOTO: {$remote}
CÓDIGO DE ERROR: {$context['error_code']}
MENSAJE: {$error}

Intentos de polling: {$context['poll_attempts']}
Enviada: {$context['submitted_at']}
Detectado: {$context['timestamp']}

Requiere atención del operador RA para investigar y resolver.
TEXT;
    }

    /**
     * A diferencia de un fallo real, POLL_EXPIRED es un resultado esperado
     * del cron ExpireStalledKycAccreditationsJob: el usuario final nunca
     * completó la verificación KYC dentro del plazo. El cron YA canceló la
     * solicitud y reintegró el cupo automáticamente — no requiere ninguna
     * acción del operador RA ni del proveedor (nunca hubo un certificado
     * emitido que revocar). Este correo es solo informativo.
     */
    private function buildExpirationEmailMessage(array $context): string
    {
        $company = $context['company_name'];
        $nit     = $context['company_nit'];
        $remote  = $context['remote_status'];

        return <<<TEXT
SOLICITUD CANCELADA POR VENCIMIENTO DE VERIFICACIÓN KYC (informativo)

Empresa: {$company} (NIT: {$nit})
ID Viafirma: {$context['viafirma_request_id']}
ID Solicitud: {$context['certificate_request_id']}

El usuario final no completó la verificación de identidad (KYC) dentro del
plazo configurado. La solicitud fue cancelada automáticamente y el cupo
consumido ya fue reintegrado — no requiere ninguna acción de tu parte.

ESTADO REMOTO AL MOMENTO DE CANCELAR: {$remote}
Intentos de polling: {$context['poll_attempts']}
Enviada: {$context['submitted_at']}
Cancelada: {$context['timestamp']}
TEXT;
    }

    private function isEmailNotificationEnabled(): bool
    {
        return (bool) config('mail.support_address');
    }
}
