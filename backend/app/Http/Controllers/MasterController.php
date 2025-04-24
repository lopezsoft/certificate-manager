<?php

namespace App\Http\Controllers;

use App\Services\ReferencedTablesService;
use Illuminate\Http\JsonResponse;

class MasterController extends Controller
{
    public function getHealthUserType(): JsonResponse
    {
        return ReferencedTablesService::getHealthUserType();
    }
    public function getHealthContracting(): JsonResponse
    {
        return ReferencedTablesService::getHealthContracting();
    }
    public function getHealthCoverage(): JsonResponse
    {
        return ReferencedTablesService::getHealthCoverage();
    }
    public function getDiscountCodes(): JsonResponse
    {
        return ReferencedTablesService::getDiscountCodes();
    }
    public function geMeansPayment(): JsonResponse
    {
        return ReferencedTablesService::geMeansPayment();
    }

    public function getPaymentMethods(): JsonResponse
    {
        return ReferencedTablesService::getPaymentMethods();
    }

    public function getReferencePrice(): JsonResponse
    {
        return ReferencedTablesService::getReferencePrice();
    }
    public function getTypeItemIdentifications(): JsonResponse
    {
        return ReferencedTablesService::getTypeItemIdentifications();
    }
    public function getQuantityUnits(): JsonResponse
    {
        return ReferencedTablesService::getQuantityUnits();
    }
    public function getTypeOrganization(): JsonResponse
    {
        return ReferencedTablesService::getTypeOrganization();
    }
    public function getIdentityDocuments(): JsonResponse
    {
        return ReferencedTablesService::getIdentityDocuments();
    }
    public function getOperationType(): JsonResponse
    {
        return ReferencedTablesService::getOperationType();
    }
    public function getDocumentType(): JsonResponse
    {
       return ReferencedTablesService::getDocumentType();
    }
    public function getDestinationEnvironment(): JsonResponse
    {
        return ReferencedTablesService::getDestinationEnvironment();
    }

    public function getCorrectionNotes(): JsonResponse
    {
        return ReferencedTablesService::getCorrectionNotes();
    }

    public function getCurrencies(): JsonResponse
    {
        return ReferencedTablesService::getCurrencies();
    }


}
