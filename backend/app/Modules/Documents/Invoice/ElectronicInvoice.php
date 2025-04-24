<?php

namespace App\Modules\Documents\Invoice;

use App\Common\HttpResponseMessages;
use App\Common\MessageExceptionResponse;
use App\Interfaces\ElectronicDocumentProcessor;
use App\Modules\Company\CompanyQueries;
use App\Modules\Documents\StatusContent;
use App\Modules\Documents\TypeDocumentIdSoftware;
use App\Modules\Resolutions\ResolutionQueries;
use App\Services\DianResponseService;
use App\Services\FileSystem\FileSystemService;
use App\Services\FileSystem\UploadXmlFileToS3Service;
use App\Services\ShippingService;
use App\Services\Certificate\CertificateValidatorService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ElectronicInvoice implements ElectronicDocumentProcessor
{
    public function process(Request $request): JsonResponse
    {
        try {
            $user                   = Auth::user();
            $company                = CompanyQueries::getCompany();
            CertificateValidatorService::validateCertificate($company->dni);
            $user->company          = $company;
            $request->language_id   = 842;
            $request->async         = false;
            $request->payment_value = 0;
            $request->type_id       = TypeDocumentIdSoftware::getId($request->type_document_id ?? 7);
            $software               = CompanyQueries::getSoftware($request, $company);
            $send_to_queue          = $request->send_to_queue ?? 0; // 1: Para enviar el documento a procesar en la cola
            if ($send_to_queue) {
                throw new Exception('El envío a cola de procesamiento aún no está habilitado.', 400); // TODO: Habilitar envío a cola
            }
            if (!$software) {
                throw new Exception('No se encontró una configuración de software asociada al tipo de documento.', 404);
            }
            $request->software      = $software;
            $type_document_id       = $request->type_document_id;
            $resolution_number      = $request->resolution_number;
            $prefix                 = $request->prefix ?? null;
            $resolutionParams       = (object)[
                'company'           => $company,
                'type_document_id'  => $type_document_id,
                'resolution_number' => $resolution_number,
                'prefix'            => $prefix,
            ];
            // Resolution
            $resolution             = ResolutionQueries::getResolution($request, $resolutionParams);
            $request->resolution    = $resolution;
            $request->user          = $user;
            $request->company       = $company;
            $shipping               = ShippingService::getShipping($request);
            $response               = (new ElectronicDocumentoProcessor())->process($request);
            if ($software->environment->code === '2') { // Test
                return HttpResponseMessages::getResponse([
                    'XmlDocumentKey'    => $response->XmlDocumentKey,
                    'ZipKey'            => $response->ZipKey,
                    'response'          => DianResponseService::getResponse($response->dianResponse),
                    'XmlDocumentName'   => $response->XmlDocumentName,
                    'ShippingMethod'    => $response->ShippingMethod,
                    'XmlBase64Bytes'    => $response->XmlBase64,
                ]);
            }
            // Guarda la información del documento
            $shipping       = ShippingService::save($shipping, $request, $response);
            // Genera la representación gráfica, envía el correo
            $content        = StatusContent::getContent($request, $response, $shipping);
            $shipping->refresh();
            if ($shipping->is_valid === 1 && $send_to_queue === 0) {
                $prefix = $resolution->type_document->prefix;
                $params = (object) [
                    'localPath' => $shipping->xmlPath,
                    'company'   => $company,
                    'fileName'  => $prefix . $response->document_number,
                    'shipping'  => $shipping,
                ];
                FileSystemService::uploadToS3(new UploadXmlFileToS3Service(), $params);
            }
            // Production
            return $content;
        } catch (Exception $e) {
            DB::rollBack();
            return MessageExceptionResponse::response($e);
        }
    }
}
