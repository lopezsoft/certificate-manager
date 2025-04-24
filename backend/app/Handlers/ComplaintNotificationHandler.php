<?php

namespace App\Handlers;

use App\Handlers\Contracts\NotificationHandlerInterface;
use App\Models\EmailLog;
use App\Models\EmailRecipientStatus;
use App\Models\SuppressedEmail;
use App\Services\Mail\EmailSuppressedNotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ComplaintNotificationHandler implements NotificationHandlerInterface
{
    public function handle(array $notification): void
    {
        // --- Validación inicial y extracción de datos ---
        if (!isset($notification['mail']['messageId']) || !isset($notification['complaint'])) {
            Log::error('Notificación de queja mal formada: faltan mail.messageId o complaint.', $notification);
            return;
        }
        $messageId = $notification['mail']['messageId'];
        $complaint = $notification['complaint'];
        $complainedRecipients = $complaint['complainedRecipients'] ?? [];
        // Usar el timestamp de la queja para precisión
        $complaintTimestamp = isset($complaint['timestamp']) ? Carbon::parse($complaint['timestamp']) : now();
        // Extraer tipo de queja si existe (ej: 'abuse', 'opt-out')
        $feedbackType = $complaint['complaintFeedbackType'] ?? null;

        $emailLog = EmailLog::where('message_id', $messageId)->first();

        if (!$emailLog) {
            Log::warning("Email log not found for Message ID $messageId");
            return;
        }
        $emailLog->status = 'complaint';
        $emailLog->complained_at = now();
        $emailLog->save();

        Log::info("Email complaint: Message ID $messageId");

        // Opcional: Excluir destinatarios de futuras comunicaciones
        foreach ($complainedRecipients as $recipient) {
            $emailAddress = $recipient['emailAddress'];
            // Implementar lógica para manejar el destinatario
            // Por ejemplo, marcarlo como 'no contactable' en la base de datos
            if (!$emailAddress) {
                continue;
            }

            // --- 1. Actualizar/Crear estado detallado del destinatario ---
            EmailRecipientStatus::updateOrCreate(
                [
                    'email_log_id' => $emailLog->id,
                    'recipient_email' => $emailAddress,
                ],
                [
                    'status' => 'complained', // Estado específico: Se quejó
                    'event_type' => 'Complaint', // Tipo de evento
                    // Guarda el tipo de feedback si está disponible, útil para análisis
                    'diagnostic_code' => $feedbackType,
                    'event_timestamp' => $complaintTimestamp, // Usa el timestamp de la queja
                    // Puedes decidir si quieres limpiar/resetear campos de rebote aquí
                    'bounce_type' => null,
                    'bounce_subtype' => null,
                    'smtp_status' => null,
                ]
            );
            // --- 2. Añadir a la lista de supresión ---
            SuppressedEmail::updateOrCreate(
                ['email' => $emailAddress], // Buscar/crear por email
                [
                    'reason_type' => 'Complaint', // La causa fue una queja
                    // Guarda el tipo de feedback (si existe) como subtipo para más detalle
                    'reason_subtype' => $feedbackType ?? 'Complaint',
                    'diagnostic_code' => null, // El diagnostic_code aplica más a bounces
                    'suppressed_at' => $complaintTimestamp, // Timestamp de la queja
                    'source' => 'SES_Notification', // Origen
                ]
            );

            // --- 3. Enviar notificación ---
            $messageData = (object)[
                'suppressedEmail' => $emailAddress,
                'reasonType' => 'Complaint',
                'reasonSubtype' => $feedbackType,
                'emailLog' => $emailLog,
                'diagnosticCode' => null,
            ];
            try {
                EmailSuppressedNotificationService::send($messageData);
            } catch (\Exception $e) {
                Log::error('Error sending complaint notification: ' . $e->getMessage());
            }
        }
    }
}
