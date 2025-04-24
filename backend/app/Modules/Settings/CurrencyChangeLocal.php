<?php

namespace App\Modules\Settings;

use App\Common\HttpResponseMessages;
use App\Common\MessageExceptionResponse;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CurrencyChangeLocal
{
    private string $APIKEY    = "2097|tbf^pf2THM1bJ*MDFH^U_WTp1TWuMaa8";
    public function getChangeLocal(Request $request): JsonResponse
    {
        try {
            $query  = DB::table('currency_sys')
                ->where(['national_currency' => 1])
                ->first();
            if(!$query){
                throw new Exception('Debe crear la moneda nacional.', 404);
            }
            $query  = DB::table("currency")
                ->where(['id' => $query->currency_id])
                ->first();
            if (!$query) {
                throw new Exception('No existe la moneda.', 404);
            }
            $source     = $request->source;
            $target     = $query->CurrencyISO;
            $quantity   = $request->quantity ?? 1;
            $url        = "https://api.devises.zone/v1/quotes/{$source}/{$target}/json?quantity={$quantity}&key={$this->APIKEY}";
            $data       = json_decode( file_get_contents($url) );
            return HttpResponseMessages::getResponse([
                'records'       => [[
                    'value'         => round($data->result->value, 2),
                    'amount'        => round($data->result->amount, 2),
                ]]
            ]);
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }

    public function getChange(Request $request): JsonResponse
    {
        try {
            $source     = $request->source;
            $target     = $request->target;
            $quantity   = $request->quantity;
            $url        = "https://api.devises.zone/v1/quotes/{$source}/{$target}/json?quantity={$quantity}&key={$this->APIKEY}";
            $data       = json_decode( file_get_contents($url) );
            return HttpResponseMessages::getResponse([
                'value'         => $data->result->value,
                'amount'        => $data->result->amount,
            ]);
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }
}
