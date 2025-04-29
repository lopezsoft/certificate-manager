<?php

namespace App\Http\Controllers;

use App\Services\ReferencedTablesService;
use Illuminate\Http\JsonResponse;

class MasterController extends Controller
{
    public function getTypeOrganization(): JsonResponse
    {
        return ReferencedTablesService::getTypeOrganization();
    }
    public function getIdentityDocuments(): JsonResponse
    {
        return ReferencedTablesService::getIdentityDocuments();
    }

    public function getCurrencies(): JsonResponse
    {
        return ReferencedTablesService::getCurrencies();
    }

}
