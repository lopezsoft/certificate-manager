<?php

namespace App\Services\Test;

use App\Enums\DocumentTestStatusEnum;
use App\Models\Company;
use App\Models\Settings\Software;
use App\Models\Test\TestDocument;
use App\Models\Test\TestProcess;
use App\Modules\Documents\StatusZip;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TestProcessService
{
    public static function init($processId): void
    {
        $process    = TestProcess::find($processId);
        $software   = Software::query()->where('id', $process->software_id)->first();
        try {
            if (!$software) {
                throw new Exception('Software not found');
            }
            $company    = Company::query()->where('id', $software->company_id)->first();
            if (!$company) {
                throw new Exception('Company not found');
            }
            $process->status            = DocumentTestStatusEnum::getRunning();
            $process->error_message     = null;
            $process->save();
            $software->environment_id   = 2;
            $software->save();
            $software->refresh();
            self::sendDocuments($software, $company, $process, $processId);
        }catch (Exception $e) {
            $process->status        = DocumentTestStatusEnum::getError();
            $process->error_message = $e->getMessage();
            $process->save();
        }
    }

    /**
     * Reenvía los documentos electrónicos que no han sido enviados a la DIAN
     * @return void
     * @throws Exception
     */
    public static function resendDocuments(): void
    {
        $testProcesses  = TestProcess::query()
            ->whereIn('status', [DocumentTestStatusEnum::getCreated(), DocumentTestStatusEnum::getRunning()])
            ->where('created_at', '>=', now()->subMinutes(120))
            ->limit(100)
            ->get();
        foreach ($testProcesses as $process) {
            $software   = Software::query()->where('id', $process->software_id)->first();
            $company    = Company::query()->where('id', $software->company_id)->first();
            if($process->status == DocumentTestStatusEnum::getRunning()){
                self::sendDocuments($software, $company, $process, $process->id);
            } else {
                self::init($process->id);
            }
        }
    }
    /**
     * Valída si las pruebas fueron exitosas
     * @return void
     * @throws Exception
     */
    public static function validateDocuments(): void
    {
        $testProcesses  = TestProcess::query()
            ->whereIn('status', [DocumentTestStatusEnum::getSending(), DocumentTestStatusEnum::getValidating()])
            ->where('created_at', '>=', now()->subMinutes(120))
            ->limit(100)
            ->get();
        foreach ($testProcesses as $process) {
            $testDocuments   = TestDocument::query()
                ->where('process_id', $process->id)->get();

            $software       = Software::query()->where('id', $process->software_id)->first();
            $company        = Company::query()->where('id', $software->company_id)->first();
            $process->status= 'VALIDATING';
            $process->save();
            $isValid = false;
            foreach ($testDocuments as $testDocument) {
                $dianResponse= (new StatusZip)->dianResponse($testDocument->zipkey, $company);
                $response    = $dianResponse->Envelope->Body;
                $dianResponse= $response->GetStatusZipResponse->GetStatusZipResult->DianResponse;
                $isValid     = ($dianResponse->IsValid == 'true');
                if(!$isValid){
                    $msg     = "Set de prueba con identificador {$software->testsetid} se encuentra Aceptado.";
                    $isValid = ($dianResponse->StatusDescription == $msg);
                }
                if (!$isValid) {
                    $process->status        = 'ERROR';
                    $process->error_message = $dianResponse->ErrorMessage;
                    $process->StatusDescription = $dianResponse->StatusDescription;
                    $process->save();
                    break;
                }
            }
            if ($isValid) {
                $process->status = 'FINISHED';
                $process->error_message = [
                    'string'    => 'El proceso de habilitación ha sido finalizado con éxito.'
                ];
                $process->save();
                $software->environment_id = 1;
                $software->testsetid    = '';
                $software->save();
            }
        }
    }

    /**
     *  Envía los documentos electrónicos a la DIAN
     * @throws Exception
     */
    private static function sendDocuments($software, $company, $process, $processId): void
    {
        $eachData   = match ($software->type_id) {
            1, 4 => collect([1]), // Facturación
            2 => collect([1, 2, 3, 4]), // Nómina
            default => throw new Exception('Software type not found'),
        };
        $contentData    = self::getContent($software);
        $request        = new Request();
        $request->merge($contentData);
        $request->merge([
            'company'           => $company,
            'user'              => $process->user,
            'software'          => $software,
            'processId'         => $processId,
        ]);
        $initialNumber  = $software->initial_number - 1 ?? 1;
        foreach ($eachData as $data) {
            $initialNumber++;
            $request->merge([
                'count_data'        => $data,
                'initial_number'    => $initialNumber,
            ]);
            switch ($software->type_id) {
                case 1:
                    TestInvoiceService::process($request);
                    break;
                case 2:
                    $request->type_document_id = 13;
                    (new TestPayrollService())->process($request);
                    break;
                case 4:
                    $request->type_document_id = 20;
                    TestPosService::process($request);
                    break;
            }
        }
        $process->status            = DocumentTestStatusEnum::getSending();
        $process->save();
    }

    /**
     * @throws Exception
     */
    private static function getContent($software)
    {
        $fileName       = null;
        switch ($software->type_id) {
            case 1:
                $fileName = 'invoice.json';
                break;
            case 2:
                $fileName = 'payroll.json';
                break;
            case 4 :
                $fileName = 'pos.json';
                break;
        }
        if (!file_exists(storage_path("app/test/{$fileName}"))) {
            throw new Exception('File not found in storage');
        }
        $fileContent = Storage::disk('local')->get("test/{$fileName}");
        if (!$fileContent) {
            throw new Exception('File content not found');
        }
        return json_decode($fileContent, true);
    }
}
