<?php

namespace App\Services;

use App\Common\HttpResponseMessages;
use App\Models\EntityDocumentType;
use App\Models\IdentityDocument;
use App\Models\TypeOrganization;
use Illuminate\Http\JsonResponse;

class ReferencedTablesService
{
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
    public static function getEntityDocumentTypes(): JsonResponse
    {
        return HttpResponseMessages::getResponse([
            "dataRecords" => [
                "data"  => EntityDocumentType::where('active', true)->get(),
            ]
        ]);
    }
}

