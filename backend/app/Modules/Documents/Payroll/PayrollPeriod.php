<?php

namespace App\Modules\Documents\Payroll;

use Illuminate\Http\Request;

class PayrollPeriod
{
    public static function get(Request $request) {
        $period = $request->period ?? null;
        if($period) {
            if(is_array($period)){
                $period = (object) array_merge($period, []);
            }elseif(is_string($period)) {
                $period = json_decode($period);
            }
        }
        return $period;
    }
}
