<?php

namespace App\Handlers\Certificate;

use App\Commands\Certificate\DeleteCertificateRequestCommand;
use App\Common\HttpResponseMessages;
use App\Common\MessageExceptionResponse;
use App\Events\CertificateRequestDeleted;
use App\Models\CertificateRequest;
use App\Services\QuotaService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Handler para eliminar una solicitud de certificado.
 *
 * Aplica Command Pattern: recibe DeleteCertificateRequestCommand y
 * encapsula toda la lógica de eliminación + liberación de cuota + disparo de evento.
 */
class DeleteCertificateRequestHandler
{
    public function __construct(
        private readonly QuotaService $quotaService,
    ) {}

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

            DB::transaction(function () use ($certificate, $deletedCompany) {
                $certificateId = $certificate->id;

                // Limpiar vinculación del item con el certificado
                DB::table('certificate_order_items')
                    ->where('certificate_request_id', $certificateId)
                    ->update(['certificate_request_id' => null]);

                $certificate->delete();

                // Liberar el cupo que se consumió al crear la solicitud
                $this->quotaService->releaseQuotaForRequest($deletedCompany);
            });

            event(new CertificateRequestDeleted($deletedId, $deletedCompany, $deletedDni, $deletedName));

            return HttpResponseMessages::getResponse([
                'message' => 'Solicitud de certificado eliminada exitosamente',
            ]);
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }
}
