<?php

namespace App\Modules\Documents\Payroll;

use App\Models\Ep\PayrollPeriod;
use App\Models\Types\TypeCurrency;
use Illuminate\Http\Request;

class PayrollGeneralInformation
{
    static function get(Request $request) {
        $information    = $request->general_information ?? null;

        if($information){
            if(is_array($information)){
                $information    = (object) array_merge($information, []);
            }elseif (is_string($information)) {
                $information    = json_decode($information);
            }
        }
        if(!$information) {
            $information    = (object) [];
        }
        $information->generation_date   = $information->generation_date ?? date('Y-m-d');
        $information->generation_time   = $information->generation_time ?? date('H:i:s');
        $information->period            = PayrollPeriod::findOrFail($information->period_id ?? 5);
        $information->currency          = TypeCurrency::findOrFail($information->currenc_id ?? 272);
        $information->trm               = $information->trm ?? 0;

        return $information;
    }
}
