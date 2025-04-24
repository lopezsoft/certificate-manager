<?php

namespace App\Services;

use App\Jobs\MigratedJSonDataJob;
use App\Models\JsonData;
use App\Models\ShippingHistory;
use App\Modules\Resolutions\ResolutionQueries;
use Exception;
use Illuminate\Support\Str;

class ShippingService
{
    /**
     * Válida si el documento ya se encuentra validado en la tabla de envíos y retorna una excepción si ya se encuentra validado
     * de lo contrario retorna el documento si existe
     *
     *
     * @param $request
     * @return object|null
     * @throws Exception
     */
    public static function getShipping($request): ?object
    {
        $company                = $request->company;
        $type_document_id       = $request->type_document_id;
        $resolution             = $request->resolution;
        $documentNumber         = ResolutionQueries::getDocumentNumber($request, $resolution);
        $shopping   = ShippingHistory::query()
            ->where('document_number', $documentNumber)
            ->where('company_id', $company->id)
            ->where('type_document_id', $type_document_id)
            ->where('resolution_id', $resolution->id)
            ->first();
        if ($shopping && $shopping->is_valid === 1) {
            throw new Exception("El documento ({$resolution->type_document->voucher_name}) con numero {$documentNumber}, ya se encuentra validado", 400);
        }
        if($shopping && $shopping->send_to_queue === 1) {
            throw new Exception("El documento ({$resolution->type_document->voucher_name}) con numero {$documentNumber}, se encuentra en cola de procesamiento", 400);
        }
        return $shopping;
    }

    /**
     * @throws Exception
     */
    public static function save($shopping, $request, $response): object
    {
        try {
            $jsonData                       = (Object)json_decode($response->jsonData);
            // $isPayroll                      = in_array($request->type_document_id, [13, 14], true);
            $send_to_queue                  = $response->send_to_queue ?? 0;
            $shopping                       = $shopping ?? new ShippingHistory();
            $user                           = $request->user;
            $company                        = $request->company;
            $type_document_id               = $request->type_document_id;
            $resolution                     = $request->resolution;
            $operation_type_id              = $request->operation_type_id ?? 1;
            // Save shopping
            $isValid                        = false;
            if ($send_to_queue === 0) {
                $dianResponse               = DianResponseService::getResponse($response->dianResponse);
                $isValid                    = ($dianResponse->IsValid === "true");
                if (isset($dianResponse->XmlDocumentKey)) {
                    $response->XmlDocumentKey   = $dianResponse->XmlDocumentKey;
                }
                $errorMessage           = $dianResponse->ErrorMessage;
                if (isset($errorMessage->string) && is_string($errorMessage->string)) {
                    if (Str::contains($errorMessage->string, 'procesado anteriormente')){
                        $isValid = true;
                    }
                }
            }
            $shopping->user_id              = $user->id;
            $shopping->company_id           = $company->id;
            $shopping->type_document_id     = $type_document_id;
            $shopping->operation_type_id    = $operation_type_id;
            $shopping->resolution_id        = $resolution->id;
            $shopping->document_number      = $response->document_number;
            $shopping->XmlDocumentKey       = $response->XmlDocumentKey;
            $shopping->XmlDocumentName      = $response->XmlDocumentName;
            $shopping->jsonPath             = null;
            $shopping->xmlPath              = $response->xmlPath;
            $shopping->zipPath              = $response->zipPath;
            $shopping->order_number         = $response->order_number ?? null;
            $shopping->invoice_date         = $jsonData->invoiceDate ?? $jsonData->date ?? null;
            $shopping->payable_amount       = $jsonData->totalPayment ?? $jsonData->total_voucher ?? 0;
            $shopping->send_to_queue        = $response->send_to_queue ?? 0;
            if ($isValid) {
                $shopping->is_valid     = 1;
            }
            $shopping->save();
            // Actualizamos las lines y eliminamos algunas propiedades del objeto
            $lines = $jsonData->lines ?? [];
            $linesData = [];
            foreach ($lines as $line) {
                // Eliminamos la propiedad quantity_units del objeto line
                unset($line->quantity_units, $line->type_item_identifications);
                // Guardamos la información de la línea
                $linesData[] = $line;
            }
            $jsonData->lines    = $linesData;
            /*if (!$isPayroll) {
                MigratedJSonDataJob::migrateCustomer($shopping, $jsonData); TODO: Des comentar cuando se implemente la migración de clientes
            }*/

            $Data   = JsonData::query()->where('shipping_id', $shopping->id)->first();
            if (!$Data) {
                $Data = new JsonData();
                $Data->shipping_id  = $shopping->id;
            }
            $Data->jdata        = $jsonData;
            $Data->is_migrated  = 1;
            $Data->save();

            return $shopping;
        }catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}
