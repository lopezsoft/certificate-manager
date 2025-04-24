<?php

namespace App\Modules\Documents;

use App\Interfaces\ElectronicDocumentProcessor;
use Illuminate\Http\Request;

class RunElectronicDocumentProcessor
{
    public static function process(Request $request, ElectronicDocumentProcessor $document) {
        return $document->process($request);
    }
}
