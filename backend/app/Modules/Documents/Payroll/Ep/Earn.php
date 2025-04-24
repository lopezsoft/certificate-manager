<?php

namespace App\Modules\Documents\Payroll\Ep;

use App\Models\Ep\DisabilityType;
use Exception;
use Illuminate\Http\Request;

class Earn
{
    static function getEarn(Request $request): ?object
    {
        $earn   = null;
        try {
            if($request->earn) {
                $earn   = (object) [];
                if(is_array($request->earn)) {
                    $data   = (object) array_merge($request->earn, []);
                }else {
                    $data   = json_decode($request->earn);
                }

                $earn->basic                = (object) $data->basic ?? null;
                $earn->transport            = (isset($data->transport)) ? self::getTransport($data) : null;
                $earn->HEDs                 = (isset($data->HEDs)) ? self::getExtraHours($data, 'HEDs') : null;
                $earn->HENs                 = (isset($data->HENs)) ? self::getExtraHours($data, 'HENs') : null;
                $earn->HRNs                 = (isset($data->HRNs)) ? self::getExtraHours($data, 'HRNs') : null;
                $earn->HEDDFs               = (isset($data->HEDDFs)) ? self::getExtraHours($data, 'HEDDFs') : null;
                $earn->HRDDFs               = (isset($data->HRDDFs)) ? self::getExtraHours($data, 'HRDDFs') : null;
                $earn->HENDFs               = (isset($data->HENDFs)) ? self::getExtraHours($data, 'HENDFs') : null;
                $earn->HRNDFs               = (isset($data->HRNDFs)) ? self::getExtraHours($data, 'HRNDFs') : null;
                $earn->vacations            = self::getVacations($data);
                $earn->bonus                = self::getBonus($data);
                $earn->cesantias            = self::getCesantias($data);
                $earn->incapacity           = self::getIncapacity($data);
                $earn->licenses             = self::getLicenses($data);
                $earn->bonuses              = self::getBonuses($data);
                $earn->assistances          = self::getAssistances($data);
                $earn->legal_strikes        = self::getLegalStrikes($data);
                $earn->other_concepts       = self::getOtherConcepts($data);
                $earn->compensations        = self::getCompensations($data);
                $earn->bondEPCTVs           = self::getBondEPCTVs($data);
                $earn->commissions          = self::getCommissions($data);
                $earn->payments_third_party = self::getPaymentsThirdParty($data);
                $earn->advances             = self::getAdvances($data);

                if(isset($data->endowment)){
                    $earn->endowment        = floatval($data->endowment) > 0 ? $data->endowment : null;
                }

                if(isset($data->sustaining_support)){
                    $earn->sustaining_support        = floatval($data->sustaining_support) > 0 ? $data->sustaining_support : null;
                }
                if(isset($data->teleworking)){
                    $earn->teleworking        = floatval($data->teleworking) > 0 ? $data->teleworking : null;
                }
                if(isset($data->withdrawal_bonus)){
                    $earn->withdrawal_bonus        = floatval($data->withdrawal_bonus) > 0 ? $data->withdrawal_bonus : null;
                }

                if(isset($data->indemnification)){
                    $earn->indemnification        = floatval($data->indemnification) > 0 ? $data->indemnification : null;
                }
                if(isset($data->refund)){
                    $earn->refund        = floatval($data->refund) > 0 ? $data->refund : null;
                }

            }

        } catch (Exception $e) {
            $earn = null;
        }
        return $earn;
    }

    private static function getTransport($data): ?object
    {
        $aData          = $data->transport ?? null;
        $transports     = [];
        if($aData) {
            foreach ($aData as $key => $value) {
                if($value > 0) {
                    $transports[$key] = $value;
                }
            }
        }

        return count($transports) > 0 ? (object)$transports : null;
    }

    private static function getCesantias($data): ?object
    {
        $aData  = $data->cesantias ?? null;
        if($aData){
            $aData  = (object)$aData;
        }
        $cesantias  = null;
        if($aData) {
            if($aData->payment > 0) {
                $cesantias = $aData;
            }
        }

        return $cesantias;
    }

    private static function getBonus($data): ?object
    {
        $aData  = $data->bonus ?? null;
        if($aData) {
            $aData  = (object)$aData;
        }
        $bonus  = null;
        if($aData) {
            if($aData->amount > 0 || $aData->payment > 0 || $aData->non_salary_payment > 0) {
                $bonus = $aData;
            }
        }

        return $bonus;
    }

    private static function getVacations($data): ?object
    {
        $licData    = $data->vacations ?? null;
        $vacations   = [];
        if($licData) {
            foreach ($licData as $key => $value) {
                if($value['amount'] > 0 && $value['payment'] > 0) {
                    $vacations[$key] = $value;
                }
            }
        }

        return count($vacations) > 0 ? (object)$vacations : null;
    }

    private static function getAdvances($data): ?object
    {
        $aData      = $data->advances ?? null;
        $advances   = [];
        if($aData) {
            foreach ($aData as $value) {
                if($value['advance'] > 0) {
                    $advances[] = (object)$value;
                }
            }
        }

        return count($advances) > 0 ? (object)$advances : null;
    }

    private static function getPaymentsThirdParty($data): ?object
    {
        $aData                  = $data->payments_third_party ?? null;
        $payments_third_party   = [];
        if($aData) {
            foreach ($aData as $value) {
                if($value['payment_third_party'] > 0) {
                    $payments_third_party[] = (object)$value;
                }
            }
        }

        return count($payments_third_party) > 0 ? (object)$payments_third_party : null;
    }

