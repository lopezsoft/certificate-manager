<?php

namespace App\Modules\Taxes;

use App\Common\HttpResponseMessages;
use App\Models\Taxes\Tax;
use App\Models\Taxes\TaxRate;
use App\Models\Types\TypeLiability;
use App\Models\Types\TypeRegime;
use Illuminate\Http\JsonResponse;

class TaxTables
{
    public function getTaxRates(): JsonResponse
    {
        return HttpResponseMessages::getResponse([
            'dataRecords'   => [
                'data'  => TaxRate::all(),
            ],
        ]);
    }

    public function getTaxes(): JsonResponse
    {
        return HttpResponseMessages::getResponse([
            'dataRecords'   => [
                'data'  => Tax::all(),
            ],
        ]);
    }

    public function getTaxRegime(): JsonResponse
    {
        return HttpResponseMessages::getResponse([
            'dataRecords'   => [
                'data'  => TypeRegime::all(),
            ],
        ]);
    }

    public function getTaxLevel(): JsonResponse
    {
        return HttpResponseMessages::getResponse([
            'dataRecords'   => [
                'data'  => TypeLiability::all(),
            ],
        ]);
    }

}
