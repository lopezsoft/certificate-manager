<?php

namespace App\Handlers;

use App\Handlers\Contracts\NotificationHandlerInterface;
use App\Models\EmailLog;
use App\Models\EmailRecipientStatus;
use App\Models\SuppressedEmail;
use App\Services\Mail\EmailSuppressedNotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class BounceNotificationHandler implements NotificationHandlerInterface
{
    public function handle(array $notification): void
    {
        $messageId = $notification['mail']['messageId'];
        $bounce = $notification['bounce'];
        $bounceType = $bounce['bounceType'] ?? 'Unknown';
        $bounceSubType = $bounce['bounceSubType'] ?? 'Unknown';
        $bouncedRecipients = $bounce['bouncedRecipients'] ?? [];
        $bounceTimestamp = isset($bounce['timestamp']) ? Carbon::parse($bounce['timestamp']) : now();


        $emailLog = EmailLog::where('message_id', $messageId)->first();

        if (!$emailLog) {
            Log::warning("Email log not found for Message ID $messageId");
            return;
        }
        $emailLog->status = 'bounced';
        $emailLog->bounce_type = $bounceType;
        $emailLog->bounce_subtype = $bounceSubType;
        $emailLog->bounced_at = now();
        $emailLog->save();

        // Log::info("Email bounced: Message ID $messageId, Type: $bounceType");

        // Opcional: Actualizar estado de destinatarios
        foreach ($bouncedRecipients as $recipient) {
            // Log::info("Processing bounced recipient: " . json_encode($recipient));
            $emailAddress = $recipient['emailAddress'] ?? null;
            // Implementar lógica para manejar el destinatario
            // Por ejemplo, marcar el email como inválido en la base de datos
            if (!$emailAddress) {
                continue;
            }

            $smtpStatusCode = $recipient['status'] ?? null;
            $diagnosticCode = $recipient['diagnosticCode'] ?? null;

            // Actualizar o crear (por si acaso no se creó inicialmente) el registro del destinatario
            EmailRecipientStatus::updateOrCreate(
                [
                    'email_log_id' => $emailLog->id,
                    'recipient_email' => $emailAddress,
                ],
                [
                    'status' => 'bounced', // Actualizar estado específico
                    'event_type' => 'Bounce',
                    'bounce_type' => $bounceType,
                    'bounce_subtype' => $bounceSubType,
                    'smtp_status' => $smtpStatusCode ? json_encode($smtpStatusCode) : null,
                    'diagnostic_code' => $diagnosticCode,
                    'event_timestamp' => $bounceTimestamp
                ]
            );
            // --- 2. Añadir a la lista de supresión si es Permanente o Suprimido ---
            // La condición principal para suprimir es que el rebote sea Permanente
            // o que SES ya lo marque como Suprimido.
            if (in_array($bounceType, ['Permanent','Suppressed']) || ($bounceType === 'Transient' && $bounceSubType === 'CustomTimeoutExceeded')) {
                SuppressedEmail::updateOrCreate(
                    ['email' => $emailAddress],
                    [
                        'reason_type' => 'Bounce',
                        'reason_subtype' => $bounceSubType ?? $bounceType,
                        'diagnostic_code' => $diagnosticCode,
                        'suppressed_at' => $bounceTimestamp,
                        'source' => 'SES_Notification'
                    ]
                );

                // --- 3. Enviar notificación ---
                $messageData = (object)[
                    'suppressedEmail' => $emailAddress,
                    'reasonType' => $bounceType,
                    'reasonSubtype' => $bounceSubType,
                    'emailLog' => $emailLog,
                    'diagnosticCode' => $diagnosticCode,
                ];

                try {
                    EmailSuppressedNotificationService::send($messageData);
                } catch (\Exception $e) {
                    Log::error("Error sending suppressed email notification: " . $e->getMessage());
                }

            }
        }
    }
}
