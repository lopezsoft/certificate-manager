<?php

namespace App\Http\Controllers;

use App\Queries\QueryParams;
use App\Services\ReferencedTablesService;
use Illuminate\Http\JsonResponse;

class EpController extends Controller
{
    private QueryParams $queryParams;

    public function __construct() {
        $this->queryParams  = new QueryParams();
        $this->queryParams->where   = [
            'state'    => 1
        ];
    }
    public function getAdjustmentNoteType(): JsonResponse
    {
        return ReferencedTablesService::getAdjustmentNoteType();
    }

    public function getContractType(): JsonResponse
    {
        return ReferencedTablesService::getContractType();
    }


    public function getDisabilityType(): JsonResponse
    {
        return ReferencedTablesService::getDisabilityType();
    }

    public function getExtraHours(): JsonResponse
    {
        return ReferencedTablesService::getExtraHours();
    }


    public function getPayrollPeriod(): JsonResponse
    {
        return ReferencedTablesService::getPayrollPeriod();
    }


    public function getWorkerSubtype(): JsonResponse
    {
        return ReferencedTablesService::getWorkerSubtype();
    }


    public function getWorkerType(): JsonResponse
    {
        return ReferencedTablesService::getWorkerType();
    }
}
