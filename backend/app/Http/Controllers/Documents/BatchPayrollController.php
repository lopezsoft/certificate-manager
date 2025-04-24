<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Modules\Documents\Payroll\Ep\BatchPayroll;
use Illuminate\Http\Request;

class BatchPayrollController extends Controller
{
    function processbatch(Request $request) {
        return (new BatchPayroll)->processbatch($request);
    }
    function read(Request $request) {
        return BatchPayroll::read($request);
    }

    function import(Request $request) {
        return BatchPayroll::import($request);
    }

    function download() {
        return BatchPayroll::download();
    }
}
