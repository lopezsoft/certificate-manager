<?php

namespace App\Modules\Documents\Invoice;

use App\Models\Invoice\BillingReference;
use Illuminate\Http\Request;

class DocumentReference
{

    public static  function getInvoicePeriod(Request $request): ?object
    {
        $invoicePeriod = null;
        if (isset($request->invoice_period)) {
            $invoicePeriod = $request->invoice_period;
            if (is_array($request->invoice_period)) {
                $invoicePeriod = array_merge($request->invoice_period, []);
            } else if (is_string($request->invoice_period)) {
                $invoicePeriod = json_decode($request->invoice_period, TRUE);
            }
            $invoicePeriod = (object) $invoicePeriod;
        }
        return $invoicePeriod;
    }
    public static function getBillingReference(Request $request): ?object
    {
        $billingReference = null;
        if (isset($request->billing_reference)) {
            $billingReference = $request->billing_reference;
            if (is_array($request->billing_reference)) {
                $billingReference = array_merge($request->billing_reference, []);
            } else if (is_string($request->billing_reference)) {
                $billingReference = json_decode($request->billing_reference, TRUE);
            }
            $reference = (object) $billingReference;
            $billingReference = new BillingReference($billingReference);
            if (isset($reference->scheme_name)) {
                $billingReference->scheme_name   = $reference->scheme_name;
            }
        }
        return $billingReference;
    }

    public static function getOrderReference(Request $request){
        $orderReference = null;
        if(isset($request->order_reference)){
            if (is_array($request->order_reference)) {
                $orderReference = (object) array_merge($request->order_reference, []);
            } else {
                $orderReference = json_decode($request->order_reference);
            }
        }
        return $orderReference;
    }
}
