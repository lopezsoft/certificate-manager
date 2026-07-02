<?php

namespace App\Handlers\Certificate;

use App\Commands\Certificate\DeleteCertificateRequestCommand;
use App\Common\HttpResponseMessages;
use App\Common\MessageExceptionResponse;
use App\Events\CertificateRequestDeleted;
use App\Models\CertificateRequest;
use App\Models\FileManager;
use App\Services\QuotaService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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

                // 1. Obtener todos los archivos asociados a la solicitud
                $files = FileManager::where('certificate_request_id', $certificateId)->get();

                // 2. Eliminar archivos del storage PRIMERO (AWS S3 o disco local)
                $disk = Storage::disk(config('certificate.storage.disk', 'local'));
                foreach ($files as $file) {
                    if ($disk->exists($file->file_path)) {
                        $disk->delete($file->file_path);
                    }
                }

                // 3. Limpiar vinculación del item con el certificado
                DB::table('certificate_order_items')
                    ->where('certificate_request_id', $certificateId)
                    ->update(['certificate_request_id' => null]);

                // 4. Eliminar registros de file_managers
                FileManager::where('certificate_request_id', $certificateId)->delete();

                // 5. Eliminar la solicitud
                $certificate->delete();

                // 6. Liberar el cupo que se consumió al crear la solicitud
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
