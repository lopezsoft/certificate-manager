<?php

namespace App\Handlers\Certificate;

use App\Commands\Certificate\DeleteCertificateRequestCommand;
use App\Common\HttpResponseMessages;
use App\Common\MessageExceptionResponse;
use App\Events\CertificateRequestDeleted;
use App\Models\CertificateRequest;
use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Handler para eliminar una solicitud de certificado.
 *
 * Aplica Command Pattern: recibe DeleteCertificateRequestCommand y
 * encapsula toda la lógica de eliminación + disparo de evento.
 */
class DeleteCertificateRequestHandler
{
    public function handle(DeleteCertificateRequestCommand $command): JsonResponse
    {
        try {
            $certificate = CertificateRequest::query()
                ->where('company_id', $command->companyId)
                ->where('id', $command->certificateId)
                ->firstOrFail();

            $deletedId      = $certificate->id;
            $deletedCompany = $certificate->company_id;
            $deletedDni     = $certificate->dni;
            $deletedName    = $certificate->company_name;

            $certificate->delete();

            event(new CertificateRequestDeleted($deletedId, $deletedCompany, $deletedDni, $deletedName));

            return HttpResponseMessages::getResponse([
                'message' => 'Solicitud de certificado eliminada exitosamente',
            ]);
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }
}