    private static function getCommissions($data): ?object
    {
        $aData          = $data->commissions ?? null;
        $commissions    = [];
        if($aData) {
            foreach ($aData as $value) {
                if($value['commission'] > 0) {
                    $commissions[] = (object)$value;
                }
            }
        }

        return count($commissions) > 0 ? (object)$commissions : null;
    }

    private static function getBondEPCTVs($data): ?object
    {
        $aData          = $data->bondEPCTVs ?? null;
        $bondEPCTVs     = [];
        if($aData) {
            foreach ($aData as $value) {
                if($value['paymentS'] > 0 || $value['paymentNS'] > 0 || $value['payment_foodS'] > 0 || $value['payment_foodNS'] > 0) {
                    $bondEPCTVs[] = (object)$value;
                }
            }
        }

        return count($bondEPCTVs) > 0 ? (object)$bondEPCTVs : null;
    }

    private static function getCompensations($data): ?object
    {
        $aData          = $data->compensations ?? null;
        $compensations  = [];
        if($aData) {
            foreach ($aData as $value) {
                if($value['compensationO'] > 0 || $value['compensationE'] > 0) {
                    $compensations[] = (object)$value;
                }
            }
        }

        return count($compensations) > 0 ? (object)$compensations : null;
    }

    private static function getOtherConcepts($data): ?array
    {
        $aData              = $data->other_concepts ?? [];
        $conceptsData       = null;
        if(count($aData) > 0) {
            $concepts           = (object)$aData;
            foreach ($concepts as $value) {
                $concept            = (object)$value;
                $other_concepts     = (object)[];
                if(isset($concept->conceptS) && $concept->conceptS > 0){
                    $other_concepts->conceptS    = $concept->conceptS;
                }
                if(isset($concept->conceptNS) && $concept->conceptNS > 0){
                    $other_concepts->conceptNS    = $concept->conceptNS;
                }

                if(isset($concept->descripcion) && ( isset($concept->conceptS) ||  isset($concept->conceptNS))){
                    $other_concepts->descripcion    = $concept->descripcion;
                }
                $other_concepts = (array)$other_concepts;
                if(count($other_concepts) > 0){
                    $conceptsData[]   = (object)$other_concepts;
                }
            }
        }

        return $conceptsData;
    }

    private static function getLegalStrikes($data): ?object
    {
        $aData          = $data->legal_strikes ?? null;
        $legal_strikes  = [];
        if($aData) {
            foreach ($aData as $value) {
                if($value['amount'] > 0) {
                    $legal_strikes[] = (object)$value;
                }
            }
        }

        return count($legal_strikes) > 0 ? (object)$legal_strikes : null;
    }

    private static function getAssistances($data): ?object
    {
        $asisData       = $data->assistances ?? null;
        $assistances    = [];
        if($asisData) {
            foreach ($asisData as $value) {
                $data   = [];
                foreach ($value as $key => $val) {
                    if($val > 0) {
                        $data[$key] =$val;
                    }
                }
                if(count($data) > 0){
                    $assistances[] = (object)$data;
                }
            }
        }

        return count($assistances) > 0 ? (object)$assistances : null;
    }

    private static function getBonuses($data): ?object
    {
        $licBonus  = $data->bonuses ?? null;
        $bonuses   = [];
        if($licBonus) {
            foreach ($licBonus as $key => $value) {
                $data   = [];
                foreach ($value as $keya => $valuea){
                    if($valuea > 0) {
                        $data[$keya]    = $valuea;
                    }
                }
                if(count($data) > 0){
                    $bonuses[] = (object)$data;
                }
            }
        }

        return count($bonuses) > 0 ? (object)$bonuses : null;
    }

    private static function getLicenses($data): ?object
    {
        $licData    = $data->licenses ?? null;
        $licenses   = [];
        if($licData) {
            foreach ($licData as $key => $value) {
                $objectData = (Object) $value;
                if((isset($objectData->amount) && isset($objectData->payment)) && ($objectData->amount > 0 && $objectData->payment > 0)) {
                    $licenses[$key] = $objectData;
                }else if((isset($objectData->amount)) && ($objectData->amount > 0)) {
                    $licenses[$key] = $objectData;
                }
            }
        }

        return count($licenses) > 0 ? (object)$licenses : null;
    }

    private static function getIncapacity($data): ?array
    {
        $indata     = $data->incapacity ?? null;
        $incapacity = [];

        if($indata) {
            foreach ($indata as $key => $value) {
                if($value['amount'] > 0 && $value['payment'] > 0) {
                    $incapacity[] = (object) [
                        'start_date'        => $value['start_date'],
                        'final_date'        => $value['final_date'],
                        'amount'            => $value['amount'],
                        'type'              => DisabilityType::findOrFail($value['type_id'] ?? 1)->first(),
                        'payment'           => $value['payment']
                    ];
                }
            }
        }

        return count($incapacity) > 0 ? $incapacity : null;
    }

    private static function getExtraHours($dataEh, $eh): ?array
    {

        $data   = [];

        try {
            $dt     = (array) $dataEh;
            $dt     = $dt[$eh];

            foreach ($dt as $value) {
                if($value['amount'] > 0 && $value['payment'] > 0) {
                    $data[] = $value;
                }
            }
        } catch (Exception $e) {
            $data   = [];
        }

        return (count($data) > 0) ? $data : null;
    }
}
