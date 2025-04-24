<?php

namespace App\Modules\Documents\Payroll;

use Illuminate\Http\Request;

class PayrollReplacingPredecessor
{
    public static function get(Request $request) {
        $replacing_predecessor = $request->replacing_predecessor ?? null;
        if($replacing_predecessor) {
            if(is_array($replacing_predecessor)){
                $replacing_predecessor = (object) array_merge($replacing_predecessor, []);
            }elseif(is_string($replacing_predecessor)) {
                $replacing_predecessor = json_decode($replacing_predecessor);
            }
        }

        return $replacing_predecessor;
    }
}
