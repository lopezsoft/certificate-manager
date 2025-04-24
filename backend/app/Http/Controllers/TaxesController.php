<?php

namespace App\Http\Controllers;

use App\Modules\Taxes\TaxTables;
use Illuminate\Http\JsonResponse;

class TaxesController extends Controller
{

    public function getTaxRates(): JsonResponse
    {
        return (new TaxTables())->getTaxRates();
    }

    public function getTaxes(): JsonResponse
    {
        return (new TaxTables())->getTaxes();
    }

    public function getTaxRegime(): JsonResponse
    {
        return (new TaxTables())->getTaxRegime();
    }

    public function getTaxLevel(): JsonResponse
    {
        return (new TaxTables())->getTaxLevel();
    }

}
