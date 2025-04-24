<?php

namespace App\Modules\Documents;

use App\Interfaces\StatusProcessor;
use Illuminate\Http\Request;

class RunStatusProcessor
{
    public  static function execute(Request $request, $trackId, StatusProcessor $stateProcessor) {
        return $stateProcessor->process($request, $trackId);
    }
}
