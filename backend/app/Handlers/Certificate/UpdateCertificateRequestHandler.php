<?php

namespace App\Handlers\Certificate;

use App\Commands\Certificate\UpdateCertificateRequestCommand;
use App\Common\HttpResponseMessages;
use App\Common\MessageExceptionResponse;
use App\Common\VerificationDigit;
use App\Models\CertificateRequest;
use App\Models\ChangeHistory;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Handler para actualizar los datos de una solicitud de certificado.
 *
 * Aplica Command Pattern: recibe UpdateCertificateRequestCommand y
 * encapsula toda la lógica de actualización + registro de historial.
 */
class UpdateCertificateRequestHandler
{
    public function handle(UpdateCertificateRequestCommand $command): JsonResponse
    {
        try {
            $certificate = CertificateRequest::query()
                ->where('company_id', $command->companyId)
                ->where('id', $command->certificateId)
                ->firstOrFail();

            $dv = VerificationDigit::getDigit($command->dni);

            $certificate->update([
                'city_id'               => $command->cityId,
                'identity_document_id'  => $command->identityDocumentId,
                'type_organization_id'  => $command->typeOrganizationId,
                'document_number'       => strip_tags($command->documentNumber),
                'address'               => strip_tags($command->address),
                'legal_representative'  => Str::upper(strip_tags($command->legalRepresentative)),
                'company_name'          => Str::upper(strip_tags($command->companyName)),
                'dni'                   => strip_tags($command->dni),
                'dv'                    => $dv,
                'info'                  => strip_tags($command->info ?? ''),
                'life'                  => $command->life,
                'postal_code'           => $command->postalCode,
                'phone'                 => $command->phone,
                'mobile'                => $command->mobile,
            ]);

            // Registrar historial de cambios para trazabilidad
            ChangeHistory::create([
                'certificate_request_id' => $certificate->id,
                'status'                 => $certificate->request_status,
                'comments'               => 'Datos de la solicitud actualizados',
                'user_of_change'         => 'USER',
                'user_id'                => auth()->id(),
            ]);

            return HttpResponseMessages::getResponse([
                'message'     => 'Solicitud de certificado actualizada exitosamente',
                'dataRecords' => $certificate->fresh(),
            ]);
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }
}

