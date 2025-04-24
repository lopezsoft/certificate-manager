<?php

namespace App\Modules\Documents\Invoice;

use App\Models\Invoice\AllowanceCharge;
use Illuminate\Http\Request;

class Charges
{
    public static function getCharges(Request $request): \Illuminate\Support\Collection
    {
        $allowanceCharges   = collect();
        if (is_array($request->allowance_charges)) {
            $charges             = $request->allowance_charges;
        } else if (is_string($request->allowance_charges)) {
            $charges             = json_decode($request->allowance_charges, true);
        }
        foreach ($charges ?? [] as $allowanceCharge) {
            $allowanceCharges->push(new AllowanceCharge($allowanceCharge));
        }

        return $allowanceCharges;
    }
}
