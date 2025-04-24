<?php

namespace App\Modules\Documents;

use App\Common\HttpResponseMessages;
use App\Common\MessageExceptionResponse;
use App\Interfaces\DocumentsInterface;
use App\Jobs\Emails\EmailSendingDocumentJob;
use App\Models\Email\DocumentEmailJob;
use App\Models\JsonData;
use App\Models\ShippingHistory;
use App\Modules\Company\CompanyQueries;
use App\Services\Certificate\CertificateValidatorService;
use App\Services\Mail\EmailSuppressedNotificationService;
use App\Traits\DocumentTrait;
use App\Traits\ElectronicDocumentsTrait;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SendingEmail implements DocumentsInterface
{
    use HasFactory, DocumentTrait, ElectronicDocumentsTrait;
    public function process(Request $request, $trackId): \Illuminate\Http\JsonResponse
    {
        try {
            $company        = CompanyQueries::getCompany();
            CertificateValidatorService::validateCertificate($company->dni);
            $emailTo        = $request->input('email_to');
            if(is_null($emailTo) || !(strlen($emailTo) > 10)) {
                throw new Exception('Debe indicar, al menos un correo, a donde desea enviar el documento.', 400);
            }
            $mail 	= substr_count($emailTo, ' ');
            if ($mail > 0) {
                throw new Exception("Hay espacios en blanco en la lista de correos electrónicos.");
            }

            EmailSuppressedNotificationService::checkSuppressed($emailTo, $company, 'Envío de documento electrónico');

            $shipping   = ShippingHistory::where('XmlDocumentKey', $trackId)
                ->where('company_id', $company->id)
                ->first();
            if (!$shipping) {
                throw new Exception('El trackId del documento no existe en la base de datos.', 404);
            }

            $jsonData       = JsonData::query()->where('shipping_id', $shipping->id)->first();
            if (!$jsonData) {
                throw new Exception('No se encontró el documento para el envío de correo.', 404);
            }
            $jsonData       = (object) $jsonData->jdata;
            if (!isset($jsonData->dni) || isFinalConsumer($jsonData->dni)) {
                $customer = (object) $jsonData->customer;
                if (isset($customer->company)){
                    $companyCustomer = (object) $customer->company;
                    if (isset($companyCustomer->dni) && !isFinalConsumer($companyCustomer->dni)){
                        $jData = JsonData::query()->where('shipping_id', $shipping->id)->first();

                        $uData = (object) $jData->jdata;
                        $uData->dni = $companyCustomer->dni;

                        $jData->jdata = $uData;
                        $jData->save();
                    } else {
                        throw new Exception('No se puede enviar un correo electrónico a consumidor final.', 400);
                    }
                } else {
                    throw new Exception('No se puede enviar un correo electrónico a consumidor final.', 400);
                }
            }
            $request->type_id   = TypeDocumentIdSoftware::getId($shipping->type_document_id ?? 7);
            if ($request->type_id == 2) {
                throw new Exception('El documento nómina electrónica no está soportado para el envío de email.', 400);
            }
            // Se guarda el registro en la tabla de envío de correos electrónicos
            $documentEmailJob = DocumentEmailJob::create([
                'company_id'        => $company->id,
                'document_id'       => $shipping->id,
                'type_document_id'  => $shipping->type_document_id,
                'email_to'          => $emailTo,
                'created_at'        => now(),
            ]);
            EmailSendingDocumentJob::dispatch($documentEmailJob);
            return HttpResponseMessages::getResponse([
                'message'   => 'Se ha iniciado el proceso de envío del correo electrónico.'
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return MessageExceptionResponse::response($e);
        }
    }

}
