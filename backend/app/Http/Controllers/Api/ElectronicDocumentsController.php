<?php

namespace App\Http\Controllers\Api;

use App\Common\HttpResponseMessages;
use App\Common\MessageExceptionResponse;
use App\Enums\DocumentTestStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\InvoiceRequest;
use App\Jobs\Test\TestRunJob;
use App\Models\Settings\Software;
use App\Models\Test\TestProcess;
use App\Modules\Documents\Invoice\ElectronicInvoice;
use App\Modules\Documents\Payroll\IndividualPayroll;
use App\Modules\Documents\Payroll\IndividualPayrollDelete;
use App\Modules\Documents\RunElectronicDocumentProcessor;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ElectronicDocumentsController extends Controller
{
    public function runTest(Request $request): JsonResponse
    {
        try {
            $software   = Software::query()->where('id', $request->software_id)->first();
            $testProcess= $software->testProcess;
            if ($testProcess) {
                if(!in_array($testProcess->status, [DocumentTestStatusEnum::getError(), DocumentTestStatusEnum::getFinished()], true)) {
                    throw new Exception('Ya existe un proceso en ejecución para este software. Por favor espere a que finalice.');
                }
            }
            $process                = new TestProcess();
            $process->user_id       = $request->user()->id;
            $process->software_id   = $request->software_id;
            $process->save();
            TestRunJob::dispatch($process->id);
            return HttpResponseMessages::getResponse([
                'message'       => 'Se ha iniciado el proceso de habilitación de documentos electrónicos.',
                'process_id'    => $process->id,
            ]);
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }
    public function invoice(InvoiceRequest $request) {
        return RunElectronicDocumentProcessor::process($request, new ElectronicInvoice());
    }
    public function note(InvoiceRequest $request) {
        return RunElectronicDocumentProcessor::process($request, new ElectronicInvoice());
    }
    public function documentSupport(InvoiceRequest $request) {
        return RunElectronicDocumentProcessor::process($request, new ElectronicInvoice());
    }
    public function adjustmentNote(InvoiceRequest $request) {
        $request->type_id   = 3;
        return RunElectronicDocumentProcessor::process($request, new ElectronicInvoice());
    }
    public function payroll(Request $request) {
        $request->type_document_id = 13;
        return RunElectronicDocumentProcessor::process($request, new IndividualPayroll());
    }
    public function payrollReplace(Request $request) {
        $request->type_document_id = 14;
        return RunElectronicDocumentProcessor::process($request, new IndividualPayroll());
    }
    public function payrollDelete(Request $request) {
        $request->type_document_id = 14;
        return RunElectronicDocumentProcessor::process($request, new IndividualPayrollDelete());
    }
}
