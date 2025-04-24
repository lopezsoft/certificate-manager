<?php

namespace App\Services\Test;

use App\Models\Test\TestDocument;
use App\Modules\Documents\Invoice\ElectronicDocumentoProcessor;
use Carbon\Carbon;
use Exception;

class TestPosService
{
    /**
     * @throws Exception
     */
    public static function process($request): void
    {
        $processId  = $request->processId;
        try {
            $software                   = $request->software;
            // Obtener la fecha actual
            $hoy                        = Carbon::now();
            $request->date              = $hoy->toDateString();
            $request->expiration_date   = $hoy->addDays(30)->toDateString();
            $request->time              = $hoy->toTimeString();
            // company
            $company                    = $request->company;
            $user                       = $request->user;
            $user->company              = $company;
            $request->language_id       = 842;
            $request->operation_type_id = 1;
            $request->notes             = 'Software en modo de pruebas';
            $request->tax_retentions    = [];
            $request->payment_value     = 0;
            $request->currency_id       = 272;
            // Method async
            $request->async             = false;
            $type_document_id           = 20;
            $prefix                     = 'EPOS';
            $rangeUp                    = 1000000;
            $initialNumber              = $software->initial_number;
            $request->document_number   = $initialNumber;
            $resolution                 = TestResolution::get($type_document_id, $company->id, $prefix, 1, $rangeUp);
            $resolution->date_from      = "2019-01-19";
            $resolution->date_up        = "2030-01-19";
            $request->resolution        = $resolution;
            $request->company           = $company;
            $request->user              = $user;
            $response                   = (new ElectronicDocumentoProcessor())->process($request);
            TestDocument::create([
                'process_id'        => $processId,
                'document_number'   => $response->document_number,
                'zipkey'            => $response->ZipKey,
                'XmlDocumentKey'    => $response->XmlDocumentKey,
                'type_document_id'  => $type_document_id,
            ]);
        }catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}
