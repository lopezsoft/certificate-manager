<?php

namespace App\Modules\Documents\Payroll;

use Illuminate\Http\Request;

class PayrollDeletingPredecessor
{
    public static function get(Request $request) {
        $deleting_predecessor = $request->deleting_predecessor ?? null;
        if($deleting_predecessor) {
            if(is_array($deleting_predecessor)){
                $deleting_predecessor = (object) array_merge($deleting_predecessor, []);
            }elseif (is_string($deleting_predecessor)) {
                $deleting_predecessor = json_decode($deleting_predecessor);
            }
        }
        return $deleting_predecessor;
    }

}
