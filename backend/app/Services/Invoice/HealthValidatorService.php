<?php

namespace App\Services\Invoice;

use App\Common\DateFunctions;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HealthValidatorService
{
    /**
     * @throws Exception
     */
    public static function validator(Request $request): object
    {
        $validator = Validator::make($request->all(), [
            'health.operation_type' => 'required|string',
            'health.invoice_period.start_date' => 'required|date_format:Y-m-d',
            'health.invoice_period.start_time' => 'required|date_format:H:i:s',
            'health.invoice_period.end_date' => 'required|date_format:Y-m-d',
            'health.invoice_period.end_time' => 'required|date_format:H:i:s',
            'health.download_attachments.url' => 'nullable|string',
            'health.download_attachments.arguments.*.name' => 'nullable|string',
            'health.download_attachments.arguments.*.value' => 'nullable|string',
            'health.document_delivery.ws' => 'nullable|url',
            'health.document_delivery.arguments.*.name' => 'nullable|string',
            'health.document_delivery.arguments.*.value' => 'nullable|string',
            'health.user_collections.*.information.*.name' => 'required|string',
            'health.user_collections.*.information.*.value' => 'required|string',
            'health.user_collections.*.information.*.schemeName' => 'sometimes|required|string',
            'health.user_collections.*.information.*.schemeID' => 'sometimes|required|string',
        ]);

        if ($validator->fails()) {
            throw new Exception($validator->errors());
        }
        $health                 = (Object) $request->input('health');
        $invoicePeriod          = (Object) $health->invoice_period;
        $invoicePeriod->start_date  = DateFunctions::transformDate($invoicePeriod->start_date);
        $invoicePeriod->start_time  = DateFunctions::transformTime($invoicePeriod->start_time);
        $invoicePeriod->end_date    = DateFunctions::transformDate($invoicePeriod->end_date);
        $invoicePeriod->end_time    = DateFunctions::transformTime($invoicePeriod->end_time);
        $health->invoice_period     = $invoicePeriod;
        $health->download_attachments   = (Object) $health->download_attachments ?? null;
        $health->document_delivery      = (Object) $health->document_delivery ?? null;

        return $health;
    }
}
