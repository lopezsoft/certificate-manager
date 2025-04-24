<?php

namespace App\Modules\Documents\Payroll;

use App\Traits\DocumentTrait;
use Illuminate\Http\Request;

class PayrollSequenceNumber
{
    use DocumentTrait;
    public static function get(Request $request, $resolution): object
    {
        $document_number    = $request->document_number;
        $worker_code        = $request->worker_code   ?? null;

        $document_number    = self::gstuffedString($document_number, strlen(strval($resolution->range_up)));

        return (object) [
            'worker_code'       => $worker_code,
            'prefix'            => $resolution->prefix,
            'consecutive'       => $document_number,
            'number'            => $resolution->prefix.$document_number,
        ];
    }

}
