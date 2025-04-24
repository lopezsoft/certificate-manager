<?php

namespace App\Modules\Documents\Payroll;

use App\Common\HttpResponseMessages;
use App\Common\MessageExceptionResponse;
use App\Interfaces\ElectronicDocumentProcessor;
use App\Modules\Company\CompanyQueries;
use App\Modules\Documents\StatusContent;
use App\Modules\Resolutions\ResolutionQueries;
use App\Services\FileSystem\FileSystemService;
use App\Services\FileSystem\UploadXmlFileToS3Service;
use App\Services\ShippingService;
use App\Traits\DocumentTrait;
use App\Traits\ElectronicDocumentsTrait;
use App\Traits\MessagesTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IndividualPayrollDelete implements ElectronicDocumentProcessor
{
    use DocumentTrait, ElectronicDocumentsTrait, MessagesTrait;
    public function process(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            // User
            $user               = auth()->user();
            $company            = CompanyQueries::getCompany();
            $type_document_id   = $request->type_document_id    ?? 14;
            $resolution_number  = $request->resolution_number   ?? null;
            // User company
            $user->company      = $company;
            $request->type_id   = 2;
            $software           = CompanyQueries::getSoftware($request, $company);
            // Resolution
            $prefix                 = $request->prefix ?? null;
            $resolutionParams       = (Object)[
                'company'           => $company,
                'type_document_id'  => $type_document_id,
                'resolution_number' => $resolution_number,
                'prefix'            => $prefix,
            ];
            $resolution         = ResolutionQueries::getResolution($request, $resolutionParams);
            $request->resolution= $resolution;
            $request->user      = $user;
            $request->company   = $company;
            $request->software  = $software;
            $request->type_document_id  = $type_document_id;
            $shipping           = null;
            if ($software->environment->code == '1'){ // Production
                $shipping           = ShippingService::getShipping($request);
            }
            $response               = (new PayrollDeleteProcessor())->process($request);
            if ($software->environment->code == '2') { // Test
                return HttpResponseMessages::getResponse($response);
            }
            $shipping       = ShippingService::save($shipping, $request, $response);
            $content        = StatusContent::getContent($request, $response, $shipping);
            $shipping->refresh();
            if ($shipping->is_valid == 1) {
                $prefix = $resolution->type_document->prefix;
                $params = (object) [
                    'localPath' => $shipping->xmlPath,
                    'company'   => $company,
                    'fileName'  => $prefix.$response->document_number,
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
