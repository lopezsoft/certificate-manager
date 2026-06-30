<?php

namespace App\Listeners;

use App\Events\CertificateStatusChanged;
use App\Models\CertificateRequest;
use App\Notifications\CertificateRequestStatusNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Notification;

/**
 * Listener que envía notificación por email cuando el estado de una solicitud de certificado cambia.
 * 
 * Se dispara automáticamente cuando se emite el evento CertificateStatusChanged,
 * incluyendo cambios automáticos desde Viafirma (AssembleP12Job).
 */
class SendCertificateStatusNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public function handle(CertificateStatusChanged $event): void
    {
        // Cargar la solicitud de certificado con sus relaciones
        $certificateRequest = CertificateRequest::with(['company'])
            ->find($event->certificateRequestId);

        if ($certificateRequest === null) {
            return;
        }

        // Preparar datos para la notificación
        $messageData = (object) [
            'company'        => $certificateRequest->company,
            'data'           => $certificateRequest,
            'comments'       => $this->buildComments($event->newStatus, $event->comment),
            'request_status' => $event->newStatus,
        ];

        // Enviar notificación al email de soporte
        Notification::route('mail', config('certificate.mail.support_address'))
            ->notify(new CertificateRequestStatusNotification($messageData));

        // Enviar notificación al email de la empresa
        Notification::route('mail', $certificateRequest->company->email)
            ->notify(new CertificateRequestStatusNotification($messageData));
    }

    /**
     * Construye el mensaje de comentarios basado en el estado y el comentario proporcionado.
     */
    private function buildComments(string $newStatus, ?string $comment): string
    {
        // Si el estado es PROCESSED y no hay comentario personalizado, usar mensaje por defecto
        if ($newStatus === 'PROCESSED' && empty($comment)) {
            return "<p style='font-size: 12px;'>El certificado digital ha sido procesado exitosamente.</p>
                    <p style='font-size: 12px;'>Puede proceder con la descarga del certificado desde la interfaz web de CERTIFICATE MANAGER</p>
                    <p style='font-size: 12px'>Si tiene alguna pregunta o necesita más información, no dude en ponerse en contacto con nosotros.</p>";
        }

        // Si hay comentario personalizado, usarlo
        return $comment ?? '';
    }
}
