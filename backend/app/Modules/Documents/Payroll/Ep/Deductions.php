<?php

namespace App\Modules\Documents\Payroll\Ep;

use Exception;
use Illuminate\Http\Request;

class Deductions
{
    static function getDeductions(Request $request): ?object
    {
        $deductions = null;
        try {
            if($request->deductions) {
                $deductions = (object) [];
                if(is_array($request->deductions)) {
                    $data = (object) array_merge($request->deductions, []);
                }else {
                    $data   = json_decode($request->deductions);
                }

                $deductions->health                 = (object)$data->health ?? null;
                $deductions->pension_fund           = self::getPensionFund($data);
                $deductions->fundSP                 = self::getFundSP($data);
                $deductions->trade_union            = self::getTradeUnion($data);
                $deductions->sanctions              = self::getSanctions($data);
                $deductions->libranzas              = self::getLibranzas($data);
                $deductions->third_party_payment    = self::getThirdPartyPayment($data);
                $deductions->advances               = self::getAdvances($data);
                $deductions->other_deductions       = self::getOtherDeductions($data);

                if(isset($data->voluntary_pension)){
                    $deductions->voluntary_pension        = floatval($data->voluntary_pension) > 0 ? $data->voluntary_pension : null;
                }
                if(isset($data->retefuente)){
                    $deductions->retefuente        = floatval($data->retefuente) > 0 ? $data->retefuente : null;
                }
                if(isset($data->afc)){
                    $deductions->afc                = floatval($data->afc) > 0 ? $data->afc : null;
                }
                if(isset($data->cooperative)){
                    $deductions->cooperative        = floatval($data->cooperative) > 0 ? $data->cooperative : null;
                }
                if(isset($data->tax_embargo)){
                    $deductions->tax_embargo        = floatval($data->tax_embargo) > 0 ? $data->tax_embargo : null;
                }
                if(isset($data->complementary_plan)){
                    $deductions->complementary_plan = floatval($data->complementary_plan) > 0 ? $data->complementary_plan : null;
                }
                if(isset($data->education)){
                    $deductions->education          = floatval($data->education) > 0 ? $data->education : null;
                }
                if(isset($data->refund)){
                    $deductions->refund             = floatval($data->refund) > 0 ? $data->refund : null;
                }
                if(isset($data->debt)){
                    $deductions->debt               = floatval($data->debt) > 0 ? $data->debt : null;
                }

            }
        } catch (Exception $e) {
            $deductions = null;
        }
        return $deductions;
    }

    private static function getFundSP($data) {
        $aData          = $data->fundSP ?? null;
        $fundSP         = [];
        if($aData) {
            foreach ($aData as $key => $value) {
                if($value > 0) {
                    $fundSP[$key] = $value;
                }
            }
        }

        return count($fundSP) > 0 ? (object)$fundSP : null;
    }

    private static function getPensionFund($data) {
        $aData          = $data->pension_fund ?? null;
        $pension_fund   = [];
        if($aData) {
            foreach ($aData as $key => $value) {
                if($value > 0) {
                    $pension_fund[$key] = $value;
                }
            }
        }

        return count($pension_fund) > 0 ? (object)$pension_fund : null;
    }

    private static function getOtherDeductions($data) {
        $aData     = $data->other_deductions ?? null;
        if($aData){
            $aData  = (object)$aData;
        }
        $other_deductions = [];

        if($aData) {
            foreach ($aData as $key => $value) {
                if($value['other_deduction'] > 0) {
                    $other_deductions[] = (object)$value;
                }
            }
        }
        return count($other_deductions) > 0 ? (object)$other_deductions : null;
    }

    private static function getAdvances($data) {
        $aData     = $data->advances ?? null;
        if($aData){
            $aData  = (object)$aData;
        }
        $advances = [];

        if($aData) {
            foreach ($aData as $key => $value) {
                if($value['advance'] > 0) {
                    $advances[] = (object)$value;
                }
            }
        }

        return count($advances) > 0 ? (object)$advances : null;
    }

    private static function getThirdPartyPayment($data) {
        $aData     = $data->third_party_payment ?? null;
        if($aData){
            $aData  = (object)$aData;
        }
        $third_party_payment = [];

        if($aData) {
            foreach ($aData as $key => $value) {
                if($value['third_party_pay'] > 0) {
                    $third_party_payment[] = (object)$value;
                }
            }
        }

        return count($third_party_payment) > 0 ? (object)$third_party_payment : null;
    }

    private static function getLibranzas($data) {
        $aData     = $data->libranzas ?? null;
        if($aData){
            $aData  = $aData;
        }

        $libranzas = [];

        if($aData) {
            foreach ($aData as $key => $value) {
                if($value['deduction'] > 0) {
                    $libranzas[] = (object)$value;
                }
            }
        }

        return count($libranzas) > 0 ? (object)$libranzas : null;
    }

    private static function getSanctions($data) {
        $aData     = $data->sanctions ?? null;
        if($aData){
            $aData  = (object)$aData;
        }
        $sanctions = [];

        if($aData) {
            foreach ($aData as $key => $value) {
                if($value['sanctionPublic'] > 0 || $value['sanctionPriv'] > 0) {
                    $sanctions[] = (object)$value;
                }
            }
        }

        return count($sanctions) > 0 ? (object)$sanctions : null;
    }

    private static function getTradeUnion($data) {
        $aData     = $data->trade_union ?? null;
        if($aData){
            $aData  = (object)$aData;
        }
        $trade_union = [];

        if($aData) {
            foreach ($aData as $key => $value) {
                if($value['percentage'] > 0 && $value['deduction'] > 0) {
                    $trade_union[] = (object)$value;
                }
            }
        }

        return count($trade_union) > 0 ? (object)$trade_union : null;
    }
}
