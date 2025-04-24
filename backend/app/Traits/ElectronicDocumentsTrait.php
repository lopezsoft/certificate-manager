<?php

namespace App\Traits;

use App\Models\Invoice\DiscrepancyResponse;
use App\Models\Invoice\PaymentMethod;
use App\Models\Invoice\MeansPayment;
use App\Modules\Resolutions\ResolutionQueries;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Electronic Documents
 */
trait ElectronicDocumentsTrait
{
    use DocumentTrait;

    public function getDocumentNumber(Request $request, $resolution): string
    {
        return ResolutionQueries::getDocumentNumber($request, $resolution);
    }


    public function getDate(Request $request): string
    {
        return ($request->date) ? date('Y-m-d', strtotime(str_replace('/', '-', $request->date))) : date('Y-m-d');
    }

    public function getExpirationDate(Request $request): string
    {
        return ($request->expiration_date) ? date('Y-m-d', strtotime(str_replace('/', '-', $request->expiration_date))) : date('Y-m-d');
    }

    public function getDueDate(Request $request): string
    {
        return ($request->due_date) ? date('Y-m-d', strtotime(str_replace('/', '-', $request->due_date))) : date('Y-m-d');
    }

    public function getTime(Request $request): string
    {
        return ($request->time) ? date('H:i:s', strtotime(str_replace('/', '-', $request->time))) : date("H:i:s");
    }

    public function getPayment($request): array
    {
        $currency_id    = $request->currency_id ?? 272;
        // Payment form default
        $paymentFormAll = $this->paymentFormDefault;
        if ($request->payments) {
            if (is_array($request->payments)) {
                $paymentFormAll = $request->payments;
            } elseif (is_string($request->payments)) {
                $paymentFormAll = json_decode($request->payments, true);
            }
        }
        $paymentData = [];
        $countId = 0;
        foreach ($paymentFormAll as $payment) {
            $countId++;
            if(is_array($payment))
                $payment    = (object) array_merge($payment, []
            );
            $paymentData[] = (object) [
                'id'                => $countId,
                'paymentMethod'     => PaymentMethod::findOrFail($payment->payment_method_id),
                'meansPayment'      => MeansPayment::findOrFail($payment->means_payment_id),
                'payment_due_date'  => $payment->payment_due_date ?? null,
                'currency_id'       => $payment->currency_id ?? $currency_id,
                'value_paid'        => $payment->value_paid ?? 0,
            ];
        }
        return  $paymentData;
    }


    /**
     * @throws Exception
     */
    public function getPaymentExchangeRate(Request $request, $company_id = 0, $currency_id = 272): ?object
    {
        $paymentExchangeRate    = null;
        if($request->payment_exchange_rate){
            if(is_array($request->payment_exchange_rate)){
                $payment    = (object) array_merge($request->payment_exchange_rate, []);
            }else if (is_string($request->payment_exchange_rate)){
                $payment    = json_decode($request->payment_exchange_rate);
            }
            $currencyId    = $payment->currency_id ?? $currency_id;
            $currency       = DB::table('currency_sys', 'c')
                ->join('currency AS d', 'd.id', '=', 'c.currency_id')
                ->where('c.company_id', $company_id)
                ->where('c.currency_id', $currencyId)
                ->select('c.*', 'd.CurrencyISO', 'd.CurrencyName', 'd.Money', 'd.Symbol')
                ->first();
            if (!$currency) {
                throw new Exception("No se encontró la moneda con id: $currencyId, debe crearla en la sección de Ajustes/monedas.");
            }
            $paymentExchangeRate  = (object)[
                'source_currency_code'          => "COP",
                'source_currency_base_rate'     => $payment->base_rate ?? 0,
                'target_currency_code'          => $currency->CurrencyISO,
                'calculation_rate'              => $payment->exchange_rate ?? 0,
                'date'                          => $payment->rate_date ?? date("Y-m-d"),
                'national_currency'             => $currency->national_currency,
            ];
        }

        return $paymentExchangeRate;
    }

    public function getDiscrepancyResponse(Request $request)
    {
        if(!isset($request->discrepancy_response) || !$request->discrepancy_response){
            return null;
        }
        $discResponse           = $request->discrepancy_response;
        if (is_array($discResponse)) {
            $discResponse           = (object) array_merge($request->discrepancy_response, []);
        } else if(is_string($discResponse)) {
            $discResponse           = json_decode($request->discrepancy_response);
        }
        $discrepancyResponse    = DiscrepancyResponse::findOrFail($discResponse->response_id ?? 2);
        $discrepancyResponse->reference_id  = $discResponse->reference_id ?? null;
        return $discrepancyResponse;
    }

}
