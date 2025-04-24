<?php

namespace App\Services;

use App\Common\HttpResponseMessages;
use App\Models\Environment;
use App\Models\Ep\AdjustmentNoteType;
use App\Models\Ep\ContractType;
use App\Models\Ep\DisabilityType;
use App\Models\Ep\ExtraHours;
use App\Models\Ep\PayrollPeriod;
use App\Models\Ep\WorkerSubtype;
use App\Models\Ep\WorkerType;
use App\Models\Invoice\CorrectionNote;
use App\Models\Invoice\Discount;
use App\Models\Invoice\IdentityDocument;
use App\Models\Invoice\MeansPayment;
use App\Models\Invoice\PaymentMethod;
use App\Models\Invoice\ReferencePrice;
use App\Models\QuantityUnit;
use App\Models\Types\TypeCurrency;
use App\Models\Types\TypeDocument;
use App\Models\Types\TypeItemIdentification;
use App\Models\Types\TypeOperation;
use App\Models\Types\TypeOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ReferencedTablesService
{
    // Health
    public static function getHealthUserType(): JsonResponse
    {
        return HttpResponseMessages::getResponse([
            "dataRecords" => [
                "data"  => DB::table('health_user_type')
                    ->select('code', 'description')
                    ->get(),
            ]
        ]);
    }
    public static function getHealthContracting(): JsonResponse
    {
        return HttpResponseMessages::getResponse([
            "dataRecords" => [
                "data"  => DB::table('health_contracting_payment_modalities')
                    ->select('code', 'description')
                    ->get(),
            ]
        ]);
    }
    public static function getHealthCoverage(): JsonResponse
    {
        return HttpResponseMessages::getResponse([
            "dataRecords" => [
                "data"  => DB::table('health_coverage')
                    ->select('code', 'description')
                    ->get(),
            ]
        ]);
    }

    // Payroll
    public static function getWorkerType(): JsonResponse
    {
        return HttpResponseMessages::getResponse([
            "dataRecords" => [
                "data"  => WorkerType::all(),
            ]
        ]);
    }
    public static function getWorkerSubtype(): JsonResponse
    {
        return HttpResponseMessages::getResponse([
            "dataRecords" => [
                "data"  => WorkerSubtype::all(),
            ]
        ]);
    }
    public static function getPayrollPeriod(): JsonResponse
    {
        return HttpResponseMessages::getResponse([
            "dataRecords" => [
                "data"  => PayrollPeriod::all(),
            ]
        ]);
    }
    public static function getExtraHours(): JsonResponse
    {
        return HttpResponseMessages::getResponse([
            "dataRecords" => [
                "data"  => ExtraHours::all(),
            ]
        ]);
    }
    public static function getDisabilityType(): JsonResponse
    {
        return HttpResponseMessages::getResponse([
            "dataRecords" => [
                "data"  => DisabilityType::all(),
            ]
        ]);
    }
    public static function getAdjustmentNoteType(): JsonResponse
    {
        return HttpResponseMessages::getResponse([
            "dataRecords" => [
                "data"  => AdjustmentNoteType::all(),
            ]
        ]);
    }
    public static function getContractType(): JsonResponse
    {
        return HttpResponseMessages::getResponse([
            "dataRecords" => [
                "data"  => ContractType::all(),
            ]
        ]);
    }

    // Documents electronic
    public static function getCurrencies(): JsonResponse
    {
        return HttpResponseMessages::getResponse([
            "dataRecords" => [
                "data"  => TypeCurrency::query()->where('active', 1)->get(),
            ]
        ]);
    }
    public static function geMeansPayment(): JsonResponse
    {
        return HttpResponseMessages::getResponse([
            "dataRecords" => [
                "data"  => MeansPayment::all(),
            ]
        ]);
    }
    public static function getPaymentMethods(): JsonResponse
    {
        return HttpResponseMessages::getResponse([
            "dataRecords" => [
                "data"  => PaymentMethod::all(),
            ]
        ]);
    }
    public static function getReferencePrice(): JsonResponse
    {
        return HttpResponseMessages::getResponse([
            "dataRecords" => [
                "data"  => ReferencePrice::all(),
            ]
        ]);
    }
    public static function getTypeItemIdentifications(): JsonResponse
    {
        return HttpResponseMessages::getResponse([
            "dataRecords" => [
                "data"  => TypeItemIdentification::all(),
            ]
        ]);
    }
    public static function getQuantityUnits(): JsonResponse
    {
        return HttpResponseMessages::getResponse([
            "dataRecords" => [
                "data"  => QuantityUnit::all(),
            ]
        ]);
    }
    public static function getTypeOrganization(): JsonResponse
    {
        return HttpResponseMessages::getResponse([
            "dataRecords" => [
                "data"  => TypeOrganization::all(),
            ]
        ]);
    }
    public static function getIdentityDocuments(): JsonResponse
    {
        return HttpResponseMessages::getResponse([
            "dataRecords" => [
                "data"  => IdentityDocument::all(),
            ]
        ]);
    }
    public static function getOperationType(): JsonResponse
    {
        return HttpResponseMessages::getResponse([
            "dataRecords" => [
                "data"  => TypeOperation::all(),
            ]
        ]);
    }
    public static function getDocumentType(): JsonResponse
    {
        return HttpResponseMessages::getResponse([
            "dataRecords" => [
                "data"  => TypeDocument::query()->where('active', 1)->orderBy('code')->get(),
            ]
        ]);
    }
    public static function getDestinationEnvironment(): JsonResponse
    {
        return HttpResponseMessages::getResponse([
            'dataRecords'   => [
                'data'  => Environment::all(),
            ],
        ]);
    }

    public static function getCorrectionNotes(): JsonResponse
    {
        return HttpResponseMessages::getResponse([
            'dataRecords'   => [
                'data'  => CorrectionNote::all(),
            ],
        ]);
    }

    public static function getDiscountCodes(): JsonResponse
    {
        return HttpResponseMessages::getResponse([
            'dataRecords'   => [
                'data'  => Discount::all()->where('active', 1),
            ],
        ]);
    }
}
