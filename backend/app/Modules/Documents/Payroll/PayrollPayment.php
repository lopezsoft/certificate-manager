<?php

namespace App\Modules\Documents\Payroll;

use App\Models\Invoice\PaymentMethod;
use App\Models\Invoice\MeansPayment;
use App\Traits\DocumentTrait;
use Illuminate\Http\Request;

class PayrollPayment
{
    use DocumentTrait;
    public static function get(Request $request) {
        $payment = $request->payment ?? null;
        if ($payment) {
            if (is_array($payment)) {
                $paymentFormAll = (object) array_merge($payment, []);
            } else {
                $paymentFormAll = json_decode($payment);
            }
        } else {
            // Payment form default
            $paymentFormAll = (object) array_merge([
                'payment_method_id'     => 1,
                'means_payment_id'      => 10,
                'currency_id'           => 272
            ], []);
        }

        $paymentForm = PaymentMethod::findOrFail($paymentFormAll->payment_method_id);
        $meanPayment = MeansPayment::findOrFail($paymentFormAll->means_payment_id);

        $paymentForm->means_payment         = $meanPayment;
        $paymentForm->payment_method_code   = $meanPayment->code;
        $paymentForm->bank                  = $paymentFormAll->bank ?? null;
        $paymentForm->account_type          = $paymentFormAll->account_type ?? null;
        $paymentForm->account_number        = $paymentFormAll->account_number ?? null;

        return  $paymentForm;
    }
}
