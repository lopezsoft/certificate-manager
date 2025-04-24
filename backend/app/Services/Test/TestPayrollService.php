<?php

namespace App\Services\Test;

use App\Models\Test\TestDocument;
use App\Modules\Documents\Payroll\PayrollProcessor;
use App\Traits\ElectronicDocumentsTrait;
use Carbon\Carbon;
use Exception;
class TestPayrollService
{
    use ElectronicDocumentsTrait;
    public function process($request): void
    {
        $processId  = $request->processId;
        try {
            $software   = $request->software;
            // Obtener la fecha actual
            $hoy = Carbon::now();
            // Obtener el primer día del mes (sin hora)
            $primerDiaDelMes    = $hoy->copy()->startOfMonth()->toDateString();
            // Obtener el último día del mes (sin hora)
            $ultimoDiaDelMes    = $hoy->copy()->endOfMonth()->toDateString();
            $generationDate     = $hoy->toDateString();
            $request->document_number   = $request->initial_number;
            $request->pay_day           = $ultimoDiaDelMes;
            // period
            $request->period                        = (object) $request->period;
            $request->period->settlement_start_date = $primerDiaDelMes;
            $request->period->settlement_end_date   = $ultimoDiaDelMes;
            $request->period->generation_date       = $generationDate;
            // general_information
            $request->general_information                   = (object) $request->general_information;
            $request->general_information->generation_date  = $generationDate;
            // company
            $company                    = $request->company;
            $user                       = $request->user;
            $user->name                 = $company->company_name;
            $user->company              = $company;
            $type_document_id           = $request->type_document_id ?? 13;
            $prefix                     = $type_document_id == 13 ? 'SETPN' : 'SETPNR';
            $initialNumber              = $software->initial_number;
            $resolution                 = TestResolution::get($type_document_id, $company->id, $prefix, $initialNumber, $initialNumber + 1000);
            $request->resolution        = $resolution;
            $request->company           = $company;
            $request->user              = $user;
            $request->type_document_id  = $type_document_id;
            $response                   = (new PayrollProcessor())->process($request);
            TestDocument::create([
                'process_id'        => $processId,
                'user_id'           => $user->id,
                'software_id'       => $software->id,
                'document_number'   => $response->document_number,
                'zipkey'            => $response->ZipKey,
                'XmlDocumentKey'    => $response->XmlDocumentKey,
                'type_document_id'  => $type_document_id,
            ]);
            if ($request->type_document_id == 13) {
                $request->type_document_id  = 14;
                $request->replacing_predecessor = (Object) [
                    'number'            => $response->document_number,
                    'cune'              => $response->XmlDocumentKey,
                    'generation_date'   => $generationDate,
                ];
                $this->process($request);
            }
        }catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}
