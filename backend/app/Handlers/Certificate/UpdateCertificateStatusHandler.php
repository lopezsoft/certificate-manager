<?php

namespace App\Handlers\Certificate;

use App\Commands\Certificate\UpdateCertificateStatusCommand;
use App\Common\HttpResponseMessages;
use App\Common\MessageExceptionResponse;
use App\Enums\CertificateRequestStatusEnum;
use App\Events\CertificateStatusChanged;
use App\Models\CertificateRequest;
use App\Models\ChangeHistory;
use App\Notifications\CertificateRequestStatusNotification;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Handler para actualizar el estado de una solicitud de certificado.
 *
 * Aplica Command Pattern: recibe UpdateCertificateStatusCommand,
 * persiste el cambio de estado, registra el historial y dispara
 * notificaciones/eventos correspondientes.
 */
class UpdateCertificateStatusHandler
{
    public function handle(UpdateCertificateStatusCommand $command): JsonResponse
    {
        try {
            $certificate    = CertificateRequest::query()
                ->with(['company'])
                ->where('id', $command->certificateId)
                ->firstOrFail();

            $previousStatus = $certificate->request_status;

            DB::beginTransaction();

            $certificate->update([
                'request_status' => $command->requestStatus,
            ]);

            ChangeHistory::create([
                'certificate_request_id' => $certificate->id,
                'status'                 => $command->requestStatus,
                'comments'               => $command->comments,
                'user_id'                => $command->userId,
                'user_of_change'         => $command->userOfChange,
            ]);

            $this->sendStatusNotifications($certificate, $command);

            DB::commit();

            event(new CertificateStatusChanged(
                certificateRequestId: $certificate->id,
                companyId:            $certificate->company_id,
                previousStatus:       $previousStatus,
                newStatus:            $command->requestStatus,
                userId:               $command->userId,
                comment:              $command->comments,
            ));

            return HttpResponseMessages::getResponse([
                'message'     => 'El estado de la solicitud se ha actualizado exitosamente',
                'dataRecords' => $certificate->fresh(),
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return MessageExceptionResponse::response($e);
        }
    }

    private function sendStatusNotifications(CertificateRequest $certificate, UpdateCertificateStatusCommand $command): void
    {
        if ($command->userOfChange !== 'MANAGER') {
            return;
        }

        $isRejected  = $command->requestStatus === CertificateRequestStatusEnum::REJECTED->value;
        $isProcessed = $command->requestStatus === CertificateRequestStatusEnum::PROCESSED->value;

        if (!$isRejected && !$isProcessed) {
            return;
        }

        $company = $certificate->company;

        $comments = $isProcessed
            ? "<p style='font-size: 12px;'>La solicitud <b>({$certificate->uuid})</b> de certificado ha sido procesada exitosamente.</p>
               <p style='font-size: 12px;'>Puede proceder con la descarga del certificado desde la interfaz web de CERTIFICATE MANAGER</p>
               <p style='font-size: 12px'>Si tiene alguna pregunta o necesita más información, no dude en ponerse en contacto con nosotros.</p>"
            : $command->comments;

        $messageData = (object) [
            'company'        => $company,
            'data'           => $certificate,
            'comments'       => $comments,
            'request_status' => $command->requestStatus,
        ];

        Notification::route('mail', env('MAIL_SUPPORT_ADDRESS', 'soporte@matias.com.co'))
            ->notify(new CertificateRequestStatusNotification($messageData));

        Notification::route('mail', $company->email)
            ->notify(new CertificateRequestStatusNotification($messageData));
    }
}
