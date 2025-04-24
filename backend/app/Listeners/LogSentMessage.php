<?php

namespace App\Listeners;

use App\Models\business\Customer;
use App\Models\Company;
use App\Services\Mail\CleanEmailsJob;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Log;
use App\Models\EmailLog;

class LogSentMessage
{
    /**
     * Maneja el evento.
     */
    public function handle(MessageSent $event): void
    {
        try {
            // Obtener el dni de la persona que envió el correo
            $dni = $event->message->getHeaders()->get('X-DNI-COMPANY')?->getBody();
            $company = Company::where('dni', $dni)->first();
            // Obtener el destinatario
            $dni = $event->message->getHeaders()->get('X-CUSTOMER-DNI')?->getBody();
            $customer = Customer::where('dni', $dni)->first();
            // Obtener el documento
            $documentId = $event->message->getHeaders()->get('X-DOCUMENT-ID')?->getBody();
            // Obtener el tipo de documento
            $typeDocumentId = $event->message->getHeaders()->get('X-TYPE-DOCUMENT-ID')?->getBody();

            // Obtener el destinatario
            $to = $event->message->getTo()[0];
            // Obtener el ID del mensaje
            $messageId = $event->sent->getMessageId();

            // Limpiar los caracteres < y >
            $messageId = trim($messageId, '<>');

            // Obtener la dirección de correo electrónico del destinatario
            $email = $to->getAddress();

            // Libere el correo electrónico de la tabla de los jobs
            if($company && $documentId && $typeDocumentId){
                CleanEmailsJob::clean($company, $documentId, $typeDocumentId);
            }

            // Registrar en la base de datos
            EmailLog::create([
                'company_id' => $company->id ?? null,
                'customer_id' => $customer->id ?? null,
                'type_document_id' => $typeDocumentId,
                'document_id' => $documentId,
                'message_id' => $messageId,
                'email' => $email,
                'status' => 'sent',
            ]);
        } catch (\Exception $e) {
            Log::error('Error logging sent message: ' . $e->getMessage());
        }
    }
}
